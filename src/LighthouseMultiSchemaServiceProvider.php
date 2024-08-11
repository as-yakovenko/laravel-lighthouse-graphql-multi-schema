<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\ASTCacheService;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLService;

class LighthouseMultiSchemaServiceProvider extends ServiceProvider
{
    protected GraphQLService $graphQLService;

    /**
     * Register services.
     *
     * This method registers various services and configurations required for the 
     * Lighthouse GraphQL multi-schema functionality. It includes registering the 
     * GraphQLService, ASTCacheService, SchemaSourceProvider, and configuration files.
     * Additionally, it registers commands for publishing configuration and clearing cache.
     *
     * @return void
     */
    public function register()
    {
        // Register GraphQLService
        $this->graphQLService = $this->app->make( GraphQLService::class );

        // Retrieve the current request instance
        $request = $this->app->make( Request::class );

        // Register ASTCacheService
        $this->app->singleton( ASTCache::class, function ( $app ) use ( $request ) {
            return new ASTCacheService( $app['config'], $this->graphQLService, $request );
        });

        // Register SchemaSourceProvider
        $this->app->singleton( SchemaSourceProvider::class, function () use ( $request )  {
            $schemaPath = $this->graphQLService->getSchemaPath( $request->getPathInfo() );
            return new SchemaStitcher( $schemaPath );
        });

        // Register configuration
        $this->mergeConfigFrom(
            __DIR__.'/lighthouse-multi-schema.php', 'lighthouse-multi-schema'
        );

        // Register PublishConfigCommand
        $this->commands([
            \Yakovenko\LighthouseGraphqlMultiSchema\Commands\PublishConfigCommand::class,
            \Yakovenko\LighthouseGraphqlMultiSchema\Commands\LighthouseClearCacheCommand::class
        ]);
    }


    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() 
    {
        // Publishing the configuration file
        $this->publishes([
            __DIR__.'/lighthouse-multi-schema.php' => config_path( 'lighthouse-multi-schema.php' ),
        ], 'config');

        // Register GraphQL routes
        $this->graphQLService->registerGraphQLRoutes();
    }
}
