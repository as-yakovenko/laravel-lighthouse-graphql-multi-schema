<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Symfony\Component\Console\Input\InputArgument;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLService;

/**
 * Class LighthouseClearCacheCommand
 *
 * This command clears the GraphQL schema cache. If no schema name is provided,
 * it clears all schema caches. If a schema name is provided, it clears the cache
 * for that specific schema only.
 */
class LighthouseClearCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:clear-cache {schema? : The name of the schema to clear the cache for. Use "default" for the default schema.}';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the GraphQL schema cache.';

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     * @param GraphQLService $graphQLService
     */
    public function __construct( protected Filesystem $filesystem, protected GraphQLService $graphQLService )
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * If a schema name is provided as an argument, clear the cache for that schema.
     * Otherwise, clear all schema caches.
     *
     * @param ASTCache $cache
     * @return void
     */
    public function handle( ASTCache $cache ): void
    {
        $schemaName = $this->argument( 'schema' );

        if ( $schemaName ) {

            $this->clearSchemaCache( $schemaName );

        } else {

            $this->clearAllSchemaCaches( $cache );
            $this->info( 'All GraphQL AST schema caches deleted.' );

        }
    }

    /**
     * Clear the cache for a specific schema.
     *
     * @param string $schemaName The name of the schema to clear the cache for.
     * @return void
     */
    protected function clearSchemaCache( string $schemaName ): void
    {
        if ( $schemaName === 'default' ) {

            $cachePath = config( 'lighthouse.schema_cache.path' );

            if ( $this->filesystem->exists( $cachePath ) ) {

                $this->filesystem->delete( $cachePath );
                $this->info("GraphQL AST schema cache for 'default' schema deleted.");

            } else {

                $this->warn("Cache file for 'default' schema not found.");

            }

        } else {

            $multiSchemas = $this->graphQLService->multiSchemas;

            if ( isset( $multiSchemas[$schemaName] ) ) {

                $cachePath = $multiSchemas[$schemaName]['schema_cache_path'] ?? null;

                if ( $cachePath && $this->filesystem->exists( $cachePath ) ) {

                    $this->filesystem->delete( $cachePath );
                    $this->info( "GraphQL AST schema cache for '{$schemaName}' deleted." );

                } else {

                    $this->warn( "Cache file for '{$schemaName}' not found." );

                }

            } else {

                $this->warn( "Schema '{$schemaName}' not configured." );

            }

        }
    }

    /**
     * Clear the cache for all schemas and the default schema.
     *
     * @param ASTCache $cache
     * @return void
     */
    protected function clearAllSchemaCaches( ASTCache $cache ): void
    {
        $multiSchemas = $this->graphQLService->multiSchemas;

        foreach ( $multiSchemas as $schemaConfig ) {

            $cachePath = $schemaConfig['schema_cache_path'] ?? null;

            if ( $cachePath && $this->filesystem->exists( $cachePath) ) {

                $this->filesystem->delete( $cachePath );

            }

        }

        // Clear the default schema cache
        $cache->clear();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['schema', InputArgument::OPTIONAL, 'The name of the schema to clear the cache for. Use "default" for the default schema.'],
        ];
    }
}
