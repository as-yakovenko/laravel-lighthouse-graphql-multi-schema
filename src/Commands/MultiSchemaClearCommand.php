<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaKeyedContainer;

class MultiSchemaClearCommand extends Command
{
    protected $signature = 'lighthouse:multi-clear
        {schema? : Schema name to clear. If omitted, all schema caches will be cleared.}';

    protected $description = 'Clear the cached GraphQL schemas.';

    public function __construct(
        protected Filesystem $filesystem,
        protected GraphQLSchemaConfig $graphQLSchemaConfig,
    ) {
        parent::__construct();
    }

    public function handle( ASTCache $cache ): void
    {
        $schemaName      = $this->argument( 'schema' );
        $keyedContainer  = app( SchemaKeyedContainer::class );

        if ( $schemaName ) {
            $this->clearOne( $schemaName, $keyedContainer );
        } else {
            $this->clearAll( $cache, $keyedContainer );
        }
    }

    /**
     * Clear a specific schema.
     *
     * @param string $schemaName The name of the schema to clear.
     * @param SchemaKeyedContainer $keyedContainer The keyed container.
     */
    protected function clearOne( string $schemaName, SchemaKeyedContainer $keyedContainer ): void
    {
        if ( $schemaName === 'default' ) {
            app( ASTCache::class )->clear();
            $keyedContainer->flushSchema( 'default' );
            $this->info( "Default GraphQL schema cache removed." );
            return;
        }

        $multiSchemas = $this->graphQLSchemaConfig->multiSchemas;

        if ( !isset( $multiSchemas[$schemaName] ) ) {
            $this->warn( "Schema '{$schemaName}' not configured." );
            return;
        }

        $cachePath = $multiSchemas[$schemaName]['schema_cache_path'] ?? null;

        if ( $cachePath && $this->filesystem->exists( $cachePath ) ) {
            $this->filesystem->delete( $cachePath );
            $this->info( "  ✓ '{$schemaName}' cache cleared." );
        } else {
            $this->warn( "  ⚠ '{$schemaName}' — cache file not found." );
        }

        $keyedContainer->flushSchema( $schemaName );
    }

    /**
     * Clear all schemas.
     *
     * @param ASTCache $cache The AST cache.
     * @param SchemaKeyedContainer $keyedContainer The keyed container.
     */
    protected function clearAll( ASTCache $cache, SchemaKeyedContainer $keyedContainer ): void
    {
        foreach ( $this->graphQLSchemaConfig->multiSchemas as $schemaName => $schemaConfig ) {
            $cachePath = $schemaConfig['schema_cache_path'] ?? null;

            if ( $cachePath && $this->filesystem->exists( $cachePath ) ) {
                $this->filesystem->delete( $cachePath );
                $this->info( "  ✓ '{$schemaName}' cache cleared." );
            } else {
                $this->warn( "  ⚠ '{$schemaName}' — cache file not found, skipping." );
            }
        }

        $cache->clear();
        $this->info( "  ✓ 'default' cache cleared." );

        $keyedContainer->flushAll();

        $this->info( "\nAll schema caches cleared." );
    }
}
