<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// Example middleware: Add timing header
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

// Example middleware: Add custom header
class CustomHeaderMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Powered-By', 'ElliePHP');
    }
}

// Configure router
Router::configure(['debug_mode' => true]);

// Route with middleware - Array syntax
Router::get('/protected', function () {
    return ['message' => 'This route has middleware'];
}, [
    'middleware' => [TimingMiddleware::class, CustomHeaderMiddleware::class]
]);

// Route with middleware - Fluent syntax (alternative)
Router::get('/protected-fluent', function () {
    return ['message' => 'This route has middleware (fluent syntax)'];
})
    ->middleware([TimingMiddleware::class, CustomHeaderMiddleware::class]);

// Group with middleware - Array syntax
Router::group(['prefix' => '/api', 'middleware' => [TimingMiddleware::class]], function () {
    Router::get('/users', function () {
        return ['users' => []];
    });
    
    Router::get('/posts', function () {
        return ['posts' => []];
    }, [
        'middleware' => [CustomHeaderMiddleware::class] // Additional middleware
    ]);
});

// Group with middleware - Fluent syntax (alternative)
Router::prefix('/api-fluent')
    ->middleware([TimingMiddleware::class])
    ->group(function () {
        Router::get('/users', function () {
            return ['users' => []];
        });
        
        Router::get('/posts', function () {
            return ['posts' => []];
        })
            ->middleware([CustomHeaderMiddleware::class]); // Additional middleware
    });

// Closure middleware
Router::get('/custom', function () {
    return ['message' => 'Custom middleware'];
}, [
    'middleware' => [
        function ($request, $next) {
            echo "Before handler\n";
            $response = $next($request);
            echo "After handler\n";
            return $response->withHeader('X-Custom', 'true');
        }
    ]
]);

// Test the routes
echo "--- Testing /protected ---\n";
$request = new ServerRequest('GET', '/protected');
$response = Router::handle($request);
echo "Headers:\n";
foreach ($response->getHeaders() as $name => $values) {
    if (str_starts_with($name, 'X-')) {
        echo "  $name: " . implode(', ', $values) . "\n";
    }
}
echo "Body: " . $response->getBody() . "\n\n";

echo "--- Testing /api/posts (multiple middleware) ---\n";
$request = new ServerRequest('GET', '/api/posts');
$response = Router::handle($request);
echo "Headers:\n";
foreach ($response->getHeaders() as $name => $values) {
    if (str_starts_with($name, 'X-')) {
        echo "  $name: " . implode(', ', $values) . "\n";
    }
}
echo "Body: " . $response->getBody() . "\n\n";

echo "--- Testing /custom (closure middleware) ---\n";
$request = new ServerRequest('GET', '/custom');
$response = Router::handle($request);
echo "Headers:\n";
foreach ($response->getHeaders() as $name => $values) {
    if (str_starts_with($name, 'X-')) {
        echo "  $name: " . implode(', ', $values) . "\n";
    }
}
echo "Body: " . $response->getBody() . "\n";
