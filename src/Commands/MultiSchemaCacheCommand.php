<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaSpecificASTCache;

class MultiSchemaCacheCommand extends Command
{
    protected $signature = 'lighthouse:multi-cache
        {schema? : Schema name to cache. Omit to cache all schemas.}';

    protected $description = 'Compile and cache GraphQL schemas (all schemas or a specific one).';

    public function handle(): int
    {
        $schemas = $this->resolveSchemas();

        $selectedSchema = $this->argument( 'schema' );

        if ( $selectedSchema ) {
            if ( !isset( $schemas[$selectedSchema] ) ) {
                $this->error( "Schema '{$selectedSchema}' not found." );
                $this->info( 'Available schemas: ' . implode( ', ', array_keys( $schemas ) ) );
                return 1;
            }

            return $this->cacheSchema( $selectedSchema, $schemas[$selectedSchema] ) ? 0 : 1;
        }

        $failed = false;
        foreach ( $schemas as $key => $schemaData ) {
            if ( !$this->cacheSchema( $key, $schemaData ) ) {
                $failed = true;
            }
        }

        if ( !$failed ) {
            $this->info( "\nAll schemas cached successfully." );
        }

        return $failed ? 1 : 0;
    }

    /**
     * Cache a specific schema.
     *
     * @param string $schemaKey The name of the schema to cache.
     * @param array $schemaData The schema data.
     * @return bool True if the schema was cached successfully, false otherwise.
     */
    protected function cacheSchema( string $schemaKey, array $schemaData ): bool
    {
        $schemaPath = $schemaData['schema_path'] ?? null;
        $cachePath  = $schemaData['schema_cache_path'] ?? null;

        if ( !$schemaPath || !file_exists( $schemaPath ) ) {
            $this->warn( "  ⚠ '{$schemaKey}' — schema file not found: {$schemaPath}" );
            return false;
        }

        if ( !$cachePath ) {
            $this->warn( "  ⚠ '{$schemaKey}' — no cache path configured, skipping." );
            return false;
        }

        try {
            $schemaSourceProvider = new SchemaStitcher( $schemaPath );
            $astCache             = new SchemaSpecificASTCache( $cachePath );

            $astBuilder = new ASTBuilder(
                app( DirectiveLocator::class ),
                $schemaSourceProvider,
                app( Dispatcher::class ),
                $astCache,
            );

            $astCache->set( $astBuilder->build() );

            $this->info( "  ✓ '{$schemaKey}' → {$cachePath}" );

            return true;
        } catch ( \Throwable $e ) {
            $this->error( "  ✗ '{$schemaKey}' failed: {$e->getMessage()}" );
            return false;
        }
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
            'schema_path'       => config( 'lighthouse.schema_path' ),
            'schema_cache_path' => config( 'lighthouse.schema_cache.path' ),
        ];

        return $schemas;
    }
}
