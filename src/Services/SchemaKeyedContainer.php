<?php

namespace Yakovenko\LighthouseGraphqlMultiSchema\Services;

use Closure;

/**
 * Schema-keyed instance container for Lighthouse services.
 *
 * Stores resolved service instances keyed by schema identifier, allowing
 * singleton-like performance while supporting multiple schemas under
 * Laravel Octane (Swoole/RoadRunner).
 *
 * Each schema version gets its own isolated set of Lighthouse service instances.
 * The first request to a schema builds and caches the services; subsequent
 * requests to the same schema reuse them from memory.
 *
 * This avoids the performance penalty of scoped() bindings (which rebuild
 * every request) while preventing the stale-state bug of plain singletons
 * (which cache only one schema's state across all requests).
 */
class SchemaKeyedContainer
{
    /**
     * Cached instances keyed by [serviceClass][schemaKey].
     *
     * @var array<class-string, array<string, object>>
     */
    protected array $instances = [];

    /**
     * Resolve a service instance for the given schema key.
     *
     * If an instance already exists for this service+schema combination,
     * it is returned immediately (singleton behavior). Otherwise, the
     * factory closure is called to build a new instance, which is then
     * cached for future requests.
     *
     * @param class-string $service  The service class being resolved
     * @param string       $schemaKey The schema identifier (e.g., 'default', 'schema1')
     * @param Closure      $factory   Factory to build the instance if not cached
     *
     * @return object The resolved service instance
     */
    public function resolve(string $service, string $schemaKey, Closure $factory): object
    {
        if (! isset($this->instances[$service][$schemaKey])) {
            $this->instances[$service][$schemaKey] = $factory();
        }

        return $this->instances[$service][$schemaKey];
    }

    /**
     * Flush all cached instances for a specific schema.
     *
     * Useful for cache:clear or schema reload scenarios.
     *
     * @param string $schemaKey The schema identifier to flush
     */
    public function flushSchema(string $schemaKey): void
    {
        foreach ($this->instances as $service => $schemas) {
            unset($this->instances[$service][$schemaKey]);
        }
    }

    /**
     * Flush all cached instances across all schemas.
     *
     * Useful for full cache clear or testing.
     */
    public function flushAll(): void
    {
        $this->instances = [];
    }
}
