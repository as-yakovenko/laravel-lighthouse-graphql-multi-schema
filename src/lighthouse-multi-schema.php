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
        ],
        // Add additional schemas as needed
    ],
];
