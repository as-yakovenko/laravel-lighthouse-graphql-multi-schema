<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Http\Request;

class GraphQLSchemaConfig
{
    protected Request $request;

    /**
     * The multi-schema configurations.
     *
     * This property holds the configuration for multiple GraphQL schemas, 
     * as defined in the 'lighthouse-multi-schemas' configuration file.
     *
     * @var array<string, array> Array of schema configurations keyed by schema name.
     */
    public array $multiSchemas;

    /**
     * The key of the current schema determined from the request path.
     *
     * @var string|null
     */
    protected ?string $key = null;

    /**
     * The path to the schema file for the current schema.
     *
     * @var string|null
     */
    protected ?string $path = null;

    /**
     * The path to the cache file for the current schema.
     *
     * @var string|null
     */
    protected ?string $cachePath = null;

    /**
     * Whether caching is enabled for the current schema.
     *
     * @var bool|null
     */
    protected ?bool $cacheEnabled = null;

    /**
     * GraphQLSchemaConfig constructor.
     *
     * Initializes the multiSchemas property by fetching the configuration 
     * from the 'lighthouse-multi-schemas' setting and sets the current schema context
     * based on the incoming request.
     */
    public function __construct()
    {
        $this->request      = request();
        $this->multiSchemas = config('lighthouse-multi-schema.multi_schemas', []);
        $this->initializeCurrentSchema();
    }

    /**
     * Initialize the current schema based on the request path.
     *
     * Determines the schema key, schema file path, cache file path,
     * and whether caching is enabled, based on the incoming request.
     *
     * @return void
     */
    protected function initializeCurrentSchema(): void
    {
        $requestPath = $this->request->getPathInfo();

        foreach ( $this->multiSchemas as $schemaKey => $schemaConfig ) {
            if ( $schemaConfig['route_uri'] === $requestPath ) {
                $this->key          = $schemaKey;
                $this->path         = $schemaConfig['schema_path'] ?? null;
                $this->cachePath    = $schemaConfig['schema_cache_path'] ?? null;
                $this->cacheEnabled = $schemaConfig['schema_cache_enable'] ?? false;
                return;
            }
        }

        $this->key          = config('lighthouse.route.uri') === $requestPath ? 'default' : null;
        $this->path         = config('lighthouse.schema_path');
        $this->cachePath    = config('lighthouse.schema_cache.path');
        $this->cacheEnabled = config('lighthouse.schema_cache.enable', false);
    }

    /**
     * Get the key for the current GraphQL schema.
     *
     * @return string|null The key for the current schema, or null if no match is found.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get the path to the GraphQL schema file.
     *
     * @return ?string The schema file path for the current schema or null if not set.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Get the path to the GraphQL schema cache file.
     *
     * @return ?string The cache file path for the current schema or null if not set.
     */
    public function getCachePath(): ?string
    {
        return $this->cachePath;
    }

    /**
     * Check if schema caching is enabled.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }
}
