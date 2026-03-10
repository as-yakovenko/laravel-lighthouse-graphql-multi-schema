<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Symfony\Component\Console\Input\InputOption;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;

class MultiSchemaIdeHelperCommand extends IdeHelperCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:multi-ide-helper';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create IDE helper files for GraphQL schema (default or specified).';

    /**
     * Handle the command.
     *
     * @return int The exit code.
     */
    public function handle(): int
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

            $this->info( "Generating IDE helper for schema: {$selectedSchema}" );
            $this->generateForSchema( $selectedSchema, $schemas[$selectedSchema] );
        } else {
            // By default generate only for main schema
            $this->info( "Generating IDE helper for default schema" );
            $this->generateForSchema( 'default', $schemas['default'] );
        }

        $this->info( "\nIt is recommended to add generated files to your .gitignore file." );

        return 0;
    }

    /**
     * Generate the IDE helper for schema.
     *
     * @param string $schemaKey The key of the schema.
     * @param array $schemaData The schema data.
     * @return void
     */
    protected function generateForSchema( string $schemaKey, array $schemaData ): void
    {
        $schemaPath = $schemaData['schema_path'];

        if ( !file_exists( $schemaPath )) {
            $this->warn("Schema file not found: {$schemaPath}");
            return;
        }

        // Create temporary SchemaSourceProvider for this schema
        $schemaSourceProvider = new SchemaStitcher( $schemaPath );

        // Generate files using parent methods
        $this->generateSchemaDirectiveDefinitionsForSchema( $schemaKey );
        $this->generateProgrammaticTypesForSchema( $schemaKey, $schemaSourceProvider );
        // $this->generateFullSchemaForSchema( $schemaKey, $schemaSourceProvider );

        if ($schemaKey === 'default') {
            $this->phpIdeHelper();
        }
    }

    /**
     * Generate the schema directives for schema.
     *
     * @param string $schemaKey The key of the schema.
     * @return void
     */
    protected function generateSchemaDirectiveDefinitionsForSchema( string $schemaKey ): void
    {
        if ( $schemaKey === 'default' ) {
            // For default schema use parent method
            $this->schemaDirectiveDefinitions( app( \Nuwave\Lighthouse\Schema\DirectiveLocator::class ) );
        } else {
            // For other schemas generate directly to target location
            $customPath = $this->getSchemaDirectivesPath( $schemaKey );
            $this->generateSchemaDirectivesToPath( $customPath );
        }
    }

    /**
     * Generate the schema directives to path.
     *
     * @param string $filePath The file path.
     * @return void
     */
    protected function generateSchemaDirectivesToPath( string $filePath ): void
    {
        // Use parent method with temporary output redirection
        $this->schemaDirectiveDefinitions( app( \Nuwave\Lighthouse\Schema\DirectiveLocator::class ) );

        // Copy created file to target location
        $originalPath = static::schemaDirectivesPath();
        if ( file_exists( $originalPath ) ) {
            \Safe\file_put_contents( $filePath, \Safe\file_get_contents( $originalPath ) );
            \Safe\unlink( $originalPath ); // Remove temporary file
        }

        $this->info("Wrote schema directive definitions to {$filePath}.");
    }

    /**
     * Generate the programmatic types for schema.
     *
     * @param string $schemaKey The key of the schema.
     * @param $schemaSourceProvider The schema source provider.
     * @return void
     */
    protected function generateProgrammaticTypesForSchema( string $schemaKey, $schemaSourceProvider ): void
    {
        // Save original SchemaSourceProvider
        $originalProvider = app( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class );

        try {
            // Temporarily replace SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $schemaSourceProvider );

            // Clear schema cache for forced recreation
            app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class )->clear();

            if ($schemaKey === 'default') {
                // For default schema use parent method
                $this->programmaticTypes( $schemaSourceProvider, app(\Nuwave\Lighthouse\Schema\AST\ASTCache::class ), app( \Nuwave\Lighthouse\Schema\SchemaBuilder::class ) );
            } else {
                // For other schemas generate directly to target location
                $customPath = $this->getProgrammaticTypesPath( $schemaKey );
                $this->generateProgrammaticTypesToPath( $schemaSourceProvider, $customPath );
            }
        } finally {
            // Restore original SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $originalProvider );
        }
    }

    /**
     * Generate the programmatic types to path.
     *
     * @param $schemaSourceProvider The schema source provider.
     * @param string $filePath The file path.
     * @return void
     */
    protected function generateProgrammaticTypesToPath( $schemaSourceProvider, string $filePath ): void
    {
        // Use parent method with temporary output redirection
        $this->programmaticTypes( $schemaSourceProvider, app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class ), app( \Nuwave\Lighthouse\Schema\SchemaBuilder::class ) );

        // Copy created file to target location
        $originalPath = static::programmaticTypesPath();
        if ( file_exists( $originalPath ) ) {
            \Safe\file_put_contents( $filePath, \Safe\file_get_contents( $originalPath ) );
            \Safe\unlink( $originalPath ); // Remove temporary file
        }

        $this->info("Wrote definitions for programmatically registered types to {$filePath}.");
    }

    /**
     * Generate the full schema.
     *
     * @param string $schemaKey The key of the schema.
     * @param $schemaSourceProvider The schema source provider.
     * @return void
     */
    protected function generateFullSchemaForSchema( string $schemaKey, $schemaSourceProvider ): void
    {
        // Save original SchemaSourceProvider
        $originalProvider = app( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class );

        try {
            // Temporarily replace SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $schemaSourceProvider );

            // Clear schema cache for forced recreation
            app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class )->clear();

            $customPath = $this->getFullSchemaPath( $schemaKey );

            // Generate full schema
            $this->generateFullSchema( $schemaSourceProvider );

            // Move file to target location if not default schema
            if ( $schemaKey !== 'default' ) {
                $originalPath = $this->getFullSchemaPath( 'default' );
                if ( file_exists( $originalPath ) ) {
                    \Safe\file_put_contents( $customPath, \Safe\file_get_contents( $originalPath ) );
                    \Safe\unlink( $originalPath );
                }
            }
        } finally {
            // Restore original SchemaSourceProvider
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $originalProvider );
        }
    }

    /**
     * Generate the full schema.
     *
     * @param $schemaSourceProvider The schema source provider.
     * @return void
     */
    protected function generateFullSchema( $schemaSourceProvider ): void
    {
        $astCache = app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class );

        $astCache->clear();

        // Create new ASTBuilder with correct SchemaSourceProvider
        $astBuilder = new \Nuwave\Lighthouse\Schema\AST\ASTBuilder(
            app( \Nuwave\Lighthouse\Schema\DirectiveLocator::class ),
            $schemaSourceProvider,
            app( \Illuminate\Contracts\Events\Dispatcher::class ),
            $astCache
        );

        // Create new SchemaBuilder
        $schemaBuilder = new \Nuwave\Lighthouse\Schema\SchemaBuilder(
            app(\Nuwave\Lighthouse\Schema\TypeRegistry::class),
            $astBuilder
        );

        // Get full schema
        $schema         = $schemaBuilder->schema();
        $schemaString   = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );

        $filePath = $this->getFullSchemaPath( 'default' );
        \Safe\file_put_contents( $filePath, self::GENERATED_NOTICE . $schemaString );

        $this->info("Wrote full schema to {$filePath}.");
    }

    /**
     * Get the path to the schema directives file.
     *
     * @param string $schemaKey The key of the schema.
     * @return string The path to the schema directives file.
     */
    protected function getSchemaDirectivesPath( string $schemaKey ): string
    {
        if ($schemaKey === 'default') {
            return base_path() . '/schema-directives.graphql';
        }

        return base_path() . "/schema-directives-{$schemaKey}.graphql";
    }

    /**
     * Get the path to the programmatic types file.
     *
     * @param string $schemaKey The key of the schema.
     * @return string The path to the programmatic types file.
     */
    protected function getProgrammaticTypesPath( string $schemaKey ): string
    {
        if ( $schemaKey === 'default' ) {
            return static::programmaticTypesPath();
        }

        return base_path() . "/programmatic-types-{$schemaKey}.graphql";
    }

    /**
     * Get the path to the full schema file.
     *
     * @param string $schemaKey The key of the schema.
     * @return string The path to the full schema file.
     */
    protected function getFullSchemaPath(string $schemaKey): string
    {
        if ($schemaKey === 'default') {
            return base_path() . '/full-schema.graphql';
        }

        return base_path() . "/full-schema-{$schemaKey}.graphql";
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['schema', 's', InputOption::VALUE_OPTIONAL, 'Generate IDE helper for specific schema (default: main schema).'],
        ]);
    }
}
