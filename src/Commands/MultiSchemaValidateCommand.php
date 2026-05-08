<?php declare(strict_types=1);

namespace Yakovenko\LighthouseGraphqlMultiSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Yakovenko\LighthouseGraphqlMultiSchema\Services\GraphQLSchemaConfig;

class MultiSchemaValidateCommand extends Command
{
    protected $aliases = ['lighthouse:multi-validate-schema'];

    protected $description = 'Validate GraphQL schemas.';

    protected $signature = 'lighthouse:multi-validate
        {schema? : Schema name to validate. If omitted, all schemas will be validated.}
        {--schema= : [deprecated] Use positional argument instead: lighthouse:multi-validate admin}';

    public function handle(): int
    {
        $schemas = $this->resolveSchemas();

        $selectedSchema = $this->resolveSchemaArgument();

        if ( $selectedSchema ) {
            if ( !isset( $schemas[$selectedSchema] ) ) {
                $this->error( "Schema '{$selectedSchema}' not found." );
                $this->info( 'Available schemas: ' . implode( ', ', array_keys( $schemas ) ) );
                return 1;
            }

            $this->info( "Validating schema: {$selectedSchema}" );
            $failed = !$this->validateSchema( $selectedSchema, $schemas[$selectedSchema] );
        } else {
            $failed = false;
            foreach ( $schemas as $key => $schemaData ) {
                $this->info( "Validating schema: {$key}" );
                if ( !$this->validateSchema( $key, $schemaData ) ) {
                    $failed = true;
                }
            }
        }

        if ( !$failed ) {
            $this->info( "\nAll selected schemas are valid." );
        }

        return $failed ? 1 : 0;
    }

    /**
     * Validate a specific schema.
     *
     * @param string $schemaKey The name of the schema to validate.
     * @param array $schemaData The schema data.
     * @return bool True if the schema was validated successfully, false otherwise.
     */
    protected function validateSchema( string $schemaKey, array $schemaData ): bool
    {
        $schemaPath = $schemaData['schema_path'];

        if ( !file_exists( $schemaPath ) ) {
            $this->warn( "  ⚠ '{$schemaKey}' — schema file not found: {$schemaPath}" );
            return false;
        }

        // Save services that will be temporarily rebound
        $saved = [
            ASTCache::class      => app( ASTCache::class ),
            TypeRegistry::class  => app( TypeRegistry::class ),
            ASTBuilder::class    => app( ASTBuilder::class ),
            SchemaBuilder::class => app( SchemaBuilder::class ),
        ];

        try {
            // Noop ASTCache: Validator calls $cache->clear() internally — override it to a
            // no-op so we never touch real cache files on disk during validation.
            $noopCache = $this->makeNoopAstCache();

            // Fresh TypeRegistry per schema: prevents programmatic types (SortOrder, etc.)
            // registered by directive manipulators from one schema leaking into the next.
            $typeRegistry = app()->build( TypeRegistry::class );

            // Fresh ASTBuilder: resets $documentAST memoization so each schema is built
            // from scratch. DirectiveLocator comes from the container — it holds directive
            // class registrations set up by service providers (needed for manipulators).
            $astBuilder = new ASTBuilder(
                app( DirectiveLocator::class ),
                new SchemaStitcher( $schemaPath ),
                app( Dispatcher::class ),
                $noopCache,
            );

            // Fresh SchemaBuilder: resets $schema memoization. Validator mutates
            // $schema->getConfig()->directives[] to add directive definitions before
            // assertValid() — a fresh instance ensures no leftover directives from prior schemas.
            $schemaBuilder = new SchemaBuilder( $typeRegistry, $astBuilder );

            // Rebind in container so Validator resolves fresh instances via app()->make()
            app()->instance( ASTCache::class, $noopCache );
            app()->instance( TypeRegistry::class, $typeRegistry );
            app()->instance( ASTBuilder::class, $astBuilder );
            app()->instance( SchemaBuilder::class, $schemaBuilder );

            // Build a fresh Validator using rebound container dependencies
            app()->build( \Nuwave\Lighthouse\Schema\Validator::class )->validate();

            $this->info( "  ✓ '{$schemaKey}' is valid." );

            return true;
        } catch ( \Throwable $e ) {
            $this->error( "  ✗ '{$schemaKey}' is invalid:\n" . $e->getMessage() );
            return false;
        } finally {
            foreach ( $saved as $abstract => $instance ) {
                app()->instance( $abstract, $instance );
            }
        }
    }

    /**
     * ASTCache that never reads, writes, or deletes any file.
     * Validator calls clear() and set() internally — override both to no-ops so
     * validate never touches real cache files on disk.
     *
     * @return ASTCache
     */
    protected function makeNoopAstCache(): ASTCache
    {
        return new class( app( 'config' ), app( Filesystem::class ) ) extends ASTCache {
            public function isEnabled(): bool { return false; }
            public function clear(): void {}
            public function set( \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST ): void {}
        };
    }

    /**
     * Resolve the schema argument.
     *
     * @return string|null The schema argument.
     */
    protected function resolveSchemaArgument(): ?string
    {
        $arg    = $this->argument( 'schema' );
        $option = $this->option( 'schema' );

        if ( $option && !$arg ) {
            $this->warn( 'The --schema= option is deprecated. Use positional argument: lighthouse:multi-validate admin' );
        }

        return $arg ?? $option ?? null;
    }

    /**
     * Resolve the schemas.
     *
     * @return array<string, array<string, string>> The schemas.
     */
    protected function resolveSchemas(): array
    {
        $schemas = app( GraphQLSchemaConfig::class )->multiSchemas;

        $schemas['default'] = [
            'schema_path' => config( 'lighthouse.schema_path' ),
            'route_uri'   => config( 'lighthouse.route.uri' ),
        ];

        return $schemas;
    }
}
