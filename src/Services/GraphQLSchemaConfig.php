<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Http\Request;

class GraphQLSchemaConfig
{
    /**
     * The multi-schema configurations.
     *
     * @var array<string, array>
     */
    public array $multiSchemas;

    public function __construct()
    {
        $this->multiSchemas = config('lighthouse-multi-schema.multi_schemas', []);
    }

    /**
     * Get the key for the current GraphQL schema.
     *
     * @param Request $request
     * @return string|null
     */
    public function getKey( Request $request ): ?string
    {
        return $this->getSchemaConfig( $request, 'key' );
    }

    /**
     * Get the path to the GraphQL schema file.
     *
     * @param Request $request
     * @return string|null
     */
    public function getPath( Request $request ): ?string
    {
        return $this->getSchemaConfig( $request, 'schema_path' );
    }

    /**
     * Get the path to the GraphQL schema cache file.
     *
     * @param Request $request
     * @return string|null
     */
    public function getCachePath( Request $request ): ?string
    {
        return $this->getSchemaConfig( $request, 'schema_cache_path' );
    }

    /**
     * Check if schema caching is enabled.
     *
     * @param Request $request
     * @return bool
     */
    public function isCacheEnabled( Request $request ): bool
    {
        return (bool) $this->getSchemaConfig( $request, 'schema_cache_enable' );
    }

    /**
     * Universal getter for schema config values by key.
     *
     * @param Request $request
     * @param string $key
     * @return mixed
     */
    protected function getSchemaConfig( Request $request, string $key ): mixed
    {
        $requestPath = $request->getPathInfo();

        foreach ( $this->multiSchemas as $schemaKey => $schemaConfig ) {
            if ( $schemaConfig['route_uri'] === $requestPath ) {
                if ( $key === 'key' ) {
                    return $schemaKey;
                } else {
                    return $schemaConfig[$key] ?? null;
                }
            }
        }

        return match ( $key ) {
            'key'                 => config('lighthouse.route.uri') === $requestPath ? 'default' : null,
            'schema_path'         => config('lighthouse.schema_path'),
            'schema_cache_path'   => config('lighthouse.schema_cache.path'),
            'schema_cache_enable' => config('lighthouse.schema_cache.enable', false),
            default => null,
        };
    }
}
