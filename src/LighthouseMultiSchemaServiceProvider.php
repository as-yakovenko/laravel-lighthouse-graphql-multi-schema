<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLRouteRegister;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaASTCache;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\LighthouseClearCacheCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\PublishConfigCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\MultiSchemaIdeHelperCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\MultiSchemaValidateCommand;

class LighthouseMultiSchemaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * This method registers various services and configurations required for
     * the Lighthouse GraphQL multi-schema functionality. It includes registering
     * GraphQLSchemaConfig, GraphQLRouteRegister, SchemaASTCache, SchemaSourceProvider,
     * and configuration files. Additionally, it registers commands for publishing
     * configuration and clearing the cache.
     *
     * @return void
     */
    public function register()
    {
        // Register GraphQLSchemaConfig bind
        $this->app->bind( GraphQLSchemaConfig::class, function () {
            return new GraphQLSchemaConfig();
        });

        // Register GraphQLRouteRegister without dependency injection
        $this->app->singleton( GraphQLRouteRegister::class, function () {
            return new GraphQLRouteRegister();
        });

        // Register SchemaASTCache as bind (not singleton) to make it Octane-compatible
        // This ensures a fresh instance per request with current request context
        $this->app->bind( ASTCache::class, function ( $app ) {
            return new SchemaASTCache(
                $app['config'],
                $app->make( GraphQLSchemaConfig::class )
            );
        });

        // Register SchemaSourceProvider bind using SchemaStitcher
        $this->app->bind( SchemaSourceProvider::class, function ( $app ) {
            return new SchemaStitcher(
                $app->make( GraphQLSchemaConfig::class )->getPath( request() )
            );
        });

        // Merge package configuration with application configuration
        $this->mergeConfigFrom(
            __DIR__ . '/lighthouse-multi-schema.php', 'lighthouse-multi-schema'
        );

        // Register custom commands
        $this->commands( [
            PublishConfigCommand::class,
            MultiSchemaIdeHelperCommand::class,
            MultiSchemaValidateCommand::class
        ] );

        // Force override of lighthouse:clear-cache command
        $this->app->singleton( 'command.lighthouse.clear-cache', function ( $app ) {
            return new LighthouseClearCacheCommand(
                $app['files'],
                $app->make( GraphQLSchemaConfig::class )
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * Publishes the configuration file and registers GraphQL routes.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file to the application's config directory
        $this->publishes([
            __DIR__ . '/lighthouse-multi-schema.php' => config_path('lighthouse-multi-schema.php'),
        ], 'config');

        // Register GraphQL routes by resolving GraphQLRouteRegister from the container
        $this->app->make( GraphQLRouteRegister::class )->registerGraphQLRoutes();

        // Register overridden command
        if ( $this->app->runningInConsole() ) {
            $this->commands( ['command.lighthouse.clear-cache'] );
        }
    }
}
