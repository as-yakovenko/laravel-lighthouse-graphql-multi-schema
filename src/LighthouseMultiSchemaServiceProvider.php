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
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\MultiSchemaIdeHelperCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\MultiSchemaValidateCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Commands\PublishConfigCommand;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLRouteRegister;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaASTCache;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\SchemaKeyedContainer;

class LighthouseMultiSchemaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // GraphQLSchemaConfig is stateless (reads config + request path), safe as singleton
        $this->app->singleton(GraphQLSchemaConfig::class, function () {
            return new GraphQLSchemaConfig();
        });

        // Register GraphQLRouteRegister without dependency injection
        $this->app->singleton(GraphQLRouteRegister::class, function () {
            return new GraphQLRouteRegister();
        });

        // SchemaKeyedContainer persists across ALL requests in the Octane worker.
        // It caches built service instances keyed by schema identifier so that
        // each schema's services are constructed exactly once per worker lifetime.
        $this->app->singleton(SchemaKeyedContainer::class, function () {
            return new SchemaKeyedContainer();
        });

        // ASTCache — scoped() so the closure runs once per request (not on every make() call).
        // SchemaKeyedContainer returns the cached instance, so the expensive construction
        // only happens on the very first request for each schema.
        $this->app->scoped(ASTCache::class, function ($app) {
            return $app->make(SchemaKeyedContainer::class)->resolve(
                ASTCache::class,
                $this->resolveSchemaKey(),
                fn () => new SchemaASTCache(
                    $app['config'],
                    $app->make(GraphQLSchemaConfig::class)
                )
            );
        });

        // SchemaSourceProvider — same pattern: scoped() + keyed cache
        $this->app->scoped(SchemaSourceProvider::class, function ($app) {
            return $app->make(SchemaKeyedContainer::class)->resolve(
                SchemaSourceProvider::class,
                $this->resolveSchemaKey(),
                fn () => new SchemaStitcher(
                    $app->make(GraphQLSchemaConfig::class)->getPath(request())
                )
            );
        });

        // Merge package configuration with application configuration
        $this->mergeConfigFrom(
            __DIR__ . '/lighthouse-multi-schema.php',
            'lighthouse-multi-schema'
        );

        // Register custom commands
        $this->commands([
            PublishConfigCommand::class,
            MultiSchemaIdeHelperCommand::class,
            MultiSchemaValidateCommand::class,
        ]);

        // Force override of lighthouse:clear-cache command
        $this->app->singleton('command.lighthouse.clear-cache', function ($app) {
            return new LighthouseClearCacheCommand(
                $app['files'],
                $app->make(GraphQLSchemaConfig::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file to the application's config directory
        $this->publishes([
            __DIR__ . '/lighthouse-multi-schema.php' => config_path('lighthouse-multi-schema.php'),
        ], 'config');

        // Override Lighthouse's singletons with scoped() + SchemaKeyedContainer.
        //
        // ## Why plain singleton() breaks multi-schema under Octane
        //
        // Lighthouse registers these as singletons. Under Octane/Swoole, singletons
        // persist across requests, caching the first request's schema state forever.
        //
        // ## Why pure scoped() was slow
        //
        // scoped() rebuilds ALL services from scratch every request (schema parsing,
        // AST building, directive registration, type resolution). This negates
        // Swoole's persistent-worker advantage entirely.
        //
        // ## Why bind() was also slow
        //
        // bind() runs the closure on EVERY $app->make() call, not just once per
        // request. These services are resolved many times through DI chains within
        // a single request, multiplying the overhead.
        //
        // ## Current approach: scoped() + SchemaKeyedContainer
        //
        // - scoped() ensures the closure runs exactly ONCE per request per service
        //   (subsequent make() calls in the same request return the cached result)
        // - SchemaKeyedContainer (a true singleton) caches built instances keyed by
        //   schema, so $app->build() runs exactly ONCE per schema per worker lifetime
        //
        // Per-request cost: 1 closure call × 5 services = 5 hash lookups (negligible)
        // Memory cost: N sets of services where N = number of schemas (typically 2-3)
        $schemaKeyedServices = [
            GraphQL::class,
            SchemaBuilder::class,
            ASTBuilder::class,
            DirectiveLocator::class,
            TypeRegistry::class,
        ];

        foreach ($schemaKeyedServices as $service) {
            $this->app->scoped($service, function ($app) use ($service) {
                return $app->make(SchemaKeyedContainer::class)->resolve(
                    $service,
                    $this->resolveSchemaKey(),
                    fn () => $app->build($service)
                );
            });
        }

        // Register GraphQL routes by resolving GraphQLRouteRegister from the container
        $this->app->make(GraphQLRouteRegister::class)->registerGraphQLRoutes();

        // Register overridden command
        if ($this->app->runningInConsole()) {
            $this->commands(['command.lighthouse.clear-cache']);
        }
    }

    /**
     * Resolve the current schema key from the request path.
     *
     * Falls back to 'default' when running in console or when no schema matches.
     */
    protected function resolveSchemaKey(): string
    {
        if ($this->app->runningInConsole()) {
            return 'default';
        }

        try {
            return $this->app->make(GraphQLSchemaConfig::class)->getKey(request()) ?? 'default';
        } catch (\Throwable) {
            return 'default';
        }
    }
}
