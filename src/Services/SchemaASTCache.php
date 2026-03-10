<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class SchemaASTCache extends ASTCache
{
    protected GraphQLSchemaConfig $graphQLSchemaConfig;

    /**
     */
    public function __construct(ConfigRepository $config, GraphQLSchemaConfig $graphQLSchemaConfig)
    {
        parent::__construct(
            $config,
            Container::getInstance()->make(Filesystem::class)
        );
        $this->graphQLSchemaConfig = $graphQLSchemaConfig;
    }

    /**
     * Get the current request dynamically from the container.
     * This ensures we always get the current request in Octane environments.
     */
    protected function getCurrentRequest(): Request
    {
        return request();
    }

    /**
     * Check if the cache is enabled for the current request.
     */
    public function isEnabled(): bool
    {
        return $this->graphQLSchemaConfig->isCacheEnabled($this->getCurrentRequest()) ?? false;
    }

    /**
     * Get the current cache path for the request.
     */
    public function path(): string
    {
        return $this->graphQLSchemaConfig->getCachePath($this->getCurrentRequest()) ?? $this->path;
    }
}
