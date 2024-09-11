<?php declare(strict_types=1);

/**
 * Configuration for Lighthouse Multi Schema package.
 */
return [
    'multi_schemas' => [
        'example' => [
            'route_uri'           => '/example-graphql',
            'route_name'          => 'example-graphql',
            'schema_path'         => base_path("graphql/example.graphql"),
            'schema_cache_path'   => env('LIGHTHOUSE_EXAMPLE_SCHEMA_CACHE_PATH', base_path("bootstrap/cache/example-schema.php")),
            'schema_cache_enable' => env('LIGHTHOUSE_EXAMPLE_CACHE_ENABLE', false),
            'middleware' => [
                // Always set the `Accept: application/json` header.
                Nuwave\Lighthouse\Http\Middleware\AcceptJson::class,

                // Logs in a user if they are authenticated. In contrast to Laravel's 'auth'
                // middleware, this delegates auth and permission checks to the field level.
                Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class,

                // Schema-specific middleware.
                // Add your custom middleware here for this schema:
                // App\Http\Middleware\ExampleSchemaMiddleware::class,
            ]
        ],
        // Add additional schemas as needed
    ],
];
