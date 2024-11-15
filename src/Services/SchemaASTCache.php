<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class SchemaASTCache extends ASTCache
{
    protected bool $cacheEnable;

    /**
     * Constructor.
     *
     * Initializes the ASTCacheService by setting the cache path and cache enable
     * flag based on the current request URI using the provided graphQLSchemaConfig.
     *
     * @param ConfigRepository $config The configuration repository instance.
     * @param GraphQLSchemaConfig $graphQLSchemaConfig The GraphQL service instance.
     */
    public function __construct( ConfigRepository $config, GraphQLSchemaConfig $graphQLSchemaConfig )
    {
        parent::__construct( $config );

        $this->path        = $graphQLSchemaConfig->getCachePath();
        $this->cacheEnable = $graphQLSchemaConfig->isCacheEnabled();
    }

    /**
     * Check if schema caching is enabled.
     *
     * This method overrides the parent isEnabled method to return the caching status
     * based on the current request URI.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isEnabled(): bool
    {
        return $this->cacheEnable;
    }
}
