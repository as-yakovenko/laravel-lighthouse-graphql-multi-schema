<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Container\Container;
use Nuwave\Lighthouse\Http\GraphQLController;

class GraphQLService
{
    /**
     * The multi-schema configurations.
     *
     * This holds the configuration for multiple GraphQL schemas as defined
     * in the 'lighthouse-multi-schemas' configuration file.
     *
     * @var array
     */
    public array $multiSchemas;

    /**
     * GraphQLService constructor.
     *
     * Initialize the multiSchemas property by fetching the configuration from the 'lighthouse-multi-schemas' setting.
     */
    public function __construct()
    {
        $this->multiSchemas = config( 'lighthouse-multi-schema.multi_schemas', [] );
    }

    /**
     * Get the schema path based on the given request URI.
     *
     * @param string $requestUri The request URI to check.
     * @return string The path to the schema file.
     */
    public function getSchemaPath( string $requestUri ): string
    {
        foreach ( $this->multiSchemas as $schemaConfig ) {
            if ( $requestUri === $schemaConfig['route_uri'] && isset( $schemaConfig['schema_path'] ) ) {
                return $schemaConfig['schema_path'];
            }
        }

        return config( 'lighthouse.schema_path' );
    }

    /**
     * Register GraphQL routes for all configured multi-schemas.
     *
     * @return void
     */
    public function registerGraphQLRoutes(): void
    {
        $container = Container::getInstance();
        $router = $container->make('router');

        foreach ( $this->multiSchemas as $schemaConfig ) {
            $action = [
                'as' => $schemaConfig['route_name'],
                'uses' => GraphQLController::class,
            ];

            if (isset($schemaConfig['middleware'])) {
                $action['middleware'] = $schemaConfig['middleware'];
            }

            $router->addRoute(
                ['GET', 'POST'],
                $schemaConfig['route_uri'],
                $action,
            );
        }
    }

    /**
     * Get the schema cache path based on the given request URI.
     *
     * @param string $requestUri The request URI to check.
     * @return string The path to the schema cache file.
     */
    public function getSchemaCache( string $requestUri ): string
    {
        foreach ( $this->multiSchemas as $schemaConfig ) {
            if ( $requestUri === $schemaConfig['route_uri'] ) {
                return $schemaConfig['schema_cache_path'] ?? config( 'lighthouse.schema_cache.path' );
            }
        }

        return config( 'lighthouse.schema_cache.path' );
    }

    /**
     * Check if schema caching is enabled for the given request URI.
     *
     * @param string $requestUri The request URI to check.
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isCacheEnabled( string $requestUri ): bool
    {
        foreach ( $this->multiSchemas as $schemaConfig ) {
            if ( $requestUri === $schemaConfig['route_uri'] ) {
                return $schemaConfig['schema_cache_enable'] ?? false;
            }
        }

        return config( 'lighthouse.schema_cache.enable', false );
    }
}
