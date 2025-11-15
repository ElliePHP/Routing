# Quick Start: Global Middleware

## 1-Minute Setup

```php
use ElliePHP\Components\Routing\Router;

// Configure global middleware
Router::configure([
    'global_middleware' => [
        RequestIdMiddleware::class,
        LoggingMiddleware::class,
        CorsMiddleware::class,
    ],
]);

// All routes now have these middleware automatically!
Router::get('/users', [UserController::class, 'index']);
Router::get('/posts', [PostController::class, 'index']);
```

## What is Global Middleware?

Global middleware runs on **every single route** automatically. Perfect for:

- ✅ Request ID tracking
- ✅ Logging all requests
- ✅ CORS headers
- ✅ Security headers
- ✅ Rate limiting
- ✅ Request/response transformation

## Execution Order

```
Request → Global MW 1 → Global MW 2 → Route MW → Handler → Route MW → Global MW 2 → Global MW 1 → Response
```

## Example Middleware

### Request ID Middleware

```php
class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestId = uniqid('req_', true);
        $request = $request->withAttribute('request_id', $requestId);
        $response = $handler->handle($request);
        return $response->withHeader('X-Request-ID', $requestId);
    }
}
```

### Logging Middleware

```php
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        error_log(sprintf(
            '[%s] %s - %d (%sms)',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $duration
        ));
        
        return $response->withHeader('X-Response-Time', $duration . 'ms');
    }
}
```

### CORS Middleware

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($request->getMethod() === 'OPTIONS') {
            $factory = new Psr17Factory();
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

## Framework Integration

```php
final class HttpApplication
{
    private function globalMiddlewares(): array
    {
        return [
            RequestIdMiddleware::class,
            LoggingMiddleware::class,
            CorsMiddleware::class,
        ];
    }
    
    public function boot(): void
    {
        Router::configure([
            'debug_mode' => false,
            'cache_enabled' => true,
            'global_middleware' => $this->globalMiddlewares(),
        ]);
        
        $request = ServerRequestFactory::fromGlobals();
        $response = Router::handle($request);
        
        // Emit response...
    }
}
```

## Combining with Route Middleware

```php
// Global middleware (all routes)
Router::configure([
    'global_middleware' => [
        RequestIdMiddleware::class,
        LoggingMiddleware::class,
    ],
]);

// Route-specific middleware (only this route)
Router::get('/admin', [AdminController::class, 'index'], [
    'middleware' => [
        AuthMiddleware::class,
        AdminMiddleware::class,
    ]
]);

// Execution order for /admin:
// 1. RequestIdMiddleware
// 2. LoggingMiddleware
// 3. AuthMiddleware
// 4. AdminMiddleware
// 5. Handler
```

## Best Practices

### ✅ DO

- Keep global middleware lightweight and fast
- Use for cross-cutting concerns (logging, CORS, etc.)
- Order middleware logically (request ID first, then logging)
- Use route middleware for specific needs (auth, admin)

### ❌ DON'T

- Don't put slow operations in global middleware (DB queries, API calls)
- Don't put route-specific logic in global middleware
- Don't forget that global middleware runs on EVERY request

## Testing

```php
public function testGlobalMiddlewareApplied(): void
{
    Router::resetInstance();
    Router::configure([
        'global_middleware' => [RequestIdMiddleware::class],
    ]);
    
    Router::get('/test', fn() => ['message' => 'test']);
    
    $request = new ServerRequest('GET', '/test');
    $response = Router::handle($request);
    
    $this->assertTrue($response->hasHeader('X-Request-ID'));
}
```

## Performance

Global middleware has **zero additional overhead** compared to route-specific middleware:

- Applied in a single pass
- Cached along with routes
- Efficient PSR-15 stack execution

## More Information

- Full documentation: [README.md](README.md#global-middleware)
- Complete guide: [GLOBAL-MIDDLEWARE-GUIDE.md](GLOBAL-MIDDLEWARE-GUIDE.md)
- Examples: [example-global-middleware.php](example-global-middleware.php)
