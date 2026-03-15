# ElliePHP Routing

A lightweight, high-performance PHP routing library built on FastRoute and PSR standards. ElliePHP Routing provides an expressive, fluent API for handling HTTP requests with support for middleware, dependency injection, domain routing, and route caching.

## Installation

Install via Composer:

```bash
composer require elliephp/routing
```

---

## Quick Start

### Minimal Example

Here's the absolute minimum needed to get started:

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

Router::configure()->build();

Router::get('/', function () {
    return ['status' => 'API Online'];
});

$request = ServerRequest::fromGlobals();
$response = Router::handle($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

This creates a single route that responds to GET requests at `/` with a JSON response. The router automatically converts array returns into JSON.

### Production-Ready Setup

For a production application, you'll want to enable caching, configure debugging based on environment, and potentially add global middleware:

```php
<?php

require 'vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

Router::configure()
    ->debugMode($_ENV['APP_DEBUG'] ?? false)
    ->enableCache($_ENV['APP_ENV'] === 'production')
    ->cacheDirectory(__DIR__ . '/storage/cache')
    ->routesDirectory(__DIR__ . '/routes')
    ->container($container)
    ->build();

Router::get('/', function () {
    return ['status' => 'API Online'];
});

$request = ServerRequest::fromGlobals();
$response = Router::handle($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

This setup disables debugging in production, enables route caching for better performance, and optionally loads route files from a dedicated directory.

---

## Configuration

The router uses a fluent builder pattern for configuration. All configuration must be done before defining routes.

### Complete Configuration Example

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

### Configuration Methods

**`debugMode(bool $enabled)`**  
Enables detailed error messages with full stack traces and provides access to debugging utilities like `Router::printRoutes()`. Always disable this in production to avoid exposing sensitive information.

**`enableCache(bool $enabled = true)`**  
Compiles all routes into a single cached file for optimal performance. When enabled, route changes won't take effect until the cache is cleared. Essential for production environments.

**`cacheDirectory(string $path)`**  
Specifies where cached route files should be stored. The directory must be writable by your web server. Typically `storage/cache` or similar.

**`routesDirectory(string $path)`**  
Automatically loads all PHP files from the specified directory. Useful for organizing routes into separate files (e.g., `routes/api.php`, `routes/web.php`).

**`enforceDomain(bool $enabled = true)`**  
When enabled, the router will reject requests from domains not explicitly defined in your routes or allowed domains list. Critical for multi-tenant applications.

**`allowedDomains(array $domains)`**  
Whitelist specific domains when domain enforcement is enabled. Requests to unlisted domains will return 404 responses.

**`addGlobalMiddleware(string $middlewareClass)`**  
Registers middleware that executes on every single request. Perfect for CORS headers, security headers, logging, or session handling. Can be called multiple times.

**`container(ContainerInterface $container)`**  
Provides a PSR-11 container for automatic dependency injection in controllers. Works with any PSR-11 implementation (PHP-DI, Symfony DI, Laravel Container, etc.).

**`errorFormatter(ErrorFormatterInterface $formatter)`**  
Customizes how errors are formatted and returned. By default, errors return JSON. Implement this interface to return HTML, XML, or custom formats.

**`build()`**  
Finalizes the configuration and initializes the router. Must be called before defining routes.

---

## Basic Routing

### Defining Routes

Routes map HTTP requests to callbacks or controllers. The simplest routes accept a URI pattern and a closure:

```php
Router::get('/greeting', function () {
    return ['message' => 'Hello World'];
});

Router::post('/users', function ($request) {
    return ['message' => 'User created'];
});
```

When you return an array from a route handler, it's automatically converted to a JSON response with the appropriate `Content-Type` header.

### Available HTTP Methods

ElliePHP supports all standard HTTP verbs:

```php
Router::get($uri, $callback);
Router::post($uri, $callback);
Router::put($uri, $callback);
Router::patch($uri, $callback);
Router::delete($uri, $callback);
Router::options($uri, $callback);
```

Each method corresponds to the HTTP verb it handles. Choose the appropriate method based on RESTful conventions.

### Request and Parameters

Route handlers receive the PSR-7 request object and route parameters:

```php
Router::post('/users', function ($request, $params) {
    $body = json_decode($request->getBody()->getContents(), true);
    return ['created' => true, 'data' => $body];
});
```

The `$request` parameter is a `ServerRequestInterface` instance with methods like `getBody()`, `getHeaders()`, `getQueryParams()`, etc.

### Named Routes

Named routes allow you to generate URLs or redirect to routes without hardcoding URIs. This is especially useful when route patterns change.

**Fluent Syntax:**
```php
Router::get('/user/profile', function () {
    return ['page' => 'profile'];
})->name('profile');
```

**Array Syntax:**
```php
Router::get('/user/profile', function () {
    return ['page' => 'profile'];
}, ['name' => 'profile']);
```

You can then reference the route by name instead of URI. Names should be unique across your application.

### URL Generation

Generate URLs for named routes using the global `route()` helper or the `Router::route()` method. This is the recommended way to link between different parts of your application.

**Basic Route:**
```php
Router::get('/users', function() {}, ['name' => 'users.index']);

$url = route('users.index'); // Returns "http://localhost/users"
```

**With Parameters:**
For routes with required parameters, pass them as an associative array:
```php
Router::get('/users/{id}', function() {}, ['name' => 'users.show']);

$url = route('users.show', ['id' => 123]); // Returns "http://localhost/users/123"
```

**Optional Parameters:**
Both FastRoute syntax (`[{param}]`) and Laravel syntax (`{param?}`) are supported:
```php
// FastRoute style
Router::get('/archive/[{year}]', function() {}, ['name' => 'archive']);
$url = route('archive', ['year' => 2023]); // Returns "http://localhost/archive/2023"
$url = route('archive'); // Returns "http://localhost/archive/"

// Laravel style
Router::get('/posts/{id?}', function() {}, ['name' => 'posts.show']);
$url = route('posts.show', ['id' => 123]); // Returns "http://localhost/posts/123"
$url = route('posts.show'); // Returns "http://localhost/posts/"
```

**Query String Generation:**
Any parameters that are not part of the route path are automatically appended as a query string:
```php
Router::get('/users', function() {}, ['name' => 'users.index']);

$url = route('users.index', ['page' => 1, 'sort' => 'asc']); 
// Returns "http://localhost/users?page=1&sort=asc"
```

**Domain-Aware URLs:**
If a route or group is restricted to a domain, the generated URL will automatically include that domain:
```php
Router::domain('{account}.myapp.com')->group(function () {
    Router::get('/dashboard', function() {})->name('dashboard');
});

$url = route('dashboard', ['account' => 'acme']);
// Returns "http://acme.myapp.com/dashboard"
```

If multiple domains are specified, the first one in the list is used for URL generation:
```php
Router::domains(['api.myapp.com', 'admin.myapp.com'])->group(function () {
    Router::get('/status', function() {})->name('status');
});

$url = route('status'); // Returns "http://api.myapp.com/status"
```

**Absolute vs Relative URLs:**
By default, `route()` returns absolute URLs. Pass `false` as the third parameter to get a relative path:
```php
$url = route('users.index', [], false); // Returns "/users"
```

**Error Handling:**
- `RouteNotFoundException`: Thrown if the specified route name does not exist.
- `InvalidArgumentException`: Thrown if a required route parameter is missing.

---

## Route Parameters

### Capturing URI Segments

Route parameters allow you to capture dynamic segments of the URI. Parameters are defined using curly braces and are passed to your handler:

```php
Router::get('/user/{id}', function ($request, $params) {
    return ['user_id' => $params['id']];
});

Router::get('/posts/{category}/{id}', function ($request, $params) {
    return [
        'category' => $params['category'],
        'post_id' => $params['id']
    ];
});
```

The `$params` array contains all captured parameters as key-value pairs. Parameter names must be alphanumeric.

### Optional Parameters

While not shown in the basic syntax, you can make parameters optional by providing default values in your handler logic.

### Regular Expression Constraints

Constrain parameter formats using inline regex patterns. This ensures routes only match when parameters meet specific criteria:

```php
Router::get('/user/{id:\d+}', function ($request, $params) {
    return ['user_id' => $params['id']];
});

Router::get('/posts/{slug:[a-z0-9-]+}', function ($request, $params) {
    return ['slug' => $params['slug']];
});

Router::get('/files/{path:.+}', function ($request, $params) {
    return ['file_path' => $params['path']];
});
```

**Common Patterns:**
- `{id:\d+}` - Numeric IDs only (1, 42, 9999)
- `{slug:[a-z-]+}` - Lowercase slugs (hello-world)
- `{uuid:[0-9a-f-]{36}}` - UUID format
- `{path:.+}` - Match anything including slashes (for file paths)

If a request doesn't match the constraint, the route won't execute and the router continues looking for other matches.

---

## Route Groups

Route groups allow you to apply shared attributes (prefixes, middleware, names, domains) to multiple routes without repeating them. This keeps your route definitions DRY and organized.

### Prefixes and Name Prefixes

Groups can share URI prefixes and route name prefixes:

**Fluent Syntax:**
```php
Router::prefix('/admin')
    ->name('admin.')
    ->group(function () {
        
        Router::get('/users', function () {
            return ['users' => []];
        })->name('users');
        
        Router::get('/posts', function () {
            return ['posts' => []];
        })->name('posts');
        
    });
```

This creates:
- `/admin/users` with name `admin.users`
- `/admin/posts` with name `admin.posts`

**Array Syntax:**
```php
Router::group([
    'prefix' => '/admin',
    'name' => 'admin.'
], function () {
    
    Router::get('/users', function () {
        return ['users' => []];
    })->name('users');
    
});
```

Both syntaxes achieve the same result. The fluent syntax is more readable for complex configurations.

### Nested Groups

Groups can be nested to create hierarchical route structures. Attributes merge intelligently:

```php
Router::prefix('/api')->group(function () {
    
    Router::prefix('/v1')->group(function () {
        
        Router::get('/users', [UserController::class, 'index']);
        
        Router::prefix('/admin')->group(function () {
            Router::get('/stats', [AdminController::class, 'stats']);
        });
        
    });

});
```

This creates `/api/v1/users` and `/api/v1/admin/stats`. Prefixes concatenate, middleware stacks, and name prefixes chain together.

### Why Use Groups?

Groups shine when you have many routes sharing common attributes:

```php
Router::prefix('/api/v1')
    ->middleware([ApiAuthMiddleware::class, RateLimitMiddleware::class])
    ->name('api.v1.')
    ->group(function () {
        
        Router::get('/users', [UserController::class, 'index'])->name('users');
        Router::get('/posts', [PostController::class, 'index'])->name('posts');
        Router::get('/comments', [CommentController::class, 'index'])->name('comments');
        
    });
```

Without groups, you'd need to repeat the prefix, middleware, and name prefix on each route.

---

## Domain Routing

Domain routing restricts routes to specific domains or captures subdomain parameters. Essential for multi-tenant applications or API versioning via subdomains.

### Basic Domain Restrictions

Restrict a group of routes to a specific domain:

```php
Router::domain('api.example.com')->group(function () {
    Router::get('/users', [UserController::class, 'index']);
});
```

This route only matches requests to `api.example.com/users`, not `example.com/users` or `www.example.com/users`.

### Multiple Domains

Restrict a group or route to multiple domains simultaneously using the `domains()` method:

```php
// On a group
Router::domains(['api.example.com', 'admin.example.com'])->group(function () {
    Router::get('/status', function () {
        return ['status' => 'OK'];
    });
});

// On a single route
Router::get('/special', function() {
    return 'Special content';
})->domains(['app1.com', 'app2.com']);
```

This route will match requests from any of the specified domains. For URL generation, the first domain in the list is used as the primary domain.

### Subdomain Parameters

Capture subdomain segments as route parameters for multi-tenant applications:

**Fluent Syntax:**
```php
Router::domain('{account}.myapp.com')->group(function () {
    
    Router::get('/dashboard', function ($request, $params) {
        return [
            'tenant' => $params['account'],
            'page' => 'dashboard'
        ];
    });
    
    Router::get('/user/{id}', function ($request, $params) {
        return [
            'tenant' => $params['account'],
            'user_id' => $params['id']
        ];
    });

});
```

**Array Syntax:**
```php
Router::group(['domain' => '{account}.myapp.com'], function () {
    Router::get('/dashboard', function ($request, $params) {
        return ['tenant' => $params['account']];
    });
});
```

Now `acme.myapp.com/dashboard` captures "acme" as the `account` parameter, and `widgets.myapp.com/dashboard` captures "widgets".

### Domain Enforcement

For security in multi-tenant applications, enforce that requests only come from defined domains:

```php
Router::configure()
    ->enforceDomain()
    ->allowedDomains(['app.example.com', 'api.example.com', '*.tenant.example.com'])
    ->build();
```

Any request from an undefined domain returns a 404 response. This prevents your application from responding to arbitrary domains that may point to your server.

---

## Middleware

Middleware provides a powerful mechanism for filtering and inspecting HTTP requests. ElliePHP uses PSR-15 standard middleware, making it compatible with thousands of existing middleware packages.

### How Middleware Works

Middleware sits between the incoming request and your route handler. It can:
- Authenticate users before allowing access
- Log requests and responses
- Add CORS headers
- Rate limit requests
- Transform request or response data
- Short-circuit the request (e.g., return 401 without hitting the route)

### Assigning Middleware to Routes

Apply middleware to individual routes:

**Fluent Syntax:**
```php
Router::get('/profile', function () {
    return ['user' => 'profile data'];
})->middleware([AuthMiddleware::class, VerifiedMiddleware::class]);
```

**Array Syntax:**
```php
Router::get('/profile', function () {
    return ['user' => 'profile data'];
}, ['middleware' => [AuthMiddleware::class]]);
```

Middleware executes in the order specified. In this example, `AuthMiddleware` runs first, then `VerifiedMiddleware`, then finally your route handler.

### Assigning Middleware to Groups

Apply middleware to all routes within a group:

**Fluent Syntax:**
```php
Router::middleware([AuthMiddleware::class, ThrottleMiddleware::class])
    ->prefix('/api')
    ->group(function () {
        
        Router::get('/user', function () {
            return ['user' => 'data'];
        });
        
        Router::get('/posts', function () {
            return ['posts' => []];
        });
        
    });
```

**Array Syntax:**
```php
Router::group([
    'prefix' => '/api',
    'middleware' => [AuthMiddleware::class, ThrottleMiddleware::class]
], function () {
    
    Router::get('/user', function () {
        return ['user' => 'data'];
    });
    
});
```

Every route in the group will have both middleware applied automatically.

### Global Middleware

Global middleware runs on every single request to your application, regardless of route:

```php
Router::configure()
    ->addGlobalMiddleware(CorsMiddleware::class)
    ->addGlobalMiddleware(SecurityHeadersMiddleware::class)
    ->addGlobalMiddleware(LoggingMiddleware::class)
    ->build();
```

Use global middleware for:
- CORS headers (if all routes need them)
- Security headers (Content-Security-Policy, X-Frame-Options, etc.)
- Request logging and monitoring
- Session initialization
- Request ID generation

Global middleware executes before route-specific and group middleware.

### Creating Custom Middleware

Implement the PSR-15 `MiddlewareInterface`:

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
        
        $token = $request->getHeader('Authorization')[0] ?? null;
        
        if (!$token || !$this->validateToken($token)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        return $handler->handle($request);
    }
    
    private function validateToken(string $token): bool
    {
        return true;
    }
}
```

---

## Controllers

Controllers organize related request handling logic into classes. Instead of using closures for complex logic, controllers provide better organization and reusability.

### Basic Controller Usage

Reference controllers using an array with the class name and method:

```php
use App\Controllers\UserController;

Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);
```

### String Syntax

Alternatively, use "Class@method" string syntax:

```php
Router::get('/users/{id}', 'App\Controllers\UserController@show');
```

Both syntaxes work identically. Array syntax provides better IDE support and refactoring.

### Controller Structure

A typical controller receives the request object and route parameters:

```php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function index(ServerRequestInterface $request)
    {
        return ['users' => []];
    }
    
    public function show(ServerRequestInterface $request, string $id)
    {
        return ['user' => ['id' => $id]];
    }
    
    public function store(ServerRequestInterface $request)
    {
        $data = json_decode($request->getBody()->getContents(), true);
        return ['created' => true, 'user' => $data];
    }
}
```

Route parameters are passed to the method as individual arguments after the request. The parameter order matches the URI definition.

### RESTful Controllers

Organize CRUD operations using standard method names:

```php
Router::prefix('/api')->group(function () {
    
    Router::get('/users', [UserController::class, 'index']);
    Router::post('/users', [UserController::class, 'store']);
    Router::get('/users/{id}', [UserController::class, 'show']);
    Router::put('/users/{id}', [UserController::class, 'update']);
    Router::delete('/users/{id}', [UserController::class, 'destroy']);
    
});
```

This creates a complete RESTful resource API.

---

## Dependency Injection

ElliePHP provides automatic dependency injection for controllers via PSR-11 containers. This eliminates manual instantiation and makes testing easier.

### Setting Up a Container

Provide any PSR-11 compliant container during configuration:

```php
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();
$container = $containerBuilder->build();

Router::configure()
    ->container($container)
    ->build();
```

Compatible containers include:
- PHP-DI
- Symfony DependencyInjection
- Laravel Container
- Laminas ServiceManager
- Any PSR-11 implementation

### Automatic Constructor Injection

Once configured, the router automatically resolves and injects controller dependencies:

```php
namespace App\Controllers;

use App\Repositories\UserRepository;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(
        protected UserRepository $users,
        protected LoggerInterface $logger
    ) {}

    public function show(ServerRequestInterface $request, string $id)
    {
        $this->logger->info("Fetching user", ['id' => $id]);
        
        $user = $this->users->find($id);
        
        return ['user' => $user];
    }
    
    public function store(ServerRequestInterface $request)
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $user = $this->users->create($data);
        $this->logger->info("User created", ['id' => $user->id]);
        
        return ['user' => $user];
    }
}
```

The `UserRepository` and `LoggerInterface` are resolved from the container automatically. Route parameters are still passed to methods as normal.

### Method Injection

You can also inject dependencies into controller methods:

```php
public function show(
    ServerRequestInterface $request,
    UserRepository $users,
    string $id
) {
    $user = $users->find($id);
    return ['user' => $user];
}
```

The container resolves type-hinted dependencies, while route parameters are matched by name.

### Benefits

- No manual instantiation or `new` keywords
- Easy to test with mock dependencies
- Clear declaration of dependencies
- Promotes separation of concerns
- Works with any PSR-11 container

---

## Error Handling

ElliePHP provides consistent error handling with sensible defaults that can be customized for your needs.

### Default Error Responses

The router returns JSON errors by default:

**404 Not Found:**
```json
{
    "error": "Route not found",
    "path": "/invalid/route"
}
```

**405 Method Not Allowed:**
```json
{
    "error": "Method not allowed",
    "allowed": ["GET", "POST"]
}
```

**500 Internal Server Error:**
```json
{
    "error": "Internal server error",
    "message": "Something went wrong"
}
```

In debug mode, error responses include full stack traces and additional context.

### Custom Error Formatting

Implement `ErrorFormatterInterface` to customize error responses:

```php
use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;

class HtmlErrorFormatter implements ErrorFormatterInterface 
{
    public function format(\Throwable $e, bool $debug): array
    {
        $statusCode = $e->getCode() ?: 500;
        
        $html = "<!DOCTYPE html>
<html>
<head>
    <title>Error {$statusCode}</title>
    <style>
        body { font-family: sans-serif; padding: 50px; }
        .error { background: #fee; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='error'>
        <h1>Error {$statusCode}</h1>
        <p>{$e->getMessage()}</p>";
        
        if ($debug) {
            $html .= "<pre>{$e->getTraceAsString()}</pre>";
        }
        
        $html .= "
    </div>
</body>
</html>";
        
        return [
            'body' => $html,
            'status' => $statusCode,
            'headers' => ['Content-Type' => 'text/html']
        ];
    }
}
```

Register your formatter:

```php
Router::configure()
    ->errorFormatter(new HtmlErrorFormatter())
    ->build();
```

The formatter receives the exception and debug mode flag, and returns an array with:
- `body` - The response body
- `status` - HTTP status code
- `headers` - Optional associative array of headers

### Exception Handling Best Practices

Throw exceptions in your application code and let the router handle them:

```php
Router::get('/user/{id}', function ($request, $params) {
    $user = $database->find($params['id']);
    
    if (!$user) {
        throw new NotFoundException("User not found");
    }
    
    return ['user' => $user];
});
```

Create custom exception classes for better error handling:

```php
class NotFoundException extends \Exception
{
    protected $code = 404;
}

class UnauthorizedException extends \Exception
{
    protected $code = 401;
}
```

---

## Optimization & Caching

### Route Caching

Route caching dramatically improves performance by compiling all routes into a single optimized file. This eliminates the overhead of re-parsing route definitions on every request.

**Production Configuration:**
```php
Router::configure()
    ->debugMode(false)
    ->enableCache(true)
    ->cacheDirectory(__DIR__ . '/storage/cache')
    ->build();
```

**Performance Impact:**  
Without caching: ~5-10ms to register 100 routes  
With caching: <1ms (50-100x faster)

**Important Considerations:**
- Route changes require cache clearing (delete the cache file or change filename)
- The cache directory must be writable by your web server
- Cache files are specific to your route definitions
- In development, disable caching to see changes immediately

### Cache Management

Clear the cache when deploying:

```bash
rm storage/cache/routes_*
```

Or implement a cache clear command:

```php
Router::clearCache();
```

### Optimization Tips

1. **Enable caching in production** - Always enable caching when deploying. The performance gain is substantial.

2. **Use route caching with OPcache** - Combine route caching with PHP's OPcache for maximum performance.

3. **Organize routes logically** - Use the `routesDirectory` option to split routes into files (web.php, api.php, admin.php).

4. **Avoid closures in production** - Controllers are cached more efficiently than closures. Use controllers for better performance.

5. **Use regex constraints sparingly** - While powerful, complex regex patterns add overhead. Use simple constraints when possible.

---

## Debugging

### Debug Mode

Enable debug mode in development for helpful utilities:

```php
Router::configure()
    ->debugMode(true)
    ->build();
```

Debug mode provides:
- Detailed error messages with stack traces
- Access to debugging utilities
- Route information in responses

**Never enable debug mode in production** - it exposes sensitive information.

### Route Inspection

Print all registered routes in a formatted table, including their domain constraints and middleware:

```php
echo Router::printRoutes();
```

Output:
```
==================================================================================================================================
METHOD   PATH                                NAME                      DOMAIN                         HANDLER
==================================================================================================================================
GET      /                                   root                      *                              Closure
GET      /users                              users.index               api.example.com                UserController@index
         └─ Middleware: AuthMiddleware, LoggingMiddleware
GET      /users/{id}                         users.show                *                              UserController@show
POST     /users                              users.store               *                              UserController@store
==================================================================================================================================
Total routes: 4
```

Programmatically access route information:

```php
$routes = Router::getRoutes();

foreach ($routes as $route) {
    echo "{$route['method']} {$route['path']}\n";
}
```

### Request Debugging

Log all requests using global middleware:

```php
class DebugMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        
        error_log(sprintf(
            "[%s] %s %s",
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getUri()->getPath()
        ));
        
        return $handler->handle($request);
    }
}

Router::configure()
    ->addGlobalMiddleware(DebugMiddleware::class)
    ->build();
```

---

## Best Practices

### Route Organization

Split routes into logical files:

```
routes/
├── web.php      # Public web routes
├── api.php      # API routes
├── admin.php    # Admin panel routes
└── webhooks.php # Webhook handlers
```

Load them automatically:

```php
Router::configure()
    ->routesDirectory(__DIR__ . '/routes')
    ->build();
```

### Naming Conventions

Use consistent naming for routes:

```php
Router::prefix('/api')->name('api.')->group(function () {
    
    Router::get('/users', [UserController::class, 'index'])->name('users.index');
    Router::post('/users', [UserController::class, 'store'])->name('users.store');
    Router::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Router::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Router::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    
});
```

This creates RESTful route names: `api.users.index`, `api.users.store`, etc.

### Security

1. **Always enforce HTTPS in production** using middleware
2. **Use domain enforcement** for multi-tenant apps
3. **Disable debug mode** in production
4. **Validate and sanitize** all input data
5. **Use authentication middleware** for protected routes
6. **Implement rate limiting** for public APIs

### Performance

1. **Enable caching** in production
2. **Use controllers** instead of closures
3. **Minimize middleware** on frequently accessed routes
4. **Use regex constraints** only when necessary
5. **Combine with OPcache** for maximum performance

### Testing

Routes are easy to test with PSR-7 request objects:

```php
use Nyholm\Psr7\ServerRequest;

$request = new ServerRequest('GET', '/users/123');
$response = Router::handle($request);

assert($response->getStatusCode() === 200);
```