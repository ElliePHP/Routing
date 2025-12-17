# ElliePHP Routing

A lightweight, high-performance PHP routing library built for modern applications. Based on FastRoute and PSR standards, it provides an expressive API to handle HTTP requests elegantly.

## Table of Contents
- [Installation](#installation)
- [Configuration & Bootstrapping](#configuration--bootstrapping)
- [Basic Routing](#basic-routing)
- [Route Parameters](#route-parameters)
- [Route Groups](#route-groups)
- [Domain Routing](#domain-routing)
- [Middleware](#middleware)
- [Controllers](#controllers)
- [Dependency Injection](#dependency-injection)
- [Error Handling](#error-handling)
- [Optimization & Caching](#optimization--caching)

---

## Installation

You may install the library via Composer:

```bash
composer require elliephp/routing
```

## Configuration & Bootstrapping

Unlike full-stack frameworks, ElliePHP Routing requires a small entry script (typically `index.php`) to boot the router.

### The Bootstrapper

Here is a production-ready example of how to configure and dispatch the router:

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// 1. Configure the Router
// It is recommended to separate configuration based on environment
Router::configure([
    'debug_mode'      => $_ENV['APP_DEBUG'] ?? false,
    'cache_enabled'   => $_ENV['APP_ENV'] === 'production',
    'cache_directory' => __DIR__ . '/storage/cache',
    'routes_directory' => __DIR__ . '/routes',
    'container'       => $container, // Optional PSR-11 Container
]);

// 2. Define or Load Routes
Router::get('/', function () {
    return ['status' => 'API Online'];
});

// 3. Dispatch the Request
$request = ServerRequest::fromGlobals();
$response = Router::handle($request);

// 4. Emit the Response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Configuration Options

| Option | Type | Description |
| :--- | :--- | :--- |
| `debug_mode` | `bool` | Enables detailed error traces and route tables. Disable in production. |
| `cache_enabled` | `bool` | Compiles routes to a single file for high performance. |
| `cache_directory` | `string` | The writable path where cache files are stored. |
| `container` | `object` | A PSR-11 Container instance for resolving controllers. |
| `enforce_domain` | `bool` | If true, rejects requests from domains not defined in routes. |

---

## Basic Routing

The simplest ElliePHP routes accept a URI and a closure. The router automatically converts array returns into JSON responses.

```php
use ElliePHP\Components\Routing\Router;

Router::get('/greeting', function () {
    return ['message' => 'Hello World'];
});
```

### Available Methods

The router allows you to register routes that respond to any HTTP verb:

```php
Router::get($uri, $callback);
Router::post($uri, $callback);
Router::put($uri, $callback);
Router::patch($uri, $callback);
Router::delete($uri, $callback);
Router::options($uri, $callback);
```

### Named Routes

Named routes allow the convenient generation of URLs or redirects for specific routes.

**Fluent Syntax:**
```php
Router::get('/user/profile', function () {
    // ...
})->name('profile');
```

**Array Syntax:**
```php
Router::get('/user/profile', function () {
    // ...
}, ['name' => 'profile']);
```

---

## Route Parameters

You can capture segments of the URI within your route by defining parameters in curly braces.

### Required Parameters

```php
Router::get('/user/{id}', function ($request, $params) {
    return 'User ' . $params['id'];
});
```

### Regular Expression Constraints

You may constrain the format of your route parameters using regex pattern matching.

**Fluent Syntax:**
*Currently, regex constraints are defined inline within the URI definition.*

```php
Router::get('/user/{id:\d+}', function ($request, $params) {
    // Only executes if {id} is numeric
});

Router::get('/posts/{slug:[a-z-]+}', function ($request, $params) {
    // Only executes if {slug} is generic slug format
});
```

---

## Route Groups

Route groups allow you to share route attributes, such as middleware, prefixes, names, or domains, across a large number of routes without needing to define those attributes on each individual route.

### Prefixes & Name Spacing

**Fluent Syntax (Recommended):**
```php
Router::prefix('/admin')
    ->name('admin.')
    ->group(function () {
        
        // URI: /admin/users
        // Name: admin.users
        Router::get('/users', function () {
            // ...
        })->name('users');
        
    });
```

**Array Syntax:**
```php
Router::group([
    'prefix' => '/admin',
    'name'   => 'admin.'
], function () {
    
    Router::get('/users', function () {
        // ...
    })->name('users');
    
});
```

### Nested Groups

ElliePHP supports deep nesting of groups. The router intelligently merges prefixes, middleware, and names.

```php
Router::prefix('/api')->group(function () {
    
    // URI: /api/v1/...
    Router::prefix('/v1')->group(function () {
        Router::get('/user', [UserController::class, 'index']);
    });

});
```

---

## Domain Routing

Domain routing allows you to restrict groups of routes to specific subdomains. This is essential for multi-tenant applications.

### Subdomain Parameters

You may define route parameters directly in the domain string to capture segments of the subdomain (e.g., a tenant name).

**Fluent Syntax:**
```php
Router::domain('{account}.myapp.com')->group(function () {
    
    Router::get('/user/{id}', function ($request, $params) {
        // Access domain parameters via $params
        return [
            'account' => $params['account'],
            'user_id' => $params['id']
        ];
    });

});
```

**Array Syntax:**
```php
Router::group(['domain' => '{account}.myapp.com'], function () {
    Router::get('/', function() { /* ... */ });
});
```

> **Security Note:** To ensure your application accepts *only* requests from defined domains, set `'enforce_domain' => true` in your configuration.

---

## Middleware

Middleware provides a convenient mechanism for inspecting and filtering HTTP requests entering your application. ElliePHP supports **PSR-15** middleware.

### Assigning Middleware to Routes

**Fluent Syntax:**
```php
Router::get('/profile', function () {
    // ...
})->middleware([AuthMiddleware::class]);
```

**Array Syntax:**
```php
Router::get('/profile', function () {
    // ...
}, ['middleware' => [AuthMiddleware::class]]);
```

### Assigning Middleware to Groups

**Fluent Syntax:**
```php
Router::middleware([AuthMiddleware::class, ThrottleMiddleware::class])
    ->prefix('/api')
    ->group(function () {
        Router::get('/user', function () {
            // ...
        });
    });
```

**Array Syntax:**
```php
Router::group([
    'prefix' => '/api', 
    'middleware' => [AuthMiddleware::class]
], function () {
    // ...
});
```

### Global Middleware

If you want middleware to run during every single request to your application (like CORS or Session handling), you may list it in the `configure` method.

```php
Router::configure([
    'global_middleware' => [
        CorsMiddleware::class,
        SecurityHeadersMiddleware::class,
    ]
]);
```

---

## Controllers

Instead of defining all of your request handling logic as closures, you may organize this behavior using Controller classes.

### Basic Controller Usage

Pass an array containing the class name and method name.

```php
use App\Controllers\UserController;

Router::get('/user/{id}', [UserController::class, 'show']);
```

### String Syntax

Alternatively, you may use the "At" string syntax:

```php
Router::get('/user/{id}', 'App\Controllers\UserController@show');
```

---

## Dependency Injection

ElliePHP Routing is built to work seamlessly with any **PSR-11** compliant container (PHP-DI, Symfony, Laravel, etc.).

### Automatic Resolution

If you provide a container instance in `Router::configure()`, the router will automatically resolve your Controller classes and inject their dependencies.

**Configuration:**
```php
Router::configure([
    'container' => $container
]);
```

**Controller:**
```php
class UserController
{
    // These dependencies are automatically injected
    public function __construct(
        protected UserRepository $users,
        protected LoggerInterface $logger
    ) {}

    // Route parameters are passed to the method
    public function show(ServerRequestInterface $request, string $id)
    {
        return $this->users->find($id);
    }
}
```

---

## Error Handling

By default, the router returns JSON error responses.

*   **404** - Route not found
*   **405** - Method not allowed
*   **500** - Internal Server Error

### Custom Formatters

You may customize error responses (e.g., to return HTML views) by implementing `ErrorFormatterInterface`.

```php
use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;

class HtmlErrorFormatter implements ErrorFormatterInterface 
{
    public function format(\Throwable $e, bool $debug): array
    {
        // Return your HTML content here
        return [
            'body' => "<h1>Error: {$e->getMessage()}</h1>",
            'status' => 500
        ];
    }
}
```

Register it in your configuration:

```php
Router::configure([
    'error_formatter' => new HtmlErrorFormatter()
]);
```

---

## Optimization & Caching

### Route Caching

When deploying to production, you should take advantage of ElliePHP's route cache. Using the route cache will drastically decrease the amount of time it takes to register all of your application's routes.

**Production Configuration:**
```php
Router::configure([
    'debug_mode'    => false, 
    'cache_enabled' => true,
    'cache_directory' => __DIR__ . '/storage/cache',
]);
```

> **Warning:** When `cache_enabled` is set to `true`, any changes you make to your routes will not take effect until you clear the cache file or the filename changes.

### Debugging

In development, enable `debug_mode` to access helper tools.

```php
// Print a formatted ASCII table of all registered routes
echo Router::printRoutes();

// Programmatically retrieve route list
$routes = Router::getRoutes();
```