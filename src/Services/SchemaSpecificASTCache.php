<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

/**
 * ASTCache bound to a fixed schema cache path.
 * Used by MultiSchemaCacheCommand to write each schema's cache to its own file.
 */
class SchemaSpecificASTCache extends ASTCache
{
    public function __construct( private readonly string $cachePath )
    {
        parent::__construct(
            Container::getInstance()->make( 'config' ),
            Container::getInstance()->make( Filesystem::class ),
        );
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function path(): string
    {
        return $this->cachePath;
    }
}
