# Lighthouse GraphQL Multi-Schema

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yakovenko/laravel-lighthouse-graphql-multi-schema.svg?style=flat-square)](https://packagist.org/packages/yakovenko/laravel-lighthouse-graphql-multi-schema)
[![Total Downloads](https://img.shields.io/packagist/dt/yakovenko/laravel-lighthouse-graphql-multi-schema.svg?style=flat-square)](https://packagist.org/packages/yakovenko/laravel-lighthouse-graphql-multi-schema)
[![PHP Version](https://img.shields.io/packagist/php-v/yakovenko/laravel-lighthouse-graphql-multi-schema.svg?style=flat-square)](https://packagist.org/packages/yakovenko/laravel-lighthouse-graphql-multi-schema)
[![License](https://img.shields.io/packagist/l/yakovenko/laravel-lighthouse-graphql-multi-schema.svg?style=flat-square)](https://opensource.org/licenses/MIT)

Run multiple independent GraphQL schemas in a single Laravel application. Each schema gets its own route, middleware, cache, and developer tooling - with full support for Laravel Octane.

**Typical use cases:** admin vs public API, API versioning (v1/v2/v3), domain-driven or modular architectures.

---

## 🚀 Key Features

- **Schema Isolation** - separate endpoints for Admin, Public, Mobile, or any other API surface
- **Independent Caching** - each schema has its own cache file, critical for production performance
- **Per-schema Middleware** - apply auth or any custom middleware independently per schema
- **Developer Tooling** - dedicated IDE helpers and validation commands for each schema
- **Octane Ready** - full support for Laravel Octane, Swoole, RoadRunner, and FrankenPHP

---

## 📦 Requirements

| Dependency | Version |
|---|---|
| PHP | `^8` |
| Laravel | `^9.0` \| `^10.0` \| `^11.0` \| `^12.0` \| `^13.0` |
| Nuwave Lighthouse | `^6.0` |

---

## ⚙️ Installation

**1. Install via Composer**

```bash
composer require yakovenko/laravel-lighthouse-graphql-multi-schema
```

**2. Publish the configuration**

```bash
php artisan lighthouse-multi-schema:publish-config
```

This creates `config/lighthouse-multi-schema.php` where you define your schemas.

---

## 🔧 Configuration

Each schema entry requires a route URI, a schema file path, and optionally cache settings and middleware.

```php
// config/lighthouse-multi-schema.php

return [
    'multi_schemas' => [

        'admin' => [
            'route_uri'           => '/admin-graphql',
            'route_name'          => 'admin-graphql',
            'schema_path'         => base_path('graphql/admin.graphql'),
            'schema_cache_path'   => base_path('bootstrap/cache/admin-schema.php'),
            'schema_cache_enable' => env('LIGHTHOUSE_ADMIN_CACHE_ENABLE', false),
            'middleware' => [
                \Nuwave\Lighthouse\Http\Middleware\AcceptJson::class,
                \Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class,
                'auth:sanctum',
            ],
        ],

        'public' => [
            'route_uri'           => '/graphql',
            'route_name'          => 'public-graphql',
            'schema_path'         => base_path('graphql/schema.graphql'),
            'schema_cache_path'   => base_path('bootstrap/cache/public-schema.php'),
            'schema_cache_enable' => env('LIGHTHOUSE_PUBLIC_CACHE_ENABLE', false),
            'middleware' => [
                \Nuwave\Lighthouse\Http\Middleware\AcceptJson::class,
                \Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class,
            ],
        ],

    ],
];
```

> The `default` Lighthouse schema (configured via `lighthouse.schema_path`) continues to work as before - this package only extends it.

---

## 🛡️ CSRF Protection

GraphQL routes use `POST` and should be excluded from CSRF verification.

**Laravel 11+ - `bootstrap/app.php`**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        '/admin-graphql',
        '/graphql',
    ]);
})
```

**Laravel 9 / 10 - `app/Http/Middleware/VerifyCsrfToken.php`**

```php
protected $except = [
    '/admin-graphql',
    '/graphql',
];
```

---

## 💻 Artisan Commands

### Quick Reference

| Command | Description |
|---|---|
| `lighthouse:multi-cache [schema]` | Compile and cache schemas |
| `lighthouse:multi-clear [schema]` | Clear cached schemas |
| `lighthouse:multi-validate [schema]` | Validate schemas |
| `lighthouse:multi-ide-helper [schema]` | Generate IDE helper files |

All commands operate on **all schemas** when called without an argument, or on a **specific schema** when a name is provided.

---

### Cache Management

Warm the schema cache before deployment to avoid cold-start latency in production.

```bash
# Cache all schemas
php artisan lighthouse:multi-cache

# Cache a specific schema
php artisan lighthouse:multi-cache admin

# Clear all caches
php artisan lighthouse:multi-clear

# Clear a specific schema cache
php artisan lighthouse:multi-clear admin
```

### Schema Validation

Catch syntax, type, and directive errors before they reach production.

```bash
# Validate all schemas
php artisan lighthouse:multi-validate

# Validate a specific schema
php artisan lighthouse:multi-validate admin
```

### IDE Helper Generation

Generate per-schema autocompletion files for your IDE.

```bash
# Generate for all schemas
php artisan lighthouse:multi-ide-helper

# Generate for a specific schema
php artisan lighthouse:multi-ide-helper admin
```

Generated files per schema:
- `schema-directives-{schema}.graphql`
- `programmatic-types-{schema}.graphql`
- `_lighthouse_ide_helper.php` (default schema only)

Add these to `.gitignore`:
```gitignore
schema-directives*.graphql
programmatic-types*.graphql
_lighthouse_ide_helper.php
```

### CI/CD Integration

```yaml
# .github/workflows/deploy.yml
- name: Validate GraphQL schemas
  run: php artisan lighthouse:multi-validate

- name: Warm schema cache
  run: php artisan lighthouse:multi-cache
```

---

## ⚡ Octane / Long-lived Workers

The package is designed for use with **Laravel Octane** (Swoole, RoadRunner, FrankenPHP). Schema services are cached per-schema per-worker using `SchemaKeyedContainer`, so each schema is built exactly once per worker lifetime - eliminating redundant AST parsing on every request without state pollution between schemas.

---

## 🔄 Upgrading from v2.2

**Deprecated flag syntax** — the `--schema=` flag is replaced by a positional argument. It remains supported with a deprecation warning.

| Old syntax (still works) | New syntax |
|---|---|
| `lighthouse:multi-validate-schema --schema=admin` | `lighthouse:multi-validate admin` |
| `lighthouse:multi-ide-helper --schema=admin` | `lighthouse:multi-ide-helper admin` |
| `lighthouse:clear-cache all` | `lighthouse:multi-clear` |

**New commands in v2.3**

| Command | Notes |
|---|---|
| `lighthouse:multi-cache` | No equivalent in previous versions |
| `lighthouse:multi-clear` | Standalone command. No argument = clears **all** schemas |

> `lighthouse:clear-cache` is unchanged — without an argument it still clears only the default schema. Use `lighthouse:multi-clear` to clear all schemas at once.

---

## 📂 Directory Structure

There is no required structure - organize schema files however fits your project.

**Simple** (small projects or API versioning)
```
graphql/
├── schema.graphql      # default Lighthouse schema
├── v1.graphql          # #import models/*.graphql, #import v1/request/*.graphql
├── v2.graphql          # #import models/*.graphql, #import v2/request/*.graphql
├── models/             # shared Types, Enums, Inputs across all schemas
│   ├── User.graphql
│   ├── Order.graphql
│   └── Product.graphql
├── v1/
│   └── request/
│       ├── user_request.graphql
│       └── order_request.graphql
└── v2/
    └── request/
        ├── user_request.graphql
        └── order_request.graphql
```

**By API surface** (recommended - admin, app, public, etc.)
```
graphql/
├── models/                     # shared Types, Enums, Inputs for all schemas
│   ├── User.graphql
│   ├── Order.graphql
│   └── Product.graphql
│
├── admin/
│   ├── api_admin.graphql       # entry point: #import ../models/*.graphql
│   └── request/                #              #import admin/request/*.graphql
│       ├── user_admin.graphql
│       └── order_admin.graphql
│
├── app/
│   ├── api_app.graphql         # entry point: #import ../models/*.graphql
│   └── request/                #              #import app/request/*.graphql
│       ├── user_app.graphql
│       └── order_app.graphql
│
└── public/
    ├── api_public.graphql
    └── request/
        └── product_public.graphql
```

Each schema folder has a single entry point file that stitches together shared models and its own requests via `#import`. This keeps schemas fully isolated while sharing common type definitions.

---

## 📄 Changelog

See [Releases](https://github.com/as-yakovenko/laravel-lighthouse-graphql-multi-schema/releases) for the full release history.

---

## 🤝 Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/as-yakovenko/laravel-lighthouse-graphql-multi-schema). Please open an issue before submitting large changes.

---

## License

MIT. See [LICENSE](LICENSE) for details.

---

**Author:** [Alexander Yakovenko](https://github.com/as-yakovenko) - Senior Software Engineer specializing in Laravel, GraphQL, and high-load systems.
