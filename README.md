# ElliePHP Routing Component

[![Latest Version](https://img.shields.io/packagist/v/elliephp/routing.svg?style=flat-square)](https://packagist.org/packages/elliephp/routing)
[![PHP Version](https://img.shields.io/packagist/php-v/elliephp/routing.svg?style=flat-square)](https://packagist.org/packages/elliephp/routing)
[![License](https://img.shields.io/packagist/l/elliephp/routing.svg?style=flat-square)](https://packagist.org/packages/elliephp/routing)
[![Total Downloads](https://img.shields.io/packagist/dt/elliephp/routing.svg?style=flat-square)](https://packagist.org/packages/elliephp/routing)

A minimal, fast routing component for ElliePHP API framework based on FastRoute and PSR-7/PSR-15 standards.

## Features

- **Fast Routing**: Built on nikic/fast-route for optimal performance
- **PSR Standards**: Full PSR-7 (HTTP messages) and PSR-15 (middleware) compliance
- **Flexible Handlers**: Support for closures, controller classes, and callable arrays
- **Middleware Support**: PSR-15 middleware with proper stack execution
- **Route Groups**: Organize routes with shared prefixes, middleware, and names
- **Route Caching**: Production-ready route caching for improved performance
- **Debug Mode**: Detailed error messages, timing info, and route visualization
- **Type Safe**: PHP 8.4+ with strict types and proper type hints

## Installation

```bash
composer require elliephp/routing
```

## Quick Start

### Basic Setup

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure the router (optional)
Router::configure([
    'debug_mode' => true,
]);

// Define a simple route
Router::get('/', function() {
    return ['message' => 'Hello World'];
});

// Handle the incoming request
$request = new ServerRequest('GET', '/');
$response = Router::handle($request);

// Output: {"message":"Hello World"}
echo $response->getBody();
```

### Using Without Facade

If you prefer not to use the static facade, you can work directly with the `Routing` class:

```php
<?php

use ElliePHP\Components\Routing\Core\Routing;
use Nyholm\Psr7\ServerRequest;

// Create router instance
$router = new Routing(
    routes_directory: __DIR__ . '/routes',
    debugMode: true,
    cacheEnabled: false
);

// Define routes
$router->get('/', function() {
    return ['message' => 'Hello World'];
});

$router->get('/users/{id}', function($request, $params) {
    return ['user_id' => $params['id']];
});

// Handle request
$request = new ServerRequest('GET', '/users/42');
$response = $router->handle($request);
```

## Usage Guide

### Defining Routes

#### Simple Routes

```php
// Using the facade
Router::get('/users', function() {
    return ['users' => []];
});

Router::post('/users', function($request) {
    return ['created' => true];
});

// Without facade
$router->get('/users', function() {
    return ['users' => []];
});

$router->post('/users', function($request) {
    return ['created' => true];
});
```

#### All HTTP Methods

```php
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::patch('/users/{id}', [UserController::class, 'patch']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);
```

#### Route Parameters

```php
// Single parameter
Router::get('/users/{id}', function($request, $params) {
    return ['user_id' => $params['id']];
});

// Multiple parameters
Router::get('/users/{userId}/posts/{postId}', function($request, $params) {
    return [
        'user_id' => $params['userId'],
        'post_id' => $params['postId']
    ];
});

// Optional parameters with controller
Router::get('/search/{query}', [SearchController::class, 'search']);
```

### Controllers

#### Basic Controller

```php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function index(ServerRequestInterface $request): array
    {
        // Return array for automatic JSON response
        return [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]
        ];
    }
    
    public function show(ServerRequestInterface $request, string $id): array
    {
        // Route parameters are automatically injected
        return [
            'user' => [
                'id' => $id,
                'name' => 'User ' . $id
            ]
        ];
    }
    
    public function store(ServerRequestInterface $request): array
    {
        // Access request body
        $body = json_decode((string)$request->getBody(), true);
        
        return [
            'message' => 'User created',
            'user' => $body
        ];
    }
}
```

#### Registering Controller Routes

```php
// Array syntax
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);

// String syntax (alternative)
Router::get('/users', 'UserController@index');

// With options
Router::post('/users', [UserController::class, 'store'], [
    'middleware' => [AuthMiddleware::class],
    'name' => 'users.store'
]);
```

#### Returning PSR-7 Responses

```php
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class UserController
{
    public function custom(ServerRequestInterface $request): ResponseInterface
    {
        // Build custom PSR-7 response
        return new Response(
            status: 201,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['created' => true])
        );
    }
}
```

### Route Groups

#### Basic Groups

```php
// With facade
Router::group(['prefix' => '/api'], function() {
    Router::get('/users', [UserController::class, 'index']);
    Router::post('/users', [UserController::class, 'store']);
});
// Routes: /api/users

// Without facade
$router->group(['prefix' => '/api'], function($router) {
    $router->get('/users', [UserController::class, 'index']);
});
```

#### Nested Groups

```php
Router::group(['prefix' => '/api'], function() {
    Router::group(['prefix' => '/v1'], function() {
        Router::get('/users', [UserController::class, 'index']);
        // Route: /api/v1/users
        
        Router::group(['prefix' => '/admin'], function() {
            Router::get('/dashboard', [AdminController::class, 'dashboard']);
            // Route: /api/v1/admin/dashboard
        });
    });
});
```

#### Groups with Middleware

```php
Router::group(['middleware' => [AuthMiddleware::class]], function() {
    Router::get('/profile', [ProfileController::class, 'show']);
    Router::put('/profile', [ProfileController::class, 'update']);
});

// Nested groups inherit parent middleware
Router::group(['middleware' => [AuthMiddleware::class]], function() {
    Router::group(['middleware' => [AdminMiddleware::class]], function() {
        Router::get('/admin/users', [AdminController::class, 'users']);
        // Has both AuthMiddleware and AdminMiddleware
    });
});
```

#### Groups with Names

```php
Router::group(['name' => 'api'], function() {
    Router::group(['name' => 'users'], function() {
        Router::get('/', [UserController::class, 'index'], [
            'name' => 'index'
        ]);
        // Full name: api.users.index
    });
});
```

### Middleware

#### Creating Middleware

```php
namespace App\Middleware;

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
        
        if (!$this->isValidToken($token)) {
            throw new UnauthorizedException('Invalid token');
        }
        
        // Continue to next middleware or handler
        $response = $handler->handle($request);
        
        // Optionally modify response
        return $response->withHeader('X-Authenticated', 'true');
    }
    
    private function isValidToken(string $token): bool
    {
        // Your authentication logic
        return !empty($token);
    }
}
```

#### Applying Middleware

```php
// Single middleware on route
Router::get('/protected', [SecureController::class, 'index'], [
    'middleware' => [AuthMiddleware::class]
]);

// Multiple middleware (executed in order)
Router::get('/admin', [AdminController::class, 'index'], [
    'middleware' => [
        AuthMiddleware::class,
        AdminMiddleware::class,
        RateLimitMiddleware::class
    ]
]);

// Group middleware
Router::group(['middleware' => [AuthMiddleware::class]], function() {
    Router::get('/profile', [ProfileController::class, 'show']);
    Router::put('/profile', [ProfileController::class, 'update']);
});
```

#### Closure Middleware

```php
Router::get('/custom', [CustomController::class, 'index'], [
    'middleware' => [
        function($request, $next) {
            // Before handler
            $start = microtime(true);
            
            // Process request
            $response = $next($request);
            
            // After handler
            $duration = microtime(true) - $start;
            return $response->withHeader('X-Response-Time', $duration . 's');
        }
    ]
]);
```

#### Middleware Execution Order

```php
Router::get('/test', $handler, [
    'middleware' => [
        FirstMiddleware::class,   // Executes first (before)
        SecondMiddleware::class,  // Executes second (before)
        ThirdMiddleware::class,   // Executes third (before)
        // Handler executes here
        // ThirdMiddleware (after)
        // SecondMiddleware (after)
        // FirstMiddleware (after)
    ]
]);
```

## Configuration

### Development Configuration

```php
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
]);

// View all registered routes
echo Router::printRoutes();
```

### Production Configuration

```php
Router::configure([
    'routes_directory' => __DIR__ . '/routes',
    'cache_enabled' => true,
    'cache_directory' => __DIR__ . '/storage/cache',
    'debug_mode' => false,
]);

// Clear cache when deploying new routes
Router::clearCache();
```

### Configuration Options

```php
Router::configure([
    // Directory containing route files (default: '/')
    'routes_directory' => __DIR__ . '/routes',
    
    // Enable debug mode for detailed errors (default: false)
    'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
    
    // Enable route caching for production (default: false)
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    
    // Cache directory (default: sys_get_temp_dir())
    'cache_directory' => __DIR__ . '/storage/cache',
    
    // Custom error formatter (default: JsonErrorFormatter)
    'error_formatter' => new HtmlErrorFormatter(),
]);
```

### Custom Error Formatters

```php
use ElliePHP\Components\Routing\Core\HtmlErrorFormatter;
use ElliePHP\Components\Routing\Core\JsonErrorFormatter;

// Use HTML error pages
Router::configure([
    'error_formatter' => new HtmlErrorFormatter(),
]);

// Use JSON errors (default)
Router::configure([
    'error_formatter' => new JsonErrorFormatter(),
]);

// Create custom formatter
class CustomErrorFormatter implements ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array
    {
        return [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ];
    }
}
```

## Route Files

Organize routes in separate files:

```php
// routes/api.php
<?php

use ElliePHP\Components\Routing\Router;

Router::group(['prefix' => '/api/v1'], function() {
    require __DIR__ . '/api/users.php';
    require __DIR__ . '/api/posts.php';
});
```

```php
// routes/api/users.php
<?php

use ElliePHP\Components\Routing\Router;

Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);
```

## Debug Features

### Route Listing

```php
// Print formatted route table
echo Router::printRoutes();

/* Output:
====================================================================================================
METHOD   PATH                                     NAME                           HANDLER
====================================================================================================
GET      /users                                   get.users                      UserController@index
GET      /users/{id}                              get.users.id                   UserController@show
POST     /users                                   post.users                     UserController@store
====================================================================================================
Total routes: 3
*/

// Get routes as array
$routes = Router::getFormattedRoutes();
```

### Debug Headers

When debug mode is enabled, responses automatically include:

```
X-Debug-Time: 4.23ms
X-Debug-Routes: 15
```

### Detailed Error Messages

Debug mode provides comprehensive error information:

```json
{
  "error": "Route not found",
  "status": 404,
  "debug": {
    "exception": "ElliePHP\\Components\\Routing\\Exceptions\\RouteNotFoundException",
    "file": "/path/to/Routing.php",
    "line": 246,
    "trace": "..."
  }
}
```

### Route Inspection

```php
// Check configuration
if (Router::isDebugMode()) {
    echo "Debug mode is enabled\n";
}

if (Router::isCacheEnabled()) {
    echo "Cache is enabled\n";
}

// Get all routes
$routes = Router::getRoutes();
foreach ($routes as $route) {
    echo "{$route['method']} {$route['path']}\n";
}
```

## Caching

### Enable Caching

```php
Router::configure([
    'cache_enabled' => true,
    'cache_directory' => __DIR__ . '/storage/cache',
]);
```

### Clear Cache

```php
// Clear cache manually
Router::clearCache();

// Or delete the cache file
unlink(__DIR__ . '/storage/cache/ellie_routes.cache');
```

### Cache Behavior

- Cache is automatically disabled when `debug_mode` is `true`
- Routes are cached after first load
- Cache is loaded on subsequent requests
- Failed cache loads fall back to loading routes normally

## Testing

### Basic Testing

```php
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset router state between tests
        Router::resetInstance();
        Router::reset();
    }
    
    public function testUserRoute(): void
    {
        Router::get('/users/{id}', function($request, $params) {
            return ['user_id' => $params['id']];
        });
        
        $request = new ServerRequest('GET', '/users/123');
        $response = Router::handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('123', $body['user_id']);
    }
}
```

### Testing with Controllers

```php
public function testUserController(): void
{
    Router::get('/users', [UserController::class, 'index']);
    
    $request = new ServerRequest('GET', '/users');
    $response = Router::handle($request);
    
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('users', (string)$response->getBody());
}
```

### Testing Middleware

```php
public function testMiddleware(): void
{
    Router::get('/protected', function() {
        return ['protected' => true];
    }, [
        'middleware' => [TestMiddleware::class]
    ]);
    
    $request = new ServerRequest('GET', '/protected');
    $response = Router::handle($request);
    
    $this->assertTrue($response->hasHeader('X-Test-Middleware'));
}
```

## Advanced Usage

### Programmatic Route Registration

```php
Router::registerRoutes([
    [
        'method' => 'GET',
        'path' => '/users',
        'class' => UserController::class,
        'handler' => 'index',
        'middleware' => [AuthMiddleware::class],
        'name' => 'users.index'
    ],
    [
        'method' => 'POST',
        'path' => '/users',
        'class' => UserController::class,
        'handler' => 'store',
        'middleware' => [AuthMiddleware::class],
        'name' => 'users.store'
    ],
]);
```

### Named Routes

```php
Router::get('/users/{id}', [UserController::class, 'show'], [
    'name' => 'users.show'
]);

Router::post('/users', [UserController::class, 'store'], [
    'name' => 'users.store'
]);

// Access route names
$routes = Router::getRoutes();
foreach ($routes as $route) {
    echo "Route: {$route['name']}\n";
}
```

### Custom Route Names

```php
// Automatic naming: get.users.id
Router::get('/users/{id}', [UserController::class, 'show']);

// Custom naming
Router::get('/users/{id}', [UserController::class, 'show'], [
    'name' => 'user.profile'
]);
```

## Complete Example

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// Configure router
Router::configure([
    'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    'cache_directory' => __DIR__ . '/storage/cache',
]);

// Define routes
Router::get('/', function() {
    return ['message' => 'Welcome to the API'];
});

Router::group(['prefix' => '/api/v1'], function() {
    // Public routes
    Router::post('/auth/login', [AuthController::class, 'login']);
    Router::post('/auth/register', [AuthController::class, 'register']);
    
    // Protected routes
    Router::group(['middleware' => [AuthMiddleware::class]], function() {
        Router::get('/profile', [ProfileController::class, 'show']);
        Router::put('/profile', [ProfileController::class, 'update']);
        
        // Admin routes
        Router::group([
            'prefix' => '/admin',
            'middleware' => [AdminMiddleware::class]
        ], function() {
            Router::get('/users', [AdminController::class, 'users']);
            Router::get('/stats', [AdminController::class, 'stats']);
        });
    });
});

// Create PSR-7 request from globals
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);
$request = $creator->fromGlobals();

// Handle request
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

## Requirements

- PHP 8.4 or higher
- psr/http-server-middleware ^1.0
- psr/http-server-handler ^1.0
- nyholm/psr7 ^1.8

## Resources

- [Examples](examples/) - Working code examples

## License

MIT License
