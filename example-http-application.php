<?php

declare(strict_types=1);

/**
 * Example: HttpApplication with Global Middleware
 * 
 * This demonstrates how to use global middleware in a framework kernel pattern
 * similar to your ElliePHP Framework setup.
 */

require_once __DIR__ . '/vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use ElliePHP\Components\Routing\Core\HtmlErrorFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// Example Middleware Classes (similar to your application)

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestId = uniqid('req_', true);
        $request = $request->withAttribute('request_id', $requestId);
        return $handler->handle($request)->withHeader('X-Request-ID', $requestId);
    }
}

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
            '[%s] %s %s - %d (%sms)',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $duration
        ));
        
        return $response->withHeader('X-Response-Time', $duration . 'ms');
    }
}

class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $factory = new Psr17Factory();
            return $factory->createResponse(200)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Max-Age', '86400');
        }
        
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}

// HttpApplication Kernel (similar to your framework)

final class HttpApplication
{
    public const string VERSION = '1.0.0';
    
    private static ?self $instance = null;
    private static bool $routerConfigured = false;
    
    public static function init(): self
    {
        return self::$instance ??= new self();
    }
    
    /**
     * Define global middleware that applies to all routes
     */
    private function globalMiddlewares(): array
    {
        return [
            RequestIdMiddleware::class,
            LoggingMiddleware::class,
            CorsMiddleware::class,
        ];
    }
    
    /**
     * Boot the application and handle the request
     */
    public function boot(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            new Psr17Factory(),
            new Psr17Factory(),
            new Psr17Factory(),
            new Psr17Factory()
        );
        
        // Configure router once
        if (!self::$routerConfigured) {
            Router::configure([
                'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
                'cache_enabled' => $_ENV['APP_ENV'] === 'production',
                'cache_directory' => __DIR__ . '/storage/cache',
                'error_formatter' => new HtmlErrorFormatter(),
                'routes_directory' => __DIR__ . '/routes',
                'global_middleware' => $this->globalMiddlewares(),
            ]);
            
            self::$routerConfigured = true;
        }
        
        // Load routes (in real app, these would be in separate files)
        $this->loadRoutes();
        
        // Handle request
        $response = Router::handle($request);
        
        // Emit response
        $this->emitResponse($response);
    }
    
    /**
     * Load application routes
     */
    private function loadRoutes(): void
    {
        // API routes
        Router::group(['prefix' => '/api/v1'], function () {
            Router::get('/status', function () {
                return [
                    'status' => 'ok',
                    'version' => HttpApplication::VERSION,
                    'timestamp' => time(),
                ];
            });
            
            Router::get('/users', function () {
                return [
                    'users' => [
                        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
                    ]
                ];
            });
            
            Router::get('/users/{id}', function (ServerRequestInterface $request, array $params) {
                return [
                    'user' => [
                        'id' => (int)$params['id'],
                        'name' => 'User ' . $params['id'],
                        'email' => "user{$params['id']}@example.com",
                    ]
                ];
            });
        });
        
        // Home route
        Router::get('/', function () {
            return [
                'message' => 'Welcome to the API',
                'version' => HttpApplication::VERSION,
            ];
        });
    }
    
    /**
     * Emit the response to the client
     */
    private function emitResponse(ResponseInterface $response): void
    {
        // Send status code
        http_response_code($response->getStatusCode());
        
        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        
        // Send body
        echo $response->getBody();
    }
}

// Run the application
echo "=== HttpApplication with Global Middleware ===\n\n";

$_ENV['APP_DEBUG'] = false;
$_ENV['APP_ENV'] = 'development';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/users/123';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

$app = HttpApplication::init();
$app->boot();

echo "\n\n=== Configuration ===\n";
echo "Global Middleware Applied:\n";
echo "  1. RequestIdMiddleware (adds X-Request-ID header)\n";
echo "  2. LoggingMiddleware (logs requests and adds X-Response-Time)\n";
echo "  3. CorsMiddleware (adds CORS headers)\n";
echo "\nAll routes automatically have these middleware applied!\n";
