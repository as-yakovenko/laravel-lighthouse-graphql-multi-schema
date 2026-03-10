<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\LighthouseClearCacheCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\PublishConfigCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLRouteRegister;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaASTCache;

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
        // Register GraphQLSchemaConfig as scoped (one per request, flushed between Octane requests)
        $this->app->scoped( GraphQLSchemaConfig::class, function () {
            return new GraphQLSchemaConfig();
        });

        // Register GraphQLRouteRegister without dependency injection
        $this->app->singleton( GraphQLRouteRegister::class, function () {
            return new GraphQLRouteRegister();
        });

        // Register SchemaASTCache as scoped (one per request, flushed between Octane requests)
        $this->app->scoped( ASTCache::class, function ( $app ) {
            return new SchemaASTCache(
                $app['config'],
                $app->make( GraphQLSchemaConfig::class )
            );
        });

        // Register SchemaSourceProvider as scoped (one per request, flushed between Octane requests)
        $this->app->scoped( SchemaSourceProvider::class, function ( $app ) {
            return new SchemaStitcher(
                $app->make( GraphQLSchemaConfig::class )->getPath( request() )
            );
        });

        // Merge package configuration with application configuration
        $this->mergeConfigFrom(
            __DIR__ . '/lighthouse-multi-schema.php', 'lighthouse-multi-schema'
        );

        // Register custom commands
        $this->commands( [PublishConfigCommand::class] );

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

        // Override Lighthouse's singletons with scoped bindings for Octane/Swoole compatibility.
        // Lighthouse registers GraphQL, SchemaBuilder, ASTBuilder, DirectiveLocator, and TypeRegistry
        // as singletons which cache schema state. Under Octane, these persist across requests,
        // breaking multi-schema switching. Scoped bindings are flushed between requests automatically.
        $this->app->scoped( GraphQL::class );
        $this->app->scoped( SchemaBuilder::class );
        $this->app->scoped( ASTBuilder::class );
        $this->app->scoped( DirectiveLocator::class );
        $this->app->scoped( TypeRegistry::class );

        // Register GraphQL routes by resolving GraphQLRouteRegister from the container
        $this->app->make( GraphQLRouteRegister::class )->registerGraphQLRoutes();

        // Register overridden command
        if ( $this->app->runningInConsole() ) {
            $this->commands( ['command.lighthouse.clear-cache'] );
        }
    }
}
