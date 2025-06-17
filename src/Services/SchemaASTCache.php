<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Illuminate\Http\Request;

class SchemaASTCache extends ASTCache
{
    protected GraphQLSchemaConfig $graphQLSchemaConfig;

    /**
     * @param ConfigRepository $config
     * @param GraphQLSchemaConfig $graphQLSchemaConfig
     */
    public function __construct( ConfigRepository $config, GraphQLSchemaConfig $graphQLSchemaConfig )
    {
        parent::__construct( $config );
        $this->graphQLSchemaConfig = $graphQLSchemaConfig;
    }

    /**
     * Get the current request dynamically from the container.
     * This ensures we always get the current request in Octane environments.
     *
     * @return Request
     */
    protected function getCurrentRequest(): Request
    {
        return request();
    }

    /**
     * Check if the cache is enabled for the current request.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->graphQLSchemaConfig->isCacheEnabled( $this->getCurrentRequest() ) ?? false;
    }

    /**
     * Get the current cache path for the request.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->graphQLSchemaConfig->getCachePath( $this->getCurrentRequest() ) ?? $this->path;
    }

}
