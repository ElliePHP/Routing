<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// Example Global Middleware Classes

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Generate unique request ID
        $requestId = uniqid('req_', true);
        
        // Add to request attributes
        $request = $request->withAttribute('request_id', $requestId);
        
        // Process request
        $response = $handler->handle($request);
        
        // Add request ID to response headers
        return $response->withHeader('X-Request-ID', $requestId);
    }
}

class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        $method = $request->getMethod();
        $uri = (string)$request->getUri();
        
        echo "[LOG] {$method} {$uri} - Started\n";
        
        $response = $handler->handle($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        $status = $response->getStatusCode();
        
        echo "[LOG] {$method} {$uri} - Completed {$status} in {$duration}ms\n";
        
        return $response->withHeader('X-Response-Time', $duration . 'ms');
    }
}

class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        echo "[AUTH] Checking authentication for protected route\n";
        
        $token = $request->getHeaderLine('Authorization');
        
        if (empty($token)) {
            $factory = new Psr17Factory();
            $response = $factory->createResponse(401);
            $body = $factory->createStream(json_encode(['error' => 'Unauthorized']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body);
        }
        
        return $handler->handle($request);
    }
}

// Configure router with global middleware
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
    'global_middleware' => [
        RequestIdMiddleware::class,  // Runs first (outermost)
        LoggingMiddleware::class,    // Runs second
        CorsMiddleware::class,       // Runs third (innermost global)
    ],
]);

echo "=== Global Middleware Example ===\n\n";

// Define routes
Router::get('/', function (ServerRequestInterface $request) {
    $requestId = $request->getAttribute('request_id');
    return [
        'message' => 'Welcome! Global middleware applied.',
        'request_id' => $requestId,
    ];
});

Router::get('/users', function (ServerRequestInterface $request) {
    return [
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]
    ];
});

// Route with additional route-specific middleware
// Execution order: RequestId -> Logging -> Cors -> Auth -> Handler
Router::get('/protected', function (ServerRequestInterface $request) {
    return [
        'message' => 'Protected resource accessed',
        'request_id' => $request->getAttribute('request_id'),
    ];
}, [
    'middleware' => [AuthMiddleware::class]
]);

// Create request
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

// Test 1: Public route with global middleware
echo "Test 1: Public Route (Global Middleware Only)\n";
echo "----------------------------------------------\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

$request = $creator->fromGlobals();
$response = Router::handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Headers:\n";
echo "  X-Request-ID: " . $response->getHeaderLine('X-Request-ID') . "\n";
echo "  X-Response-Time: " . $response->getHeaderLine('X-Response-Time') . "\n";
echo "  Access-Control-Allow-Origin: " . $response->getHeaderLine('Access-Control-Allow-Origin') . "\n";
echo "Body: " . $response->getBody() . "\n\n";

// Test 2: Protected route with global + route middleware
echo "Test 2: Protected Route (Global + Route Middleware)\n";
echo "----------------------------------------------------\n";
$_SERVER['REQUEST_URI'] = '/protected';

$request = $creator->fromGlobals();
$response = Router::handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

// Test 3: Protected route with auth token
echo "Test 3: Protected Route with Auth Token\n";
echo "----------------------------------------\n";
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

$request = $creator->fromGlobals();
$response = Router::handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "=== Middleware Execution Order ===\n";
echo "Global Middleware: RequestId -> Logging -> Cors\n";
echo "Route Middleware: Auth (only on /protected)\n";
echo "Handler: Route handler executes last\n";
