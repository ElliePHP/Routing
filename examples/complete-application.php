<?php

declare(strict_types=1);

/**
 * Complete Application Example
 * 
 * This example demonstrates a full application setup with:
 * - Configuration
 * - Multiple route groups
 * - Middleware
 * - Controllers
 * - Error handling
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// ============================================================================
// Controllers
// ============================================================================

class HomeController
{
    public function index(): array
    {
        return [
            'message' => 'Welcome to the API',
            'version' => '1.0.0',
            'endpoints' => [
                'GET /' => 'This endpoint',
                'GET /api/v1/users' => 'List users',
                'GET /api/v1/posts' => 'List posts',
            ]
        ];
    }
}

class UserController
{
    public function index(): array
    {
        return [
            'users' => [
                ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
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
}

class PostController
{
    public function index(): array
    {
        return [
            'posts' => [
                ['id' => 1, 'title' => 'First Post', 'author_id' => 1],
                ['id' => 2, 'title' => 'Second Post', 'author_id' => 2],
            ]
        ];
    }

    public function show(ServerRequestInterface $request, string $id): array
    {
        return [
            'post' => [
                'id' => $id,
                'title' => 'Post ' . $id,
                'content' => 'This is the content of post ' . $id
            ]
        ];
    }
}

// ============================================================================
// Middleware
// ============================================================================

class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        echo "[LOG] {$method} {$path} - Started\n";
        
        $response = $handler->handle($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "[LOG] {$method} {$path} - Completed in {$duration}ms\n";
        
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

class ApiVersionMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $handler->handle($request)->withHeader('X-API-Version', '1.0.0');
    }
}

// ============================================================================
// Configuration
// ============================================================================

Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
]);

// ============================================================================
// Routes
// ============================================================================

// Home route
Router::get('/', [HomeController::class, 'index']);

// Health check
Router::get('/health', function () {
    return [
        'status' => 'healthy',
        'timestamp' => time()
    ];
});

// API v1 routes with global middleware
Router::group([
    'prefix' => '/api/v1',
    'middleware' => [
        LoggingMiddleware::class,
        CorsMiddleware::class,
        ApiVersionMiddleware::class
    ]
], function () {
    
    // Users resource
    Router::group(['prefix' => '/users'], function () {
        Router::get('', [UserController::class, 'index']);
        Router::get('/{id}', [UserController::class, 'show']);
    });
    
    // Posts resource
    Router::group(['prefix' => '/posts'], function () {
        Router::get('', [PostController::class, 'index']);
        Router::get('/{id}', [PostController::class, 'show']);
    });
    
    // Stats endpoint with additional middleware
    Router::get('/stats', function () {
        return [
            'total_users' => 2,
            'total_posts' => 2,
            'uptime' => '24h'
        ];
    }, [
        'middleware' => [
            function ($request, $next) {
                echo "[STATS] Generating statistics...\n";
                return $next($request);
            }
        ]
    ]);
});

// ============================================================================
// Display Routes
// ============================================================================

echo "=== Complete Application Example ===\n\n";
echo Router::printRoutes();

// ============================================================================
// Test Requests
// ============================================================================

echo "\n=== Testing Routes ===\n\n";

$testRoutes = [
    ['GET', '/'],
    ['GET', '/health'],
    ['GET', '/api/v1/users'],
    ['GET', '/api/v1/users/42'],
    ['GET', '/api/v1/posts'],
    ['GET', '/api/v1/posts/10'],
    ['GET', '/api/v1/stats'],
];

foreach ($testRoutes as [$method, $path]) {
    echo "--- {$method} {$path} ---\n";
    
    $request = new ServerRequest($method, $path);
    $response = Router::handle($request);
    
    echo "Status: {$response->getStatusCode()}\n";
    
    // Show relevant headers
    $headers = ['X-Response-Time', 'X-API-Version', 'Access-Control-Allow-Origin'];
    foreach ($headers as $header) {
        if ($response->hasHeader($header)) {
            echo "{$header}: {$response->getHeaderLine($header)}\n";
        }
    }
    
    $body = (string)$response->getBody();
    $decoded = json_decode($body, true);
    echo "Body: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";
}

// ============================================================================
// Debug Information
// ============================================================================

echo "=== Debug Information ===\n";
echo "Total routes registered: " . count(Router::getRoutes()) . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache enabled: " . (Router::isCacheEnabled() ? 'yes' : 'no') . "\n";
