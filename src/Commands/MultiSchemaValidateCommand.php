<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\Validator as SchemaValidator;
use Symfony\Component\Console\Input\InputOption;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;

class MultiSchemaValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:multi-validate-schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate GraphQL schemas (default or specified).';

    /**
     * Handle the command.
     *
     * @param SchemaValidator $schemaValidator The schema validator instance.
     * @return int The exit code.
     */
    public function handle( SchemaValidator $schemaValidator ): int
    {
        $schemaConfig   = app( GraphQLSchemaConfig::class );
        $schemas        = $schemaConfig->multiSchemas;

        // Add main schema
        $schemas['default'] = [
            'schema_path'   => config( 'lighthouse.schema_path' ),
            'route_uri'     => config( 'lighthouse.route.uri' ),
        ];

        $selectedSchema = $this->option( 'schema' );

        if ( $selectedSchema ) {
            if ( !isset( $schemas[$selectedSchema] ) ) {
                $this->error( "Schema '{$selectedSchema}' not found." );
                $this->info( 'Available schemas: ' . implode( ', ', array_keys( $schemas) ) );
                return 1;
            }

            $this->info("Validating schema: {$selectedSchema}");
            $this->validateSchema( $selectedSchema, $schemas[$selectedSchema], $schemaValidator );
        } else {
            // By default validate only main schema
            $this->info( "Validating default schema" );
            $this->validateSchema( 'default', $schemas['default'], $schemaValidator );
        }

        $this->info("\nAll selected schemas are valid.");

        return 0;
    }

    /**
     * Validate the schema.
     *
     * @param string $schemaKey The key of the schema.
     * @param array $schemaData The schema data.
     * @param SchemaValidator $schemaValidator The schema validator instance.
     * @return void
     */
    protected function validateSchema( string $schemaKey, array $schemaData, SchemaValidator $schemaValidator ): void
    {
        $schemaPath = $schemaData['schema_path'];

        if ( !file_exists( $schemaPath ) ) {
            $this->warn("Schema file not found: {$schemaPath}");
            return;
        }

        // Save original SchemaSourceProvider
        $originalProvider = app( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class );

        try {
            // Create temporary SchemaSourceProvider for this schema
            $schemaSourceProvider = new SchemaStitcher( $schemaPath );

            // Temporarily replace SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $schemaSourceProvider );

            // Clear schema cache for forced recreation
            app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class )->clear();

            // Use the injected validator
            $schemaValidator->validate();

            $this->info("✓ Schema '{$schemaKey}' is valid.");

        } finally {
            // Restore original SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $originalProvider );
        }
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['schema', 's', InputOption::VALUE_OPTIONAL, 'Validate specific schema (default: main schema).'],
        ];
    }
}
