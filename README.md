# Lighthouse GraphQL Multi-Schema

`yakovenko/laravel-lighthouse-graphql-multi-schema` is a Laravel package that provides multi-schema support for Lighthouse GraphQL. It allows you to manage multiple GraphQL schemas within a single Laravel application, streamlining development and extending functionality.

## Installation

### Requirements

- PHP               : ^8
- Laravel           : ^9.0 || ^10.0 || ^11.0
- Nuwave Lighthouse : ^6.0

### Install the Package

You can install the package using Composer:

```bash
composer require yakovenko/laravel-lighthouse-graphql-multi-schema
```

**Publish Configuration**

After installing the package, you need to publish the configuration file by running the following command:

```bash
php artisan lighthouse-multi-schema:publish-config
```

This will create a configuration file named lighthouse-multi-schema.php in the config/ directory, where you can set up your GraphQL schemas.

**Configuration**

In the config/lighthouse-multi-schema.php file, you can define your schemas and their settings. Here’s an example configuration:

```php
return [
    'multi_schemas' => [
        'schema1' => [
            'route_uri'           => '/schema1-graphql',
            'route_name'          => 'schema1-graphql',
            'schema_path'         => base_path("graphql/schema1.graphql"),
            'schema_cache_path'   => env('LIGHTHOUSE_EXAMPLE_SCHEMA_CACHE_PATH', base_path("bootstrap/cache/schema1-schema.php")),
            'schema_cache_enable' => env('LIGHTHOUSE_EXAMPLE_CACHE_ENABLE', false),
        ],
        // Add additional schemas as needed
    ],
];
```

**CSRF exceptions**

Add your GraphQL routes to the CSRF exceptions.

Open the App/Http/Middleware/VerifyCsrfToken.php file and add your routes to the $except array.

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'schema1-graphql',
        'schema2-graphql',
        // Add other routes as needed
    ];
}
```

**Registration provider**

You need to add your service provider to the providers array in your Laravel application's ```config/app.php``` file:

```php
'providers' => [
    // Other providers...
    Yakovenko\LighthouseGraphqlMultiSchema\LighthouseMultiSchemaServiceProvider::class,
],
```

**Create Directories for Each Schema**

Organize your schema files into separate directories for each schema. The structure of these directories and how you split the files is up to you. Here’s an example of one way to organize them:
```
/graphql
├── schema.graphql
├── schema1.graphql
├── schema2.graphql
└── schema3.graphql
```

### Console Command Example

The lighthouse:clear-cache command is used to manage the cache for your GraphQL schemas. Below are the available usages:

**1 - Clear All Schema Caches**

To clear the cache for all GraphQL schemas, run the following command:

```
php artisan lighthouse:clear-cache
```

This command will delete all cached schema files, ensuring that any changes made to the schemas are reflected the next time they are accessed.

**2 - Clear Cache for a Specific Schema**

You can also clear the cache for any other schema by replacing {keyYourSchema} with the desired schema name:

```
php artisan lighthouse:clear-cache {keyYourSchema}
```

example:
```
php artisan lighthouse:clear-cache schema1
```

Replace {keyYourSchema} with the actual name of the schema you want to target. This will specifically remove the cache for that schema only.

### Endpoint Schemas

Schema 1: Access the GraphQL schema at:
```domain.local/schema1-graphql```

Schema 2: Access the GraphQL schema at:
```domain.local/schema2-graphql```

Schema 3: Access the GraphQL schema at:
```domain.local/schema3-graphql```

### Usage

Once configured, you can use the defined routes for each schema in your application. Each route will utilize its corresponding GraphQL schema. You have a multi-schema setup that allows for an unlimited number of access points, each supporting various mutations and queries tailored to your specific needs. You can define each schema according to your project requirements.
This flexibility allows you to create distinct schemas for different parts of your application, ensuring that each area can have customized queries and mutations as needed.