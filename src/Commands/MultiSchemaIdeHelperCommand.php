<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;

class MultiSchemaIdeHelperCommand extends IdeHelperCommand
{
    protected $name = 'lighthouse:multi-ide-helper';

    protected $description = 'Generate IDE helper files for GraphQL schemas.';

    public function handle(): int
    {
        $schemas = $this->resolveSchemas();

        $selectedSchema = $this->resolveSchemaArgument();

        if ( $selectedSchema ) {
            if ( !isset( $schemas[$selectedSchema] ) ) {
                $this->error( "Schema '{$selectedSchema}' not found." );
                $this->info( 'Available schemas: ' . implode( ', ', array_keys( $schemas ) ) );
                return 1;
            }

            $this->info( "Generating IDE helper for schema: {$selectedSchema}" );
            $this->generateForSchema( $selectedSchema, $schemas[$selectedSchema] );
        } else {
            foreach ( $schemas as $key => $schemaData ) {
                $this->info( "Generating IDE helper for schema: {$key}" );
                $this->generateForSchema( $key, $schemaData );
            }
        }

        $this->info( "\nIt is recommended to add generated files to your .gitignore file." );

        return 0;
    }

    /**
     * Generate IDE helper for a specific schema.
     *
     * @param string $schemaKey The name of the schema to generate the IDE helper for.
     * @param array $schemaData The schema data.
     */
    protected function generateForSchema( string $schemaKey, array $schemaData ): void
    {
        $schemaPath = $schemaData['schema_path'];

        if ( !file_exists( $schemaPath ) ) {
            $this->warn( "  ⚠ '{$schemaKey}' — schema file not found: {$schemaPath}" );
            return;
        }

        $schemaSourceProvider = new SchemaStitcher( $schemaPath );

        $this->generateSchemaDirectiveDefinitionsForSchema( $schemaKey );
        $this->generateProgrammaticTypesForSchema( $schemaKey, $schemaSourceProvider );

        if ( $schemaKey === 'default' ) {
            $this->phpIdeHelper();
        }
    }

    /**
     * Generate schema directive definitions for a specific schema.
     *
     * @param string $schemaKey The name of the schema to generate the schema directive definitions for.
     */
    protected function generateSchemaDirectiveDefinitionsForSchema( string $schemaKey ): void
    {
        if ( $schemaKey === 'default' ) {
            $this->schemaDirectiveDefinitions( app( \Nuwave\Lighthouse\Schema\DirectiveLocator::class ) );
        } else {
            $customPath = $this->getSchemaDirectivesPath( $schemaKey );
            $this->generateSchemaDirectivesToPath( $customPath );
        }
    }

    /**
     * Generate schema directive definitions to a specific path.
     *
     * @param string $filePath The path to generate the schema directive definitions to.
     */
    protected function generateSchemaDirectivesToPath( string $filePath ): void
    {
        $this->schemaDirectiveDefinitions( app( \Nuwave\Lighthouse\Schema\DirectiveLocator::class ) );

        $originalPath = static::schemaDirectivesPath();
        if ( file_exists( $originalPath ) ) {
            \Safe\file_put_contents( $filePath, \Safe\file_get_contents( $originalPath ) );
            \Safe\unlink( $originalPath );
        }

        $this->info( "  Wrote schema directive definitions to {$filePath}." );
    }

    /**
     * Generate programmatic types for a specific schema.
     *
     * @param string $schemaKey The name of the schema to generate the programmatic types for.
     * @param \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider $schemaSourceProvider The schema source provider.
     */
    protected function generateProgrammaticTypesForSchema( string $schemaKey, $schemaSourceProvider ): void
    {
        $originalProvider = app( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class );

        try {
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $schemaSourceProvider );
            app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class )->clear();

            if ( $schemaKey === 'default' ) {
                $this->programmaticTypes(
                    $schemaSourceProvider,
                    app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class ),
                    app( \Nuwave\Lighthouse\Schema\SchemaBuilder::class ),
                );
            } else {
                $customPath = $this->getProgrammaticTypesPath( $schemaKey );
                $this->generateProgrammaticTypesToPath( $schemaSourceProvider, $customPath );
            }
        } finally {
            app()->instance( \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider::class, $originalProvider );
        }
    }

    /**
     * Generate programmatic types to a specific path.
     *
     * @param \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider $schemaSourceProvider The schema source provider.
     * @param string $filePath The path to generate the programmatic types to.
     */
    protected function generateProgrammaticTypesToPath( $schemaSourceProvider, string $filePath ): void
    {
        $this->programmaticTypes(
            $schemaSourceProvider,
            app( \Nuwave\Lighthouse\Schema\AST\ASTCache::class ),
            app( \Nuwave\Lighthouse\Schema\SchemaBuilder::class ),
        );

        $originalPath = static::programmaticTypesPath();
        if ( file_exists( $originalPath ) ) {
            \Safe\file_put_contents( $filePath, \Safe\file_get_contents( $originalPath ) );
            \Safe\unlink( $originalPath );
        }

        $this->info( "  Wrote programmatic type definitions to {$filePath}." );
    }

    /**
     * Get the path to the schema directive definitions.
     *
     * @param string $schemaKey The name of the schema to get the path to the schema directive definitions for.
     * @return string The path to the schema directive definitions.
     */
    protected function getSchemaDirectivesPath( string $schemaKey ): string
    {
        if ( $schemaKey === 'default' ) {
            return base_path( 'schema-directives.graphql' );
        }

        return base_path( "schema-directives-{$schemaKey}.graphql" );
    }

    /**
     * Get the path to the programmatic types.
     *
     * @param string $schemaKey The name of the schema to get the path to the programmatic types for.
     * @return string The path to the programmatic types.
     */
    protected function getProgrammaticTypesPath( string $schemaKey ): string
    {
        if ( $schemaKey === 'default' ) {
            return static::programmaticTypesPath();
        }

        return base_path( "programmatic-types-{$schemaKey}.graphql" );
    }

    /**
     * Resolve the schema argument.
     *
     * @return string|null The schema argument.
     */
    protected function resolveSchemaArgument(): ?string
    {
        $arg    = $this->argument( 'schema' );
        $option = $this->option( 'schema' );

        if ( $option && !$arg ) {
            $this->warn( 'The --schema= option is deprecated. Use positional argument: lighthouse:multi-ide-helper admin' );
        }

        return $arg ?? $option ?? null;
    }

    /**
     * Resolve the schemas.
     *
     * @return array<string, array<string, string>> The schemas.
     */
    protected function resolveSchemas(): array
    {
        $schemas = app( GraphQLSchemaConfig::class )->multiSchemas;

        $schemas['default'] = [
            'schema_path' => config( 'lighthouse.schema_path' ),
            'route_uri'   => config( 'lighthouse.route.uri' ),
        ];

        return $schemas;
    }

    /** @return array<int, array<int, mixed>> */
    protected function getArguments(): array
    {
        return [
            ['schema', InputArgument::OPTIONAL, 'Schema name. If omitted, all schemas will be processed.'],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return array_merge( parent::getOptions(), [
            ['schema', 's', InputOption::VALUE_OPTIONAL, '[deprecated] Use positional argument instead.'],
        ] );
    }
}
