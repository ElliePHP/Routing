# ElliePHP Routing Component

A minimal, fast routing component for ElliePHP API framework based on FastRoute and PSR-7/PSR-15 standards.

## Installation

```bash
composer require elliephp/routing
```

## Key Features

- Fast routing built on nikic/fast-route
- Multiple caching layers for high-performance applications
- PSR-7 and PSR-15 compliant
- Support for closures, controllers, and callable handlers
- PSR-15 middleware with proper stack execution
- Route groups with shared prefixes and middleware
- Domain routing for multi-tenant applications
- Production-ready route caching
- PHP 8.4+ with strict types

## Breaking Changes in v2.0

The error handling architecture has changed from internal handling to middleware-based handling for better PSR-15 compliance.

**Before (v1.x):**
```php
$router = new Routing(...);
$response = $router->handle($request); // Router caught errors internally
```

**After (v2.0):**
```php
$router = new Routing(...);
$errorMiddleware = new ErrorMiddleware($responseFactory, $streamFactory, $debugMode);
$app = new MiddlewareHandler($errorMiddleware, $router);
$response = $app->handle($request); // Middleware catches errors
```

**Action Required:** Wrap the router with `ErrorMiddleware` or create your own error handler to catch exceptions.

## Quick Start

```php
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure the router
Router::configure([
    'debug_mode' => true,
]);

// Define routes
Router::get('/', function() {
    return ['message' => 'Hello World'];
});

// Handle request
$request = new ServerRequest('GET', '/');
$response = Router::handle($request);

echo $response->getBody(); // {"message":"Hello World"}
```

## Basic Usage

### Defining Routes

```php
// Simple routes
Router::get('/users', function() {
    return ['users' => []];
});

Router::post('/users', function($request) {
    return ['created' => true];
});

// All HTTP methods
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::patch('/users/{id}', [UserController::class, 'patch']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);
```

### Route Parameters

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
```

### Controllers

```php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function index(ServerRequestInterface $request): array
    {
        return ['users' => []];
    }
    
    public function show(ServerRequestInterface $request, string $id): array
    {
        return ['user' => ['id' => $id]];
    }
    
    public function store(ServerRequestInterface $request): array
    {
        $body = json_decode((string)$request->getBody(), true);
        return ['message' => 'User created', 'user' => $body];
    }
}

// Register controller routes
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
```

## Fluent API (Method Chaining)

The router supports method chaining for more readable configuration.

### Single Routes

```php
// Fluent syntax
Router::get('/users', [UserController::class, 'index'])
    ->middleware([AuthMiddleware::class])
    ->name('users.index')
    ->domain('api.example.com');

// Equivalent array syntax (still supported)
Router::get('/users', [UserController::class, 'index'], [
    'middleware' => [AuthMiddleware::class],
    'name' => 'users.index',
    'domain' => 'api.example.com'
]);
```

### Route Groups

```php
// Start with any configuration method
Router::prefix('/api/v1')
    ->middleware([ApiMiddleware::class])
    ->domain('api.example.com')
    ->group(function() {
        Router::get('/users', [UserController::class, 'index']);
        Router::post('/users', [UserController::class, 'store']);
    });

// Nested groups
Router::prefix('/api')
    ->group(function() {
        Router::prefix('/v1')
            ->middleware([ApiMiddleware::class])
            ->group(function() {
                Router::get('/users', [UserController::class, 'index']);
            });
    });
```

## Route Groups

```php
// Basic groups
Router::group(['prefix' => '/api'], function() {
    Router::get('/users', [UserController::class, 'index']);
    Router::post('/users', [UserController::class, 'store']);
});
// Routes: /api/users

// Groups with middleware
Router::group(['middleware' => [AuthMiddleware::class]], function() {
    Router::get('/profile', [ProfileController::class, 'show']);
    Router::put('/profile', [ProfileController::class, 'update']);
});

// Groups with names
Router::group(['name' => 'api'], function() {
    Router::group(['name' => 'users'], function() {
        Router::get('/', [UserController::class, 'index'], ['name' => 'index']);
        // Full name: api.users.index
    });
});
```

## Domain Routing

### Basic Domain Constraints

```php
// Main website routes
Router::get('/', function() {
    return ['message' => 'Welcome'];
}, ['domain' => 'example.com']);

// API subdomain routes
Router::get('/users', [UserController::class, 'index'], [
    'domain' => 'api.example.com'
]);

// Admin subdomain routes
Router::get('/dashboard', [AdminController::class, 'dashboard'], [
    'domain' => 'admin.example.com'
]);
```

### Domain Groups

```php
// API subdomain with all endpoints
Router::group(['domain' => 'api.example.com'], function() {
    Router::get('/users', [UserController::class, 'index']);
    Router::post('/users', [UserController::class, 'store']);
});

// Admin panel with authentication
Router::group([
    'domain' => 'admin.example.com',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function() {
    Router::get('/dashboard', [AdminController::class, 'dashboard']);
    Router::get('/users', [AdminController::class, 'users']);
});
```

### Domain Parameters (Multi-Tenant)

```php
// Basic tenant routing
Router::get('/dashboard', function($request, $params) {
    $tenant = $params['tenant'];
    return ['tenant' => $tenant, 'message' => 'Welcome'];
}, ['domain' => '{tenant}.example.com']);

// Combine domain and path parameters
Router::get('/users/{id}', function($request, $params) {
    return [
        'tenant' => $params['tenant'],
        'user_id' => $params['id']
    ];
}, ['domain' => '{tenant}.example.com']);
```

### Multi-Tenant Application

```php
Router::configure([
    'enforce_domain' => true,
    'allowed_domains' => [
        'myapp.com',
        'app.myapp.com',
        '{tenant}.myapp.com',
    ],
]);

// Main marketing site
Router::group(['domain' => 'myapp.com'], function() {
    Router::get('/', [MarketingController::class, 'home']);
});

// Tenant application routes
Router::group(['domain' => '{tenant}.myapp.com'], function() {
    Router::get('/login', [AuthController::class, 'showLogin']);
    
    Router::group(['middleware' => [AuthMiddleware::class]], function() {
        Router::get('/dashboard', function($request, $params) {
            return ['tenant' => $params['tenant']];
        });
    });
});
```

## Middleware

### Creating Middleware

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
        $token = $request->getHeaderLine('Authorization');
        
        if (!$this->isValidToken($token)) {
            throw new UnauthorizedException('Invalid token');
        }
        
        $response = $handler->handle($request);
        return $response->withHeader('X-Authenticated', 'true');
    }
}
```

### Applying Middleware

```php
// Single middleware
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
});
```

### Global Middleware

```php
Router::configure([
    'global_middleware' => [
        RequestIdMiddleware::class,
        LoggingMiddleware::class,
        CorsMiddleware::class,
    ],
]);

// All routes automatically have global middleware applied
Router::get('/users', [UserController::class, 'index']);
```

## Dependency Injection (PSR-11)

```php
// Configure router with PSR-11 container
Router::configure([
    'container' => $container,
]);

// Controller with dependencies
class UserController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function show(ServerRequestInterface $request, array $params): array
    {
        $user = $this->userRepository->findById((int) $params['id']);
        return ['user' => $user];
    }
}

// Register in container
$container->set(UserRepository::class, fn() => new UserRepository());
$container->set(UserController::class, fn($c) => 
    new UserController($c->get(UserRepository::class))
);

// Router will resolve from container
Router::get('/users/{id}', [UserController::class, 'show']);
```

## Configuration

```php
Router::configure([
    // Directory containing route files
    'routes_directory' => __DIR__ . '/routes',
    
    // Enable debug mode for detailed errors
    'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
    
    // Enable route caching for production
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    
    // Cache directory
    'cache_directory' => __DIR__ . '/storage/cache',
    
    // Enforce domain whitelist
    'enforce_domain' => false,
    
    // Allowed domains (supports parameters)
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        '{tenant}.example.com'
    ],
    
    // Global middleware for all routes
    'global_middleware' => [
        RequestIdMiddleware::class,
        LoggingMiddleware::class,
    ],
    
    // PSR-11 container for dependency injection
    'container' => $container,
]);
```

## Performance and Caching

Enable caching for production:

```php
Router::configure([
    'cache_enabled' => true,
    'cache_directory' => __DIR__ . '/storage/cache',
]);

// Clear cache after deploying new routes
Router::clearCache();
```

Performance improvements with caching:

- Route loading: 50-100x faster
- Dispatcher build: 40-80x faster
- Reflection operations: 50x faster
- Domain matching: 20x faster

## Debug Features

```php
// Enable debug mode
Router::configure(['debug_mode' => true]);

// Print formatted route table
echo Router::printRoutes();

// Get routes as array
$routes = Router::getFormattedRoutes();

// Check configuration
if (Router::isDebugMode()) {
    echo "Debug mode is enabled\n";
}
```

## Complete Example

```php
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
    Router::post('/auth/login', [AuthController::class, 'login']);
    
    Router::group(['middleware' => [AuthMiddleware::class]], function() {
        Router::get('/profile', [ProfileController::class, 'show']);
        
        Router::group([
            'prefix' => '/admin',
            'middleware' => [AdminMiddleware::class]
        ], function() {
            Router::get('/users', [AdminController::class, 'users']);
        });
    });
});

// Create PSR-7 request and handle
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
$request = $creator->fromGlobals();

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

## License

MIT License