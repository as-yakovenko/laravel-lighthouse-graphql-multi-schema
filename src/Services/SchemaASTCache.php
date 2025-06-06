<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidSchemaCacheContentsException;
use Illuminate\Container\Container;

class SchemaASTCache extends ASTCache
{
    protected GraphQLSchemaConfig $graphQLSchemaConfig;
    protected Request $request;

    /**
     * @param ConfigRepository $config
     * @param GraphQLSchemaConfig $graphQLSchemaConfig
     * @param Request $request
     */
    public function __construct( ConfigRepository $config, GraphQLSchemaConfig $graphQLSchemaConfig, Request $request )
    {
        parent::__construct( $config );
        $this->graphQLSchemaConfig = $graphQLSchemaConfig;
        $this->request = $request;
    }

    /**
     * Get the current cache path for the request.
     *
     * @return string
     */
    protected function getCurrentCachePath(): string
    {
        return $this->graphQLSchemaConfig->getCachePath( $this->request ) ?? $this->path;
    }

    /**
     * Check if the cache is enabled for the current request.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->graphQLSchemaConfig->isCacheEnabled( $this->request ) ?? false;
    }

    /**
     * Set the document AST in the cache.
     *
     * @param DocumentAST $documentAST
     * @return void
     */
    public function set( DocumentAST $documentAST ): void
    {
        $variable = var_export( $documentAST->toArray(), true );
        $this->filesystem()->put(
            $this->getCurrentCachePath(),
            /** @lang PHP */ "<?php return {$variable};"
        );
    }

    /** @param  callable(): DocumentAST  $build */
    public function fromCacheOrBuild( callable $build ): DocumentAST
    {
        $path = $this->getCurrentCachePath();

        if ( $this->filesystem()->exists( $path ) ) {
            $ast = require $path;
            if ( !is_array( $ast ) ) {
                throw new InvalidSchemaCacheContentsException( $path, $ast );
            }

            return DocumentAST::fromArray( $ast );
        }

        $documentAST = $build();
        $this->set( $documentAST );

        return $documentAST;
    }

    /**
     * Clear the cache for the current request.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->filesystem()->delete( $this->getCurrentCachePath() );
    }

    /**
     * Get the filesystem instance.
     *
     * @return Filesystem
     */
    protected function filesystem(): Filesystem
    {
        return Container::getInstance()->make( Filesystem::class );
    }

}
