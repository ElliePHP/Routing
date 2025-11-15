# Global Middleware Guide

## Overview

Global middleware allows you to apply middleware to **all routes** automatically without having to specify them on each individual route. This is perfect for cross-cutting concerns like logging, CORS, request IDs, and authentication.

## Configuration

Add global middleware to your router configuration:

```php
use ElliePHP\Components\Routing\Router;

Router::configure([
    'global_middleware' => [
        RequestIdMiddleware::class,
        LoggingMiddleware::class,
        CorsMiddleware::class,
    ],
]);
```

## Execution Order

Global middleware executes **before** route-specific middleware:

```php
Router::configure([
    'global_middleware' => [
        GlobalMiddleware1::class,  // Executes first
        GlobalMiddleware2::class,  // Executes second
    ],
]);

Router::get('/test', $handler, [
    'middleware' => [
        RouteMiddleware1::class,   // Executes third
        RouteMiddleware2::class,   // Executes fourth
    ]
]);

// Full execution order:
// 1. GlobalMiddleware1 (before)
// 2. GlobalMiddleware2 (before)
// 3. RouteMiddleware1 (before)
// 4. RouteMiddleware2 (before)
// 5. Handler executes
// 6. RouteMiddleware2 (after)
// 7. RouteMiddleware1 (after)
// 8. GlobalMiddleware2 (after)
// 9. GlobalMiddleware1 (after)
```

## Common Use Cases

### 1. Request ID Tracking

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

### 2. Logging

```php
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        $method = $request->getMethod();
        $uri = (string)$request->getUri();
        
        error_log("[{$method}] {$uri} - Started");
        
        $response = $handler->handle($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        $status = $response->getStatusCode();
        
        error_log("[{$method}] {$uri} - {$status} ({$duration}ms)");
        
        return $response->withHeader('X-Response-Time', $duration . 'ms');
    }
}
```

### 3. CORS Headers

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Handle preflight
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

### 4. Security Headers

```php
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
```

## Framework Integration

### HttpApplication Pattern

```php
final class HttpApplication
{
    public const string VERSION = '1.0.0';
    
    private static ?self $instance = null;
    private static bool $routerConfigured = false;
    
    public static function init(): self
    {
        return self::$instance ??= new self();
    }
    
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
        $request = ServerRequestFactory::fromGlobals();
        
        if (!self::$routerConfigured) {
            Router::configure([
                'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
                'cache_enabled' => $_ENV['APP_ENV'] === 'production',
                'error_formatter' => new HtmlErrorFormatter(),
                'routes_directory' => __DIR__ . '/routes',
                'global_middleware' => $this->globalMiddlewares(),
            ]);
            
            self::$routerConfigured = true;
        }
        
        $response = Router::handle($request);
        
        // Emit response...
    }
}
```

## Benefits

1. **DRY Principle**: Define middleware once, apply everywhere
2. **Consistency**: Ensures all routes have the same base middleware
3. **Maintainability**: Easy to add/remove middleware for all routes
4. **Performance**: Middleware is applied efficiently in a single pass
5. **Flexibility**: Can still add route-specific middleware on top

## Best Practices

### 1. Order Matters

Place middleware in the order they should execute:

```php
'global_middleware' => [
    RequestIdMiddleware::class,      // First: Generate request ID
    LoggingMiddleware::class,        // Second: Log with request ID
    CorsMiddleware::class,           // Third: Add CORS headers
    SecurityHeadersMiddleware::class, // Fourth: Add security headers
]
```

### 2. Keep Global Middleware Lightweight

Global middleware runs on **every request**, so keep it fast:

```php
// ✅ Good: Fast operations
- Adding headers
- Logging
- Request ID generation

// ❌ Avoid: Slow operations
- Database queries
- External API calls
- Heavy computations
```

### 3. Use Route Middleware for Specific Needs

Don't put everything in global middleware:

```php
// Global: Applies to all routes
'global_middleware' => [
    RequestIdMiddleware::class,
    LoggingMiddleware::class,
]

// Route-specific: Only where needed
Router::get('/admin', $handler, [
    'middleware' => [
        AuthMiddleware::class,
        AdminMiddleware::class,
    ]
]);
```

### 4. Environment-Specific Middleware

Adjust middleware based on environment:

```php
private function globalMiddlewares(): array
{
    $middleware = [
        RequestIdMiddleware::class,
        CorsMiddleware::class,
    ];
    
    // Add debug middleware in development
    if ($_ENV['APP_ENV'] === 'development') {
        $middleware[] = DebugMiddleware::class;
    }
    
    // Add rate limiting in production
    if ($_ENV['APP_ENV'] === 'production') {
        $middleware[] = RateLimitMiddleware::class;
    }
    
    return $middleware;
}
```

## Testing

Test that global middleware is applied:

```php
public function testGlobalMiddlewareApplied(): void
{
    Router::resetInstance();
    Router::configure([
        'global_middleware' => [
            RequestIdMiddleware::class,
        ],
    ]);
    
    Router::get('/test', fn() => ['message' => 'test']);
    
    $request = new ServerRequest('GET', '/test');
    $response = Router::handle($request);
    
    // Assert global middleware was applied
    $this->assertTrue($response->hasHeader('X-Request-ID'));
}
```

## Performance Impact

Global middleware has minimal performance impact:

- Middleware is applied in a single pass
- No additional overhead compared to route-specific middleware
- Cached along with routes when caching is enabled
- Efficient PSR-15 middleware stack execution

## Migration Guide

### Before (Route-Specific Middleware)

```php
Router::get('/users', $handler, [
    'middleware' => [LoggingMiddleware::class, CorsMiddleware::class]
]);

Router::get('/posts', $handler, [
    'middleware' => [LoggingMiddleware::class, CorsMiddleware::class]
]);

Router::get('/comments', $handler, [
    'middleware' => [LoggingMiddleware::class, CorsMiddleware::class]
]);
```

### After (Global Middleware)

```php
Router::configure([
    'global_middleware' => [
        LoggingMiddleware::class,
        CorsMiddleware::class,
    ],
]);

Router::get('/users', $handler);
Router::get('/posts', $handler);
Router::get('/comments', $handler);
```

Much cleaner and more maintainable!
