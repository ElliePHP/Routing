# ElliePHP Routing

A lightweight, high-performance PHP routing library built for modern applications. Based on FastRoute and PSR standards, it provides everything you need to handle HTTP requests elegantly without unnecessary complexity.

## Why Choose ElliePHP Routing?

- **High Performance** - Built on FastRoute with intelligent caching and optimized dispatcher
- **Simple and Intuitive** - Define routes quickly with clean, expressive syntax
- **Standards-Based** - Full PSR-7 (HTTP messages) and PSR-15 (middleware) compliance
- **Flexible** - Use closures, controllers, or mix both - whatever fits your style
- **Middleware Support** - Stack middleware for authentication, logging, CORS, and more
- **Multi-Tenant Ready** - Built-in subdomain and domain-based routing with parameter extraction
- **Fluent API** - Expressive method chaining for readable route definitions
- **Debug Tools** - Route tables, timing headers, and detailed error messages
- **DI Container** - PSR-11 container integration for dependency injection
- **Production Ready** - Route caching, domain enforcement, and security features

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer require elliephp/routing
```

## Table of Contents

- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
  - [Defining Routes](#defining-routes)
  - [Route Parameters](#route-parameters)
  - [Using Controllers](#using-controllers)
- [Route Groups](#route-groups)
- [Fluent API](#fluent-api-method-chaining)
- [Middleware](#middleware)
- [Domain Routing](#domain-routing)
- [Configuration](#configuration)
- [Dependency Injection](#dependency-injection-psr-11)
- [Error Handling](#error-handling)
- [Debugging & Development](#debugging--development)
- [Performance & Caching](#performance--caching)
- [Advanced Features](#advanced-features)
- [Complete Examples](#complete-examples)

## Quick Start

Here's a complete working example to get you started:

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure the router (optional, but recommended)
Router::configure([
    'debug_mode' => true,  // Shows helpful errors during development
]);

// Define your routes
Router::get('/', function () {
    return ['message' => 'Hello, World!'];
});

Router::get('/hello/{name}', function ($request, $params) {
    return ['message' => "Hello, {$params['name']}!"];
});

// Handle the incoming request
$request = ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send the response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

That's it! You have a working API router.

## Basic Usage

### Defining Routes

The router supports all standard HTTP methods with a clean, intuitive API:

```php
// GET request
Router::get('/users', function () {
    return ['users' => []];
});

// POST request
Router::post('/users', function ($request) {
    // Access request body
    $body = json_decode($request->getBody()->getContents(), true);
    return ['message' => 'User created', 'data' => $body];
});

// PUT request
Router::put('/users/{id}', function ($request, $params) {
    return ['message' => "User {$params['id']} updated"];
});

// DELETE request
Router::delete('/users/{id}', function ($request, $params) {
    return ['message' => "User {$params['id']} deleted"];
});

// PATCH request
Router::patch('/users/{id}', function ($request, $params) {
    return ['message' => "User {$params['id']} patched"];
});
```

**Handler Signatures:**

Your route handlers can accept:
- `$request` - PSR-7 ServerRequestInterface
- `$params` - Array of route parameters

```php
// Both parameters
Router::get('/posts/{id}', function ($request, $params) {
    $postId = $params['id'];
    $queryParams = $request->getQueryParams();
    return ['post_id' => $postId, 'query' => $queryParams];
});

// Using both request and parameters
Router::get('/users/{id}', function ($request, $params) {
    return ['user_id' => $params['id']];
});
```

### Route Parameters

Capture dynamic parts of the URL with curly braces:

```php
// Single parameter
Router::get('/users/{id}', function ($request, $params) {
    $userId = $params['id'];
    return ['user_id' => $userId];
});

// Multiple parameters
Router::get('/posts/{postId}/comments/{commentId}', function ($request, $params) {
    return [
        'post' => $params['postId'],
        'comment' => $params['commentId']
    ];
});

// Parameters work with any HTTP method
Router::put('/users/{id}/posts/{postId}', function ($request, $params) {
    return [
        'user' => $params['id'],
        'post' => $params['postId'],
        'action' => 'update'
    ];
});
```

**Parameter Patterns:**

Parameters use FastRoute's pattern matching system:

```php
// Numeric only
Router::get('/users/{id:\d+}', function ($request, $params) {
    return ['user_id' => (int)$params['id']];
});

// Custom regex patterns
Router::get('/posts/{slug:[a-z-]+}', function ($request, $params) {
    return ['slug' => $params['slug']];
});
```

### Using Controllers

Keep your code organized and testable with controller classes:

```php
class UserController
{
    public function index(ServerRequestInterface $request): array
    {
        return [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]
        ];
    }

    public function show(ServerRequestInterface $request, string $id): array
    {
        return [
            'user' => [
                'id' => $id,
                'name' => 'User ' . $id,
                'email' => "user{$id}@example.com"
            ]
        ];
    }

    public function store(ServerRequestInterface $request): array
    {
        $body = json_decode($request->getBody()->getContents(), true);
        return ['message' => 'User created', 'data' => $body];
    }
}

// Array syntax [ClassName::class, 'methodName']
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);

// String syntax 'ClassName@methodName' (alternative)
Router::get('/users', 'UserController@index');
```

**Controller Method Injection:**

The router automatically injects route parameters into controller methods:

```php
class PostController
{
    // Parameters are automatically injected by name
    public function show(ServerRequestInterface $request, string $id): array
    {
        // $id is automatically extracted from route parameter
        return ['post_id' => $id];
    }

    // Multiple parameters are supported
    public function showComment(
        ServerRequestInterface $request,
        string $postId,
        string $commentId
    ): array {
        return [
            'post' => $postId,
            'comment' => $commentId
        ];
    }
}

Router::get('/posts/{id}', [PostController::class, 'show']);
Router::get('/posts/{postId}/comments/{commentId}', [PostController::class, 'showComment']);
```

## Route Groups

Group related routes together to share common attributes like prefixes, middleware, and domains. This keeps your code DRY and organized.

### Basic Groups

```php
Router::group(['prefix' => '/api'], function () {
    Router::get('/users', function () {
        return ['users' => []];
    });
    
    Router::get('/posts', function () {
        return ['posts' => []];
    });
});

// These routes are now available at:
// - /api/users
// - /api/posts
```

### Group Options

Groups support multiple configuration options:

```php
Router::group([
    'prefix' => '/api/v1',                    // URL prefix
    'middleware' => [AuthMiddleware::class],  // Shared middleware
    'name' => 'api.v1',                      // Route name prefix
    'domain' => 'api.example.com'            // Domain constraint
], function () {
    Router::get('/users', function () {
        return ['users' => []];
    })->name('users');  // Full name: api.v1.users
});
```

### Nested Groups

Groups can be nested for complex structures:

```php
Router::group(['prefix' => '/api'], function () {
    
    // API v1
    Router::group(['prefix' => '/v1', 'name' => 'v1'], function () {
        Router::get('/users', function () {
            return ['version' => 'v1', 'users' => []];
        })->name('users');  // Full name: v1.users
    });
    
    // API v2
    Router::group(['prefix' => '/v2', 'name' => 'v2'], function () {
        Router::get('/users', function () {
            return ['version' => 'v2', 'users' => []];
        })->name('users');  // Full name: v2.users
    });
});

// Routes:
// - /api/v1/users (name: v1.users)
// - /api/v2/users (name: v2.users)
```

### Group Middleware

Apply middleware to all routes in a group:

```php
Router::group([
    'prefix' => '/admin',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function () {
    Router::get('/dashboard', function () {
        return ['page' => 'dashboard'];
    });
    
    Router::get('/users', function () {
        return ['users' => []];
    });
    
    // This route has additional middleware
    Router::get('/settings', function () {
        return ['settings' => []];
    }, [
        'middleware' => [AuditMiddleware::class]  // Merged with group middleware
    ]);
});

// All routes have AuthMiddleware and AdminMiddleware
// /admin/settings also has AuditMiddleware
```

## Fluent API (Method Chaining)

The fluent API provides an expressive, readable alternative to array-based configuration. It supports method chaining in any order and provides better IDE autocomplete support.

### Fluent Routes

Chain methods to configure routes:

```php
// Single route with middleware and name
Router::get('/dashboard', function () {
    return ['page' => 'dashboard'];
})
    ->middleware([AuthMiddleware::class])
    ->name('dashboard');

// Method order is flexible - these are equivalent
Router::get('/users', function () {
    return ['users' => []];
})
    ->name('users.index')
    ->middleware([AuthMiddleware::class])
    ->domain('api.example.com');

Router::get('/users', function () {
    return ['users' => []];
})
    ->domain('api.example.com')
    ->middleware([AuthMiddleware::class])
    ->name('users.index');
```

### Fluent Groups

Start groups with any configuration method:

```php
// Start with prefix
Router::prefix('/api')
    ->middleware([ApiMiddleware::class])
    ->name('api')
    ->group(function () {
        Router::get('/users', function () {
            return ['users' => []];
        });
    });

// Start with middleware
Router::middleware([AuthMiddleware::class])
    ->prefix('/admin')
    ->group(function () {
        Router::get('/settings', function () {
            return ['settings' => []];
        });
    });

// Start with domain
Router::domain('api.example.com')
    ->prefix('/v1')
    ->middleware([ApiMiddleware::class])
    ->group(function () {
        Router::get('/users', function () {
            return ['users' => []];
        });
    });

// Start with name
Router::name('blog')
    ->prefix('/blog')
    ->group(function () {
        Router::get('/posts', function () {
            return ['posts' => []];
        })->name('posts');  // Full name: blog.posts
    });
```

### Multiple Middleware Calls

Call `middleware()` multiple times to add middleware:

```php
Router::get('/admin/reports', function () {
    return ['reports' => []];
})
    ->middleware([AuthMiddleware::class])
    ->middleware([AdminMiddleware::class])
    ->middleware([AuditMiddleware::class])
    ->name('admin.reports');

// All three middleware will be applied in order
```

### Progressive Configuration

Build configuration conditionally:

```php
$route = Router::get('/api/data', function () {
    return ['data' => []];
});

if ($requiresAuth) {
    $route->middleware([AuthMiddleware::class]);
}

if ($isProduction) {
    $route->domain('api.production.com');
}

$route->name('api.data');
```

### Mixing Syntaxes

The fluent API and array syntax can be used together in the same application:

```php
Router::prefix('/mixed')->group(function () {
    // Fluent syntax
    Router::get('/fluent', function () {
        return ['type' => 'fluent'];
    })->middleware([ApiMiddleware::class]);
    
    // Array syntax (still works)
    Router::get('/array', function () {
        return ['type' => 'array'];
    }, [
        'middleware' => [ApiMiddleware::class]
    ]);
});

// Both work seamlessly together
```

## Middleware

Middleware provides a way to filter, modify, or inspect HTTP requests and responses. ElliePHP Routing fully supports PSR-15 middleware with both class-based and closure-based implementations.

### Creating PSR-15 Middleware

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Check authentication before handling request
        $token = $request->getHeaderLine('Authorization');
        
        if (empty($token)) {
            // Return 401 response (short-circuit the request)
            $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            return $factory->createResponse(401)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode([
                    'error' => 'Unauthorized'
                ])));
        }
        
        // Continue to next middleware/handler
        $response = $handler->handle($request);
        
        // Modify response after handling
        return $response->withHeader('X-Authenticated', 'true');
    }
}
```

### Applying Middleware

**On Single Routes:**

```php
// Array syntax
Router::get('/protected', function () {
    return ['data' => 'secret'];
}, [
    'middleware' => [AuthMiddleware::class]
]);

// Fluent syntax
Router::get('/protected', function () {
    return ['data' => 'secret'];
})
    ->middleware([AuthMiddleware::class]);

// Multiple middleware (executed in order)
Router::get('/admin/data', function () {
    return ['data' => 'admin'];
})
    ->middleware([AuthMiddleware::class, AdminMiddleware::class]);
```

**On Route Groups:**

```php
Router::group([
    'prefix' => '/api',
    'middleware' => [AuthMiddleware::class, ApiMiddleware::class]
], function () {
    Router::get('/users', function () {
        return ['users' => []];
    });
    
    // This route has group middleware plus additional middleware
    Router::get('/posts', function () {
        return ['posts' => []];
    }, [
        'middleware' => [CacheMiddleware::class]  // Merged with group middleware
    ]);
});
```

**Global Middleware:**

Apply middleware to all routes:

```php
Router::configure([
    'global_middleware' => [
        CorsMiddleware::class,
        LoggingMiddleware::class,
    ]
]);

// Global middleware executes first (outer layer),
// then group middleware, then route middleware (inner layer)
```

### Closure Middleware

For simple cases, use closures instead of classes:

```php
Router::get('/custom', function () {
    return ['message' => 'Hello'];
}, [
    'middleware' => [
        function ($request, $next) {
            // Before handler
            echo "Before request\n";
            
            $response = $next($request);
            
            // After handler
            echo "After request\n";
            return $response->withHeader('X-Custom', 'true');
        }
    ]
]);
```

### Middleware Examples

**Timing Middleware:**

```php
class TimingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = (microtime(true) - $start) * 1000;
        
        return $response->withHeader('X-Response-Time', round($duration, 2) . 'ms');
    }
}
```

**CORS Middleware:**

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            return $factory->createResponse(200)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

**Logging Middleware:**

```php
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        error_log("[{$method}] {$path} - Started");
        
        $response = $handler->handle($request);
        
        error_log("[{$method}] {$path} - Completed with status {$response->getStatusCode()}");
        
        return $response;
    }
}
```

### Middleware Execution Order

Middleware executes in a specific order:

1. **Global middleware** (configured in `Router::configure()`)
2. **Group middleware** (from outermost to innermost group)
3. **Route middleware** (specific to the route)

```php
Router::configure([
    'global_middleware' => [GlobalMiddleware::class]  // Executes first
]);

Router::group(['middleware' => [GroupMiddleware::class]], function () {  // Executes second
    Router::get('/test', function () {
        return ['test' => true];
    }, [
        'middleware' => [RouteMiddleware::class]  // Executes third
    ]);
});

// Execution order:
// 1. GlobalMiddleware
// 2. GroupMiddleware
// 3. RouteMiddleware
// 4. Route handler
// 5. RouteMiddleware (response)
// 6. GroupMiddleware (response)
// 7. GlobalMiddleware (response)
```

## Domain Routing

ElliePHP Routing provides domain-based routing for multi-tenant applications, API subdomains, and domain-specific functionality.

### Basic Domain Constraints

Restrict routes to specific domains:

```php
// Main domain
Router::get('/', function () {
    return ['message' => 'Welcome to example.com'];
}, ['domain' => 'example.com']);

// API subdomain
Router::domain('api.example.com')->group(function () {
    Router::get('/users', function () {
        return ['api' => 'users'];
    });
    
    Router::get('/posts', function () {
        return ['api' => 'posts'];
    });
});

// Admin subdomain
Router::domain('admin.example.com')->group(function () {
    Router::get('/dashboard', function () {
        return ['page' => 'admin dashboard'];
    });
});
```

### Dynamic Subdomains (Multi-Tenant)

Extract subdomain parameters for multi-tenant applications:

```php
// {tenant} will be extracted from the subdomain
Router::domain('{tenant}.example.com')->group(function () {
    Router::get('/dashboard', function ($request, $params) {
        $tenant = $params['tenant'];  // e.g., "acme", "widgets", "company"
        return [
            'tenant' => $tenant,
            'page' => 'dashboard'
        ];
    });
    
    Router::get('/users', function ($request, $params) {
        return [
            'tenant' => $params['tenant'],
            'users' => []  // Load users for this tenant
        ];
    });
    
    // Combine domain and path parameters
    Router::get('/users/{id}', function ($request, $params) {
        return [
            'tenant' => $params['tenant'],  // From domain
            'user_id' => $params['id']      // From path
        ];
    });
});

// Examples:
// acme.example.com/dashboard -> tenant = "acme"
// widgets.example.com/users/42 -> tenant = "widgets", id = "42"
```

### Multiple Domain Parameters

Extract multiple parts from the domain:

```php
Router::domain('{account}.{region}.example.com')->group(function () {
    Router::get('/data', function ($request, $params) {
        return [
            'account' => $params['account'],  // e.g., "acme"
            'region' => $params['region'],    // e.g., "us-east"
            'data' => []
        ];
    });
});

// Example: acme.us-east.example.com/data
// -> account = "acme", region = "us-east"
```

### Domain Enforcement

Restrict your application to specific domains:

```php
Router::configure([
    'enforce_domain' => true,
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        'admin.example.com',
        '{tenant}.example.com',  // Pattern-based domains
    ]
]);

// Requests from other domains will receive a 403 Forbidden response
```

### Mixed Domain Routing

Combine domain-specific and domain-agnostic routes:

```php
// Routes available on all domains
Router::get('/health', function () {
    return ['status' => 'healthy'];
});

// Routes only on api.example.com
Router::domain('api.example.com')->group(function () {
    Router::get('/v1/users', function () {
        return ['users' => []];
    });
});

// Routes only on admin.example.com
Router::domain('admin.example.com')->group(function () {
    Router::get('/dashboard', function () {
        return ['page' => 'admin'];
    });
});
```

### Real-World Multi-Tenant Example

```php
// Configure domain enforcement
Router::configure([
    'enforce_domain' => true,
    'allowed_domains' => [
        'app.example.com',           // Main app
        '{tenant}.example.com',      // Tenant subdomains
    ]
]);

// Main application domain
Router::domain('app.example.com')->group(function () {
    Router::get('/', function () {
        return ['page' => 'landing'];
    });
    
    Router::get('/pricing', function () {
        return ['plans' => []];
    });
});

// Tenant-specific routes
Router::domain('{tenant}.example.com')
    ->middleware([TenantMiddleware::class])
    ->group(function () {
        Router::get('/dashboard', function ($request, $params) {
            $tenant = $params['tenant'];
            // Load tenant-specific data
            return [
                'tenant' => $tenant,
                'dashboard' => []
            ];
        });
        
        Router::get('/settings', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'settings' => []
            ];
        });
        
        Router::get('/users/{id}', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'user' => ['id' => $params['id']]
            ];
        });
    });
```

## Configuration

Configure the router before defining routes using `Router::configure()`. Configuration should be done before the router is first used.

### Array-Based Configuration

The traditional way to configure the router using an array:

### Configuration Options

```php
Router::configure([
    // Development & Debugging
    // Enable detailed error messages and timing headers
    // WARNING: Disable in production - exposes sensitive information
    'debug_mode' => $_ENV['APP_ENV'] !== 'production',
    
    // Performance & Caching
    // Enable route caching for production (significant performance boost)
    // Automatically disabled when debug_mode is true
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    
    // Directory for cache files (defaults to system temp directory)
    'cache_directory' => __DIR__ . '/storage/cache',
    
    // Route Loading
    // Directory containing route files (optional)
    // Set to '/' to define routes programmatically only
    'routes_directory' => __DIR__ . '/routes',
    
    // Domain Security
    // Reject requests from domains not in allowed_domains
    'enforce_domain' => true,
    
    // Array of allowed domains (supports patterns like {tenant}.example.com)
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        'admin.example.com',
        '{tenant}.example.com',
    ],
    
    // Middleware
    // Global middleware applied to ALL routes
    // Executes before group and route middleware
    'global_middleware' => [
        CorsMiddleware::class,
        LoggingMiddleware::class,
        SecurityHeadersMiddleware::class,
    ],
    
    // Dependency Injection
    // PSR-11 container for dependency injection
    // Controllers and middleware will be resolved from the container
    'container' => $container,
    
    // Error Handling
    // Custom error formatter (must implement ErrorFormatterInterface)
    // Defaults to JSON error formatter
    'error_formatter' => new CustomErrorFormatter(),
]);
```

### Fluent Configuration

Use the fluent configuration builder for a more expressive approach:

```php
Router::configure()
    ->debugMode($_ENV['APP_ENV'] !== 'production')
    ->enableCache()
    ->cacheDirectory(__DIR__ . '/storage/cache')
    ->routesDirectory(__DIR__ . '/routes')
    ->enforceDomain()
    ->allowedDomains(['example.com', 'api.example.com'])
    ->addGlobalMiddleware(CorsMiddleware::class)
    ->addGlobalMiddleware(LoggingMiddleware::class)
    ->container($container)
    ->build();
```

### Environment-Based Configuration

```php
// Load environment variables
$isProduction = $_ENV['APP_ENV'] === 'production';
$isDebug = $_ENV['APP_DEBUG'] === 'true';

Router::configure([
    'debug_mode' => $isDebug && !$isProduction,
    'cache_enabled' => $isProduction,
    'cache_directory' => $_ENV['CACHE_DIR'] ?? __DIR__ . '/cache',
    'enforce_domain' => $isProduction,
    'allowed_domains' => explode(',', $_ENV['ALLOWED_DOMAINS'] ?? ''),
]);
```

### Minimal Configuration

For simple applications, you can skip configuration:

```php
// No configuration needed - uses sensible defaults
Router::get('/', function () {
    return ['message' => 'Hello, World!'];
});
```

### Development vs Production

**Development:**

```php
Router::configure([
    'debug_mode' => true,        // Detailed errors
    'cache_enabled' => false,    // No caching for instant updates
    'enforce_domain' => false,   // Allow localhost, 127.0.0.1, etc.
]);
```

**Production:**

```php
Router::configure([
    'debug_mode' => false,       // Hide sensitive information
    'cache_enabled' => true,     // Cache routes for performance
    'cache_directory' => '/var/cache/routes',
    'enforce_domain' => true,    // Security: only allow specific domains
    'allowed_domains' => [
        'example.com',
        'www.example.com',
        'api.example.com',
    ],
    'global_middleware' => [
        SecurityHeadersMiddleware::class,
        RateLimitMiddleware::class,
    ],
]);
```

## Debugging

See all registered routes:

```php
// Print a formatted table
echo Router::printRoutes();

// Get routes as array
$routes = Router::getRoutes();

// Get formatted routes for debugging
$formatted = Router::getFormattedRoutes();
```

## Real-World Example

Here's a more complete example showing how you might structure an API:

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;

// Configure
Router::configure([
    'debug_mode' => $_ENV['APP_ENV'] !== 'production',
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    'cache_directory' => __DIR__ . '/cache',
]);

// Public routes
Router::get('/', function () {
    return ['message' => 'Welcome to our API'];
});

Router::get('/health', function () {
    return ['status' => 'healthy'];
});

// API v1 routes
Router::prefix('/api/v1')
    ->middleware([ApiMiddleware::class])
    ->name('api.v1')
    ->group(function () {
        
        // Public endpoints
        Router::get('/products', [ProductController::class, 'index']);
        Router::get('/products/{id}', [ProductController::class, 'show']);
        
        // Protected endpoints
        Router::middleware([AuthMiddleware::class])
            ->group(function () {
                Router::post('/products', [ProductController::class, 'store']);
                Router::put('/products/{id}', [ProductController::class, 'update']);
                Router::delete('/products/{id}', [ProductController::class, 'destroy']);
            });
    });

// Admin routes
Router::prefix('/admin')
    ->middleware([AuthMiddleware::class, AdminMiddleware::class])
    ->group(function () {
        Router::get('/dashboard', [AdminController::class, 'dashboard']);
        Router::get('/users', [AdminController::class, 'users']);
    });

// Handle the request
$request = \Nyholm\Psr7\ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

## Tips & Best Practices

1. **Use debug mode during development** - Shows helpful error messages and route tables
2. **Enable caching in production** - Significantly improves performance
3. **Organize with groups** - Keep related routes together
4. **Use controllers for complex logic** - Keep route definitions clean
5. **Apply middleware at the group level** - Avoid repetition
6. **Name your routes** - Makes them easier to reference later
7. **Disable debug mode in production** - Never expose sensitive information

## What's Next?

- Check out the [examples](examples/) directory for more usage patterns
- Read about [middleware](examples/middleware-example.php) in depth
- Learn about [domain routing](examples/domain-routing.php) for multi-tenant applications
- Explore the [fluent API](examples/fluent-api-example.php) for expressive route definitions

## Requirements

- PHP 8.4 or higher
- Composer

## License

MIT License - feel free to use this in your projects.

## Questions or Issues?

- [Report issues](https://github.com/elliephp/routing/issues)
- [View source](https://github.com/elliephp/routing)

---

Made for the PHP community


## Dependency Injection (PSR-11)

ElliePHP Routing supports PSR-11 containers for automatic dependency injection of controllers and middleware.

### Setting Up a Container

```php
use Psr\Container\ContainerInterface;

// Use any PSR-11 compatible container
// Examples: PHP-DI, Symfony DependencyInjection, Laravel Container, etc.

$container = new YourPSR11Container();

// Register services
$container->set(UserRepository::class, function() {
    return new UserRepository($pdo);
});

$container->set(UserService::class, function($c) {
    return new UserService($c->get(UserRepository::class));
});

// Configure router with container
Router::configure([
    'container' => $container
]);
```

### Controller Dependency Injection

Controllers are automatically resolved from the container with their dependencies:

```php
class UserController
{
    // Dependencies are injected via constructor
    public function __construct(
        private UserRepository $userRepository,
        private UserService $userService
    ) {}

    public function index(ServerRequestInterface $request): array
    {
        $users = $this->userRepository->findAll();
        return ['users' => $users];
    }

    public function show(ServerRequestInterface $request, string $id): array
    {
        // Route parameters are still injected
        $user = $this->userService->findById((int)$id);
        return ['user' => $user];
    }
}

// Register controller in container
$container->set(UserController::class, function($c) {
    return new UserController(
        $c->get(UserRepository::class),
        $c->get(UserService::class)
    );
});

// Define route - controller will be resolved from container
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
```

### Middleware Dependency Injection

Middleware classes are also resolved from the container:

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $authService,
        private TokenValidator $tokenValidator
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaderLine('Authorization');
        
        if (!$this->tokenValidator->validate($token)) {
            // Return 401
        }
        
        $user = $this->authService->getUserFromToken($token);
        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        
        return $handler->handle($request);
    }
}

// Register in container
$container->set(AuthMiddleware::class, function($c) {
    return new AuthMiddleware(
        $c->get(AuthService::class),
        $c->get(TokenValidator::class)
    );
});

// Use in routes
Router::get('/profile', [ProfileController::class, 'show'])
    ->middleware([AuthMiddleware::class]);
```

### Without Container

If no container is configured, classes are instantiated directly:

```php
// No container configured
Router::configure([
    'container' => null  // or omit this option
]);

// Controllers must have no constructor dependencies
// or use default values
class SimpleController
{
    public function index(): array
    {
        return ['message' => 'Hello'];
    }
}

Router::get('/', [SimpleController::class, 'index']);
```

## Error Handling

ElliePHP Routing provides comprehensive error handling with customizable error formatters.

### Default Error Responses

By default, errors are returned as JSON:

```php
// 404 Not Found
{
    "error": "Route not found: GET /invalid-path",
    "status": 404
}

// 405 Method Not Allowed
{
    "error": "Method POST not allowed for route: /users",
    "status": 405
}

// 500 Internal Server Error
{
    "error": "Internal server error",
    "status": 500
}
```

### Debug Mode Errors

When `debug_mode` is enabled, errors include detailed information:

```php
Router::configure(['debug_mode' => true]);

// Error response includes:
{
    "error": "Route not found: GET /invalid",
    "status": 404,
    "debug": {
        "exception": "RouteNotFoundException",
        "file": "/path/to/file.php",
        "line": 123,
        "trace": [...]
    }
}
```

### Custom Error Formatter

Create a custom error formatter for HTML, XML, or custom formats:

```php
use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;

class HtmlErrorFormatter implements ErrorFormatterInterface
{
    public function format(\Throwable $e, bool $debug): array
    {
        $status = $e->getCode() >= 100 && $e->getCode() < 600 
            ? $e->getCode() 
            : 500;

        $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Error {$status}</title></head>
            <body>
                <h1>Error {$status}</h1>
                <p>{$e->getMessage()}</p>
            </body>
            </html>
        ";

        return [
            'html' => $html,
            'status' => $status
        ];
    }
}

// Use custom formatter
Router::configure([
    'error_formatter' => new HtmlErrorFormatter()
]);
```

### Exception Types

The router throws specific exceptions for different scenarios:

```php
use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use ElliePHP\Components\Routing\Exceptions\MiddlewareNotFoundException;
use ElliePHP\Components\Routing\Exceptions\ClassNotFoundException;

try {
    $response = Router::handle($request);
} catch (RouteNotFoundException $e) {
    // 404 - Route not found
} catch (MiddlewareNotFoundException $e) {
    // Middleware class not found
} catch (ClassNotFoundException $e) {
    // Controller class or method not found
} catch (RouterException $e) {
    // General router error
}
```

## Debugging & Development

ElliePHP Routing includes powerful debugging tools to help during development.

### Debug Mode

Enable debug mode for detailed error messages and timing information:

```php
Router::configure([
    'debug_mode' => true
]);

// Responses include debug headers:
// X-Debug-Time: 15.23ms
// X-Debug-Routes: 42
// X-FRV: ElliePHP Router
```

### Route Table

View all registered routes in a formatted table:

```php
// Print route table to output
echo Router::printRoutes();

// Output:
// ┌────────┬─────────────────────┬──────────────────┬────────────┐
// │ Method │ Path                │ Handler          │ Name       │
// ├────────┼─────────────────────┼──────────────────┼────────────┤
// │ GET    │ /                   │ Closure          │ get.root   │
// │ GET    │ /users              │ UserController   │ users      │
// │ GET    │ /users/{id}         │ UserController   │ users.show │
// │ POST   │ /users              │ UserController   │ users.store│
// └────────┴─────────────────────┴──────────────────┴────────────┘
```

### Get Routes Programmatically

```php
// Get all routes as array
$routes = Router::getRoutes();

// Get formatted routes for debugging
$formatted = Router::getFormattedRoutes();

foreach ($formatted as $route) {
    echo "{$route['method']} {$route['path']} -> {$route['handler']}\n";
}
```

### Check Router State

```php
// Check if debug mode is enabled
if (Router::isDebugMode()) {
    echo "Debug mode is ON\n";
}

// Check if cache is enabled
if (Router::isCacheEnabled()) {
    echo "Cache is enabled\n";
}

// Get route count
$count = count(Router::getRoutes());
echo "Total routes: {$count}\n";
```

### Clear Cache

```php
// Clear route cache (useful during development)
Router::clearCache();

// Or clear cache programmatically
if ($_GET['clear_cache'] ?? false) {
    Router::clearCache();
    echo "Cache cleared!\n";
}
```

### Reset Router (Testing)

```php
// Reset router state (useful for testing)
Router::reset();

// After reset, you can reconfigure and define new routes
Router::configure(['debug_mode' => true]);
Router::get('/', function () {
    return ['message' => 'New routes'];
});
```

## Performance & Caching

ElliePHP Routing is built for speed with multiple performance optimizations.

### Route Caching

Enable caching in production for significant performance improvements:

```php
Router::configure([
    'cache_enabled' => true,
    'cache_directory' => __DIR__ . '/storage/cache'
]);

// Routes are compiled once and cached
// Subsequent requests use the cached dispatcher
// Cache is automatically invalidated when routes change
```

**Performance Impact:**
- First request: Routes are compiled and cached (5-10ms overhead)
- Subsequent requests: Routes loaded from cache (0.1-0.5ms)
- Result: 10-50x faster route resolution

### Cache Invalidation

The cache is automatically invalidated when:
- Routes are added, modified, or removed
- Route files in `routes_directory` are modified
- `Router::clearCache()` is called

### Optimization Tips

**1. Use Route Caching in Production:**

```php
Router::configure([
    'cache_enabled' => $_ENV['APP_ENV'] === 'production'
]);
```

**2. Disable Debug Mode in Production:**

```php
Router::configure([
    'debug_mode' => false  // Removes debug overhead
]);
```

**3. Use Controller Classes Instead of Closures:**

Closures cannot be cached effectively. Use controller classes for better performance:

```php
// Slower (closures can't be fully cached)
Router::get('/users', function () {
    return ['users' => []];
});

// Faster (controller classes are cached efficiently)
Router::get('/users', [UserController::class, 'index']);
```

**4. Minimize Middleware:**

Each middleware adds overhead. Only use necessary middleware:

```php
// Too many middleware
Router::get('/data', [DataController::class, 'index'])
    ->middleware([
        Middleware1::class,
        Middleware2::class,
        Middleware3::class,
        Middleware4::class,
        Middleware5::class,
    ]);

// Only essential middleware
Router::get('/data', [DataController::class, 'index'])
    ->middleware([AuthMiddleware::class]);
```

**5. Use Domain-Specific Dispatchers:**

The router creates separate dispatchers for each domain, improving performance for multi-tenant applications:

```php
// Each domain gets its own optimized dispatcher
Router::domain('tenant1.example.com')->group(function () {
    // Routes for tenant1
});

Router::domain('tenant2.example.com')->group(function () {
    // Routes for tenant2
});
```

### Benchmarks

On a typical application with 100 routes:

| Configuration | First Request | Cached Request | Improvement |
|--------------|---------------|----------------|-------------|
| No cache | 8.5ms | 8.5ms | - |
| With cache | 9.2ms | 0.3ms | 28x faster |

## Advanced Features

### Route Names

Name your routes for easy reference:

```php
// Set route name
Router::get('/users', [UserController::class, 'index'])
    ->name('users.index');

Router::get('/users/{id}', [UserController::class, 'show'])
    ->name('users.show');

// Names are automatically generated if not provided
// Format: {method}.{path}
// Example: get.users.id
```

### Loading Routes from Files

Organize routes in separate files:

```php
// Configure routes directory
Router::configure([
    'routes_directory' => __DIR__ . '/routes'
]);

// Create routes/web.php
<?php
$router->get('/', function () {
    return ['message' => 'Home'];
});

// Create routes/api.php
<?php
$router->group(['prefix' => '/api'], function ($router) {
    $router->get('/users', [UserController::class, 'index']);
});

// Routes are automatically loaded from all .php files in the directory
```

### Non-Facade Usage

Use the `Routing` class directly instead of the static facade:

```php
use ElliePHP\Components\Routing\Core\Routing;

// Create instance
$router = new Routing(
    routes_directory: '/',
    debugMode: true,
    cacheEnabled: false,
    cacheDirectory: null,
    errorFormatter: null,
    enforceDomain: false,
    allowedDomains: [],
    globalMiddleware: [],
    container: null
);

// Define routes
$router->get('/', function () {
    return ['message' => 'Hello'];
});

// Handle request
$response = $router->handle($request);
```

**Benefits:**
- Better for testing (no global state)
- Multiple router instances
- Explicit dependencies
- Easier to mock

### Programmatic Route Registration

Register routes from arrays:

```php
$routes = [
    [
        'method' => 'GET',
        'path' => '/users',
        'handler' => [UserController::class, 'index'],
        'middleware' => [AuthMiddleware::class],
        'name' => 'users.index'
    ],
    [
        'method' => 'POST',
        'path' => '/users',
        'handler' => [UserController::class, 'store'],
        'middleware' => [AuthMiddleware::class],
        'name' => 'users.store'
    ],
];

Router::registerRoutes($routes);
```

### Custom Response Types

Return different response types from handlers:

```php
use Nyholm\Psr7\Response;

// Return array (automatically converted to JSON)
Router::get('/json', function () {
    return ['message' => 'JSON response'];
});

// Return PSR-7 Response directly
Router::get('/custom', function () {
    return new Response(
        200,
        ['Content-Type' => 'text/plain'],
        'Plain text response'
    );
});

// Return HTML
Router::get('/html', function () {
    $html = '<html><body><h1>Hello</h1></body></html>';
    return new Response(200, ['Content-Type' => 'text/html'], $html);
});
```



## Complete Examples

### Example 1: Simple API

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure
Router::configure([
    'debug_mode' => true
]);

// Routes
Router::get('/', function () {
    return [
        'name' => 'My API',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /' => 'API information',
            'GET /users' => 'List users',
            'GET /users/{id}' => 'Get user by ID',
        ]
    ];
});

Router::get('/users', function () {
    return [
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]
    ];
});

Router::get('/users/{id}', function ($request, $params) {
    return [
        'user' => [
            'id' => $params['id'],
            'name' => 'User ' . $params['id']
        ]
    ];
});

// Handle request
$request = ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
header('Content-Type: application/json');
echo $response->getBody();
```

### Example 2: RESTful API with Controllers

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

// Controller
class UserController
{
    public function index(ServerRequestInterface $request): array
    {
        return ['users' => [/* ... */]];
    }

    public function show(ServerRequestInterface $request, string $id): array
    {
        return ['user' => ['id' => $id]];
    }

    public function store(ServerRequestInterface $request): array
    {
        $data = json_decode($request->getBody()->getContents(), true);
        return ['message' => 'User created', 'data' => $data];
    }

    public function update(ServerRequestInterface $request, string $id): array
    {
        $data = json_decode($request->getBody()->getContents(), true);
        return ['message' => 'User updated', 'id' => $id, 'data' => $data];
    }

    public function destroy(ServerRequestInterface $request, string $id): array
    {
        return ['message' => 'User deleted', 'id' => $id];
    }
}

// Configure
Router::configure(['debug_mode' => true]);

// RESTful routes
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);

// Handle request
$request = \Nyholm\Psr7\ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Example 3: API with Middleware & Groups

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// Middleware
class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaderLine('Authorization');
        
        if (empty($token)) {
            $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            return $factory->createResponse(401)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode([
                    'error' => 'Unauthorized'
                ])));
        }
        
        return $handler->handle($request);
    }
}

class ApiMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $handler->handle($request)
            ->withHeader('X-API-Version', '1.0.0');
    }
}

// Configure
Router::configure([
    'debug_mode' => true,
    'global_middleware' => [ApiMiddleware::class]
]);

// Public routes
Router::get('/', function () {
    return ['message' => 'Welcome to the API'];
});

Router::get('/health', function () {
    return ['status' => 'healthy'];
});

// Protected API routes
Router::prefix('/api')
    ->middleware([AuthMiddleware::class])
    ->group(function () {
        Router::get('/users', function () {
            return ['users' => []];
        });
        
        Router::get('/posts', function () {
            return ['posts' => []];
        });
        
        Router::post('/posts', function ($request) {
            return ['message' => 'Post created'];
        });
    });

// Handle request
$request = \Nyholm\Psr7\ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Example 4: Multi-Tenant SaaS Application

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;

// Configure with domain enforcement
Router::configure([
    'debug_mode' => false,
    'cache_enabled' => true,
    'enforce_domain' => true,
    'allowed_domains' => [
        'app.example.com',
        '{tenant}.example.com',
    ]
]);

// Main application (app.example.com)
Router::domain('app.example.com')->group(function () {
    Router::get('/', function () {
        return ['page' => 'landing'];
    });
    
    Router::get('/pricing', function () {
        return ['plans' => []];
    });
    
    Router::post('/signup', function ($request) {
        return ['message' => 'Account created'];
    });
});

// Tenant subdomains ({tenant}.example.com)
Router::domain('{tenant}.example.com')
    ->middleware([TenantMiddleware::class])
    ->group(function () {
        // Dashboard
        Router::get('/dashboard', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'page' => 'dashboard',
                'data' => []
            ];
        });
        
        // Users
        Router::get('/users', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'users' => []
            ];
        });
        
        Router::get('/users/{id}', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'user' => ['id' => $params['id']]
            ];
        });
        
        // Settings
        Router::get('/settings', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'settings' => []
            ];
        });
    });

// Handle request
$request = \Nyholm\Psr7\ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Example 5: Production Application with DI Container

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Psr\Container\ContainerInterface;

// Setup container (using PHP-DI as example)
$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->addDefinitions([
    PDO::class => function() {
        return new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
    },
    UserRepository::class => \DI\autowire(),
    UserService::class => \DI\autowire(),
    UserController::class => \DI\autowire(),
]);
$container = $containerBuilder->build();

// Configure router
Router::configure([
    'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    'cache_directory' => __DIR__ . '/storage/cache',
    'container' => $container,
    'global_middleware' => [
        CorsMiddleware::class,
        LoggingMiddleware::class,
    ],
]);

// Define routes
Router::get('/', function () {
    return ['message' => 'Welcome'];
});

Router::prefix('/api/v1')
    ->middleware([ApiMiddleware::class])
    ->name('api.v1')
    ->group(function () {
        // Public endpoints
        Router::get('/status', function () {
            return ['status' => 'operational'];
        });
        
        // Protected endpoints
        Router::middleware([AuthMiddleware::class])->group(function () {
            Router::get('/users', [UserController::class, 'index']);
            Router::get('/users/{id}', [UserController::class, 'show']);
            Router::post('/users', [UserController::class, 'store']);
            Router::put('/users/{id}', [UserController::class, 'update']);
            Router::delete('/users/{id}', [UserController::class, 'destroy']);
        });
    });

// Handle request
$request = \Nyholm\Psr7\ServerRequest::fromGlobals();
$response = Router::handle($request);

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

## Tips & Best Practices

### 1. Use Debug Mode Only in Development

```php
// Good
Router::configure([
    'debug_mode' => $_ENV['APP_ENV'] !== 'production'
]);

// Bad - exposes sensitive information
Router::configure([
    'debug_mode' => true  // Always on!
]);
```

### 2. Enable Caching in Production

```php
// Good - significant performance boost
Router::configure([
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    'cache_directory' => __DIR__ . '/storage/cache'
]);
```

### 3. Use Controller Classes for Complex Logic

```php
// Good - testable, organized, cacheable
Router::get('/users', [UserController::class, 'index']);

// Bad - hard to test, not cacheable
Router::get('/users', function () {
    // 50 lines of business logic...
});
```

### 4. Apply Middleware at Group Level

```php
// Good - DRY, maintainable
Router::middleware([AuthMiddleware::class])->group(function () {
    Router::get('/users', [UserController::class, 'index']);
    Router::get('/posts', [PostController::class, 'index']);
    Router::get('/comments', [CommentController::class, 'index']);
});

// Bad - repetitive
Router::get('/users', [UserController::class, 'index'])
    ->middleware([AuthMiddleware::class]);
Router::get('/posts', [PostController::class, 'index'])
    ->middleware([AuthMiddleware::class]);
Router::get('/comments', [CommentController::class, 'index'])
    ->middleware([AuthMiddleware::class]);
```

### 5. Use Route Names for Important Routes

```php
// Good - easy to reference
Router::get('/users/{id}', [UserController::class, 'show'])
    ->name('users.show');

// Later: generate URLs, reference in tests, etc.
```

### 6. Organize Routes with Groups

```php
// Good - organized, clear structure
Router::prefix('/api/v1')->name('api.v1')->group(function () {
    Router::prefix('/users')->name('users')->group(function () {
        Router::get('', [UserController::class, 'index'])->name('index');
        Router::get('/{id}', [UserController::class, 'show'])->name('show');
    });
});
```

### 7. Use Type Hints in Controllers

```php
// Good - type safety, better IDE support
public function show(ServerRequestInterface $request, string $id): array
{
    return ['user' => ['id' => $id]];
}

// Bad - no type safety
public function show($request, $id)
{
    return ['user' => ['id' => $id]];
}
```

### 8. Handle Errors Gracefully

```php
// Good - custom error handling
try {
    $response = Router::handle($request);
} catch (RouteNotFoundException $e) {
    // Log 404
    // Return custom 404 page
} catch (RouterException $e) {
    // Log error
    // Return custom error page
}
```

## Testing

### Testing with PHPUnit

```php
use PHPUnit\Framework\TestCase;
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset router before each test
        Router::resetInstance();
        
        // Configure for testing
        Router::configure([
            'debug_mode' => true,
            'cache_enabled' => false
        ]);
    }

    public function testBasicRoute(): void
    {
        Router::get('/test', function () {
            return ['message' => 'test'];
        });

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('test', (string)$response->getBody());
    }

    public function testRouteWithParameters(): void
    {
        Router::get('/users/{id}', function ($request, $params) {
            return ['user_id' => $params['id']];
        });

        $request = new ServerRequest('GET', '/users/123');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('123', $body['user_id']);
    }

    public function test404Response(): void
    {
        $request = new ServerRequest('GET', '/nonexistent');
        $response = Router::handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
```

### Testing with Non-Facade Usage

```php
use ElliePHP\Components\Routing\Core\Routing;

class RouterTest extends TestCase
{
    private Routing $router;

    protected function setUp(): void
    {
        // Create fresh instance for each test
        $this->router = new Routing(
            routes_directory: '/',
            debugMode: true,
            cacheEnabled: false
        );
    }

    public function testRoute(): void
    {
        $this->router->get('/test', function () {
            return ['message' => 'test'];
        });

        $request = new ServerRequest('GET', '/test');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## Migration from Other Routers

### From Laravel

```php
// Laravel
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// ElliePHP Routing (very similar!)
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'store']);
Router::middleware([AuthMiddleware::class])->group(function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
});
```

### From Slim

```php
// Slim
$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
$app->group('/api', function ($group) {
    $group->get('/users', [UserController::class, 'index']);
});

// ElliePHP Routing
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'store']);
Router::prefix('/api')->group(function () {
    Router::get('/users', [UserController::class, 'index']);
});
```

## Troubleshooting

### Routes Not Found

**Problem:** 404 errors for routes that should exist.

**Solutions:**
1. Check route definition syntax
2. Verify route is registered before handling request
3. Clear cache: `Router::clearCache()`
4. Enable debug mode to see registered routes: `Router::printRoutes()`

### Middleware Not Executing

**Problem:** Middleware doesn't seem to run.

**Solutions:**
1. Verify middleware implements `MiddlewareInterface`
2. Check middleware is registered correctly
3. Ensure middleware calls `$handler->handle($request)`
4. Check middleware order (global → group → route)

### Controller Not Found

**Problem:** `ClassNotFoundException` when using controllers.

**Solutions:**
1. Verify class exists and is autoloaded
2. Check namespace is correct
3. Ensure method exists in controller
4. If using container, verify controller is registered

### Cache Issues

**Problem:** Changes to routes not reflected.

**Solutions:**
1. Clear cache: `Router::clearCache()`
2. Disable cache during development: `'cache_enabled' => false`
3. Check cache directory permissions
4. Verify cache directory path is correct

### Domain Routing Not Working

**Problem:** Domain-specific routes return 404.

**Solutions:**
1. Check domain pattern syntax
2. Verify request host matches domain pattern
3. Disable domain enforcement during testing: `'enforce_domain' => false`
4. Check allowed domains list

## FAQ

**Q: Can I use this without the static facade?**  
A: Yes! Use the `Routing` class directly. See [Non-Facade Usage](#non-facade-usage).

**Q: Does it support route caching?**  
A: Yes! Enable with `'cache_enabled' => true`. See [Performance & Caching](#performance--caching).

**Q: Can I use my own PSR-11 container?**  
A: Yes! Any PSR-11 compatible container works. See [Dependency Injection](#dependency-injection-psr-11).

**Q: How do I handle file uploads?**  
A: Access uploaded files via PSR-7 request: `$request->getUploadedFiles()`.

**Q: Can I return HTML instead of JSON?**  
A: Yes! Return a PSR-7 Response with HTML content. See [Custom Response Types](#custom-response-types).

**Q: Is it production-ready?**  
A: Yes! Used in production applications. Enable caching and disable debug mode.

**Q: How do I add CORS support?**  
A: Create a CORS middleware. See [Middleware Examples](#middleware-examples).

**Q: Can I use it with existing frameworks?**  
A: Yes! It's framework-agnostic and works standalone or integrated.

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License - feel free to use this in your projects!

## Support

- **Issues:** [GitHub Issues](https://github.com/elliephp/routing/issues)
- **Source:** [GitHub Repository](https://github.com/elliephp/routing)
- **Documentation:** This README

## Credits

Built with:
- [FastRoute](https://github.com/nikic/FastRoute) - Fast request router
- [PSR-7](https://www.php-fig.org/psr/psr-7/) - HTTP message interfaces
- [PSR-15](https://www.php-fig.org/psr/psr-15/) - HTTP server request handlers
- [Nyholm PSR-7](https://github.com/Nyholm/psr7) - PSR-7 implementation

---

**Made with ❤️ for the PHP community**

**ElliePHP Routing** - Simple, Fast, Reliable
