<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class ASTCacheService extends ASTCache
{
    protected bool $cacheEnable;

    /**
     * Constructor.
     *
     * Initializes the ASTCacheService by setting the cache path and cache enable
     * flag based on the current request URI using the provided GraphQLService.
     *
     * @param ConfigRepository $config The configuration repository instance.
     * @param GraphQLService $graphQLService The GraphQL service instance.
     * @param Request $request The incoming HTTP request instance.
     */
    public function __construct( ConfigRepository $config, GraphQLService $graphQLService, Request $request )
    {
        parent::__construct( $config );

        $requestUri        = $request->getPathInfo();
        $this->path        = $graphQLService->getSchemaCache( $requestUri );
        $this->cacheEnable = $graphQLService->isCacheEnabled( $requestUri );
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
