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
- **Domain Routing**: Support for subdomain and multi-tenant routing with domain parameters
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

### Domain Routing

Domain routing allows you to create routes that only respond to specific domains or subdomains. This is perfect for multi-tenant applications, API subdomains, or separating admin panels.

#### Basic Domain Constraints

```php
// Main website routes
Router::get('/', function() {
    return ['message' => 'Welcome to example.com'];
}, ['domain' => 'example.com']);

Router::get('/about', function() {
    return ['page' => 'about'];
}, ['domain' => 'example.com']);

// API subdomain routes
Router::get('/users', [UserController::class, 'index'], [
    'domain' => 'api.example.com'
]);

Router::post('/users', [UserController::class, 'store'], [
    'domain' => 'api.example.com'
]);

// Admin subdomain routes
Router::get('/dashboard', [AdminController::class, 'dashboard'], [
    'domain' => 'admin.example.com'
]);

Router::get('/users', [AdminController::class, 'users'], [
    'domain' => 'admin.example.com'
]);
```

#### Domain Groups

Group multiple routes under the same domain to keep your code organized:

```php
// API subdomain with all endpoints
Router::group(['domain' => 'api.example.com'], function() {
    Router::get('/users', [UserController::class, 'index']);
    Router::post('/users', [UserController::class, 'store']);
    Router::get('/posts', [PostController::class, 'index']);
    Router::get('/comments', [CommentController::class, 'index']);
});

// API with versioning
Router::group(['domain' => 'api.example.com', 'prefix' => '/v1'], function() {
    Router::get('/users', [UserController::class, 'index']);
    Router::get('/posts', [PostController::class, 'index']);
    // Accessible at: http://api.example.com/v1/users
});

Router::group(['domain' => 'api.example.com', 'prefix' => '/v2'], function() {
    Router::get('/users', [UserControllerV2::class, 'index']);
    Router::get('/posts', [PostControllerV2::class, 'index']);
    // Accessible at: http://api.example.com/v2/users
});

// Admin panel with authentication
Router::group([
    'domain' => 'admin.example.com',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function() {
    Router::get('/dashboard', [AdminController::class, 'dashboard']);
    Router::get('/users', [AdminController::class, 'users']);
    Router::get('/settings', [AdminController::class, 'settings']);
    Router::get('/reports', [AdminController::class, 'reports']);
});
```

#### Domain Parameters (Multi-Tenant SaaS)

Extract subdomain parts as parameters for multi-tenant applications:

```php
// Basic tenant routing
Router::get('/dashboard', function($request, $params) {
    $tenant = $params['tenant'];
    
    // Load tenant-specific data
    $tenantData = Database::getTenant($tenant);
    
    return [
        'tenant' => $tenant,
        'company' => $tenantData['company_name'],
        'message' => 'Welcome to your dashboard'
    ];
}, ['domain' => '{tenant}.example.com']);

// Access: http://acme.example.com/dashboard
// Returns: {"tenant":"acme","company":"Acme Corp","message":"Welcome to your dashboard"}

// Access: http://widgets.example.com/dashboard
// Returns: {"tenant":"widgets","company":"Widgets Inc","message":"Welcome to your dashboard"}

// Combine domain and path parameters
Router::get('/users/{id}', function($request, $params) {
    $tenant = $params['tenant'];
    $userId = $params['id'];
    
    // Load user from tenant database
    $user = Database::getTenantUser($tenant, $userId);
    
    return [
        'tenant' => $tenant,
        'user' => $user
    ];
}, ['domain' => '{tenant}.example.com']);

// Access: http://acme.example.com/users/42
// Returns: {"tenant":"acme","user":{"id":42,"name":"John Doe"}}

// Real-world example: Tenant-specific API
Router::get('/api/projects', function($request, $params) {
    $tenant = $params['tenant'];
    return [
        'tenant' => $tenant,
        'projects' => ProjectService::getForTenant($tenant)
    ];
}, ['domain' => '{tenant}.example.com']);

// Access: http://acme.example.com/api/projects
// Access: http://widgets.example.com/api/projects
```

#### Multi-Tenant Application Example

Complete multi-tenant SaaS application structure:

```php
// Configure domain enforcement
Router::configure([
    'enforce_domain' => true,
    'allowed_domains' => [
        'myapp.com',              // Main marketing site
        'app.myapp.com',          // Main app domain
        '{tenant}.myapp.com',     // Tenant subdomains
    ],
]);

// Main marketing site
Router::group(['domain' => 'myapp.com'], function() {
    Router::get('/', [MarketingController::class, 'home']);
    Router::get('/pricing', [MarketingController::class, 'pricing']);
    Router::get('/signup', [MarketingController::class, 'signup']);
});

// Tenant application routes
Router::group(['domain' => '{tenant}.myapp.com'], function() {
    // Public routes
    Router::get('/login', [AuthController::class, 'showLogin']);
    Router::post('/login', [AuthController::class, 'login']);
    
    // Protected tenant routes
    Router::group(['middleware' => [AuthMiddleware::class]], function() {
        Router::get('/dashboard', function($request, $params) {
            $tenant = $params['tenant'];
            return [
                'tenant' => $tenant,
                'stats' => DashboardService::getStats($tenant)
            ];
        });
        
        Router::get('/projects', [ProjectController::class, 'index']);
        Router::post('/projects', [ProjectController::class, 'store']);
        Router::get('/projects/{id}', [ProjectController::class, 'show']);
        
        Router::get('/team', [TeamController::class, 'index']);
        Router::post('/team/invite', [TeamController::class, 'invite']);
        
        Router::get('/settings', [SettingsController::class, 'show']);
        Router::put('/settings', [SettingsController::class, 'update']);
    });
});

// Examples:
// http://myapp.com/ - Marketing site
// http://acme.myapp.com/dashboard - Acme's dashboard
// http://widgets.myapp.com/projects - Widgets Inc's projects
// http://startup.myapp.com/team - Startup's team page
```

#### Multiple Domain Parameters

Extract multiple parts from the domain for advanced routing:

```php
// Regional routing
Router::get('/api/data', function($request, $params) {
    $region = $params['region'];
    $service = $params['service'];
    
    return [
        'service' => $service,
        'region' => $region,
        'endpoint' => "https://{$service}.{$region}.example.com",
        'data' => RegionalService::getData($region, $service)
    ];
}, ['domain' => '{service}.{region}.example.com']);

// Access: http://api.us-east.example.com/api/data
// Returns: {"service":"api","region":"us-east","endpoint":"https://api.us-east.example.com","data":[...]}

// Access: http://cdn.eu-west.example.com/api/data
// Returns: {"service":"cdn","region":"eu-west","endpoint":"https://cdn.eu-west.example.com","data":[...]}

// Multi-tenant with environment
Router::get('/status', function($request, $params) {
    return [
        'tenant' => $params['tenant'],
        'environment' => $params['env'],
        'status' => 'operational'
    ];
}, ['domain' => '{tenant}.{env}.example.com']);

// Access: http://acme.staging.example.com/status
// Returns: {"tenant":"acme","environment":"staging","status":"operational"}

// Access: http://acme.production.example.com/status
// Returns: {"tenant":"acme","environment":"production","status":"operational"}
```

#### Domain Configuration

```php
Router::configure([
    // Enforce domain whitelist (reject unlisted domains with 403)
    'enforce_domain' => true,
    
    // Allowed domains (supports patterns with parameters)
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        'admin.example.com',
        '{tenant}.example.com',
        '{app}.{region}.example.com'
    ],
]);
```

#### Routes Without Domain Constraints

Routes without domain constraints work on any domain:

```php
// Health check endpoint - works on all domains
Router::get('/health', function() {
    return ['status' => 'ok', 'timestamp' => time()];
});

// Metrics endpoint - accessible from any domain
Router::get('/metrics', function() {
    return [
        'requests' => MetricsService::getRequestCount(),
        'uptime' => MetricsService::getUptime()
    ];
});

// This route works on:
// - http://example.com/health
// - http://api.example.com/health
// - http://admin.example.com/health
// - http://tenant1.example.com/health
// - http://any-subdomain.example.com/health
```

#### Real-World Complete Example

```php
<?php

use ElliePHP\Components\Routing\Router;

// Configure domains
Router::configure([
    'enforce_domain' => true,
    'allowed_domains' => [
        'myapp.com',
        'api.myapp.com',
        'admin.myapp.com',
        '{tenant}.myapp.com'
    ],
]);

// Marketing site (myapp.com)
Router::group(['domain' => 'myapp.com'], function() {
    Router::get('/', [HomeController::class, 'index']);
    Router::get('/features', [HomeController::class, 'features']);
    Router::get('/pricing', [HomeController::class, 'pricing']);
    Router::post('/signup', [SignupController::class, 'register']);
});

// Public API (api.myapp.com)
Router::group(['domain' => 'api.myapp.com', 'prefix' => '/v1'], function() {
    // Public endpoints
    Router::post('/auth/login', [ApiAuthController::class, 'login']);
    Router::post('/auth/register', [ApiAuthController::class, 'register']);
    
    // Protected API endpoints
    Router::group(['middleware' => [ApiAuthMiddleware::class]], function() {
        Router::get('/users', [ApiUserController::class, 'index']);
        Router::get('/users/{id}', [ApiUserController::class, 'show']);
        Router::post('/users', [ApiUserController::class, 'store']);
    });
});

// Admin panel (admin.myapp.com)
Router::group([
    'domain' => 'admin.myapp.com',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class]
], function() {
    Router::get('/dashboard', [AdminDashboardController::class, 'index']);
    Router::get('/tenants', [AdminTenantController::class, 'index']);
    Router::get('/tenants/{id}', [AdminTenantController::class, 'show']);
    Router::post('/tenants', [AdminTenantController::class, 'create']);
    Router::delete('/tenants/{id}', [AdminTenantController::class, 'delete']);
});

// Multi-tenant application ({tenant}.myapp.com)
Router::group(['domain' => '{tenant}.myapp.com'], function() {
    // Public tenant pages
    Router::get('/login', [TenantAuthController::class, 'showLogin']);
    Router::post('/login', [TenantAuthController::class, 'login']);
    
    // Protected tenant routes
    Router::group(['middleware' => [TenantAuthMiddleware::class]], function() {
        Router::get('/dashboard', function($request, $params) {
            $tenant = $params['tenant'];
            return view('dashboard', [
                'tenant' => TenantService::load($tenant),
                'stats' => DashboardService::getStats($tenant)
            ]);
        });
        
        Router::get('/projects', [TenantProjectController::class, 'index']);
        Router::post('/projects', [TenantProjectController::class, 'store']);
        Router::get('/projects/{id}', [TenantProjectController::class, 'show']);
        Router::put('/projects/{id}', [TenantProjectController::class, 'update']);
        Router::delete('/projects/{id}', [TenantProjectController::class, 'destroy']);
    });
});

// Health check - works on all domains
Router::get('/health', function() {
    return ['status' => 'ok'];
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
    
    // Enforce domain whitelist (default: false)
    'enforce_domain' => false,
    
    // Allowed domains (supports domain parameters like {tenant}.example.com)
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        '{tenant}.example.com'
    ],
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
