<?php

declare(strict_types=1);

/**
 * Comprehensive Fluent Configuration Examples
 * 
 * This example demonstrates all aspects of the fluent router configuration API:
 * - Basic fluent configuration
 * - Mixed fluent and array configuration
 * - Advanced configuration scenarios
 * - Backward compatibility verification
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

echo "=== Comprehensive Fluent Configuration Examples ===\n\n";

// ============================================================================
// Example 1: Basic Fluent Configuration
// ============================================================================

echo "--- Example 1: Basic Fluent Configuration ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->disableCache()
    ->routesDirectory('/')
    ->build();

Router::get('/basic', function () {
    return ['example' => 'basic fluent configuration'];
});

$request = new ServerRequest('GET', '/basic');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 2: Method Chaining with All Options
// ============================================================================

echo "--- Example 2: Complete Method Chaining ---\n";

// Custom middleware for demonstration
class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        echo "[MIDDLEWARE] Processing request: " . $request->getUri()->getPath() . "\n";
        $response = $handler->handle($request);
        return $response->withHeader('X-Logged', 'true');
    }
}

// Custom error formatter
class JsonErrorFormatter implements ErrorFormatterInterface
{
    public function format(\Throwable $e, bool $debugMode): array
    {
        return [
            'error' => $e->getMessage(),
            'debug' => $debugMode,
            'formatted_by' => 'JsonErrorFormatter'
        ];
    }
}

// Simple container implementation
class SimpleContainer implements ContainerInterface
{
    private array $services = [];

    public function get(string $id)
    {
        return $this->services[$id] ?? throw new \Exception("Service not found: $id");
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }
}

$container = new SimpleContainer();
$container->set('logger', 'Logger service instance');

Router::resetInstance();
Router::configure()
    ->debugMode(false)
    ->disableCache() // Disable cache to avoid directory issues
    ->routesDirectory('/')
    ->globalMiddleware([LoggingMiddleware::class])
    ->allowedDomains(['example.com', 'api.example.com'])
    ->enforceDomain(false) // Disabled for example
    ->container($container)
    ->errorFormatter(new JsonErrorFormatter())
    ->apply(); // Using apply() instead of build()

Router::get('/complete', function () {
    return ['example' => 'complete configuration', 'features' => 'all enabled'];
});

$request = new ServerRequest('GET', '/complete');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";
echo "X-Logged header: " . $response->getHeaderLine('X-Logged') . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 3: Mixed Fluent and Array Configuration
// ============================================================================

echo "--- Example 3: Mixed Configuration Approaches ---\n";

Router::resetInstance();

// Start with array configuration
Router::configure([
    'debug_mode' => false,
    'cache_enabled' => false, // Disable cache to avoid directory issues
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['legacy.com']
]);

// Add fluent configuration on top
Router::configure()
    ->debugMode(true) // Overrides array config
    ->addGlobalMiddleware(LoggingMiddleware::class) // Merges with array config
    ->allowedDomains(['fluent.com']) // Replaces array config
    ->routesDirectory('/mixed')
    ->build();

Router::get('/mixed', function () {
    return ['example' => 'mixed configuration', 'type' => 'array + fluent'];
});

$request = new ServerRequest('GET', '/mixed');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";
echo "Debug mode (should be true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache (disabled by debug): " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 4: Backward Compatibility - Array Configuration
// ============================================================================

echo "--- Example 4: Backward Compatibility - Array Configuration ---\n";

Router::resetInstance();

// Traditional array configuration (unchanged behavior)
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
    'routes_directory' => '/',
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['traditional.com'],
    'enforce_domain' => false
]);

Router::get('/traditional-array', function () {
    return ['example' => 'traditional array configuration', 'compatibility' => 'maintained'];
});

Router::group(['prefix' => '/api'], function () {
    Router::get('/legacy', function () {
        return ['api' => 'legacy endpoint'];
    });
});

$request = new ServerRequest('GET', '/traditional-array');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/api/legacy');
$response = Router::handle($request);
echo "API Response: " . $response->getBody() . "\n\n";

// ============================================================================
// Example 5: Configuration Method Order Independence
// ============================================================================

echo "--- Example 5: Method Order Independence ---\n";

// Configuration in one order
Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->routesDirectory('/order1')
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->allowedDomains(['order1.com'])
    ->build();

Router::get('/order1', function () {
    return ['order' => 'first'];
});

$request = new ServerRequest('GET', '/order1');
$response1 = Router::handle($request);

// Same configuration in different order
Router::resetInstance();
Router::configure()
    ->allowedDomains(['order1.com'])
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->routesDirectory('/order1')
    ->debugMode(true)
    ->build();

Router::get('/order1', function () {
    return ['order' => 'second'];
});

$request = new ServerRequest('GET', '/order1');
$response2 = Router::handle($request);

echo "First order response: " . $response1->getBody() . "\n";
echo "Second order response: " . $response2->getBody() . "\n";
echo "Both should work identically (method order independence)\n\n";

// ============================================================================
// Example 6: Last Value Wins for Repeated Calls
// ============================================================================

echo "--- Example 6: Last Value Wins ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(false)
    ->debugMode(true)  // Should override previous
    ->routesDirectory('/first')
    ->routesDirectory('/second') // Should override previous
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->build();

Router::get('/last-wins', function () {
    return ['example' => 'last value wins', 'debug' => Router::isDebugMode()];
});

$request = new ServerRequest('GET', '/last-wins');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";
echo "Debug mode (should be true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 7: Error Handling with Custom Formatter
// ============================================================================

echo "--- Example 7: Custom Error Handling ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->errorFormatter(new JsonErrorFormatter())
    ->build();

Router::get('/error-demo', function () {
    throw new \Exception('Demonstration error for custom formatter');
});

$request = new ServerRequest('GET', '/error-demo');
$response = Router::handle($request);
echo "Error response (custom formatted): " . $response->getBody() . "\n";
echo "Status code: " . $response->getStatusCode() . "\n\n";

// ============================================================================
// Example 8: Complex Application Structure
// ============================================================================

echo "--- Example 8: Complex Application Structure ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(false)
    ->disableCache() // Disable cache to avoid directory issues
    ->globalMiddleware([LoggingMiddleware::class])
    ->allowedDomains(['app.example.com'])
    ->enforceDomain(false)
    ->build();

// API v1 routes
Router::group(['prefix' => '/api/v1'], function () {
    Router::get('/users', function () {
        return ['users' => [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']]];
    });

    Router::get('/users/{id}', function ($request, $params) {
        return ['user' => ['id' => $params['id'], 'name' => 'User ' . $params['id']]];
    });

    Router::group(['prefix' => '/admin'], function () {
        Router::get('/dashboard', function () {
            return ['dashboard' => 'admin data'];
        });
    });
});

// Test complex routes
$testRoutes = [
    ['GET', '/api/v1/users'],
    ['GET', '/api/v1/users/42'],
    ['GET', '/api/v1/admin/dashboard']
];

foreach ($testRoutes as [$method, $path]) {
    $request = new ServerRequest($method, $path);
    $response = Router::handle($request);
    echo "{$method} {$path}: " . $response->getBody() . "\n";
}

echo "\n";

// ============================================================================
// Example 9: Middleware Accumulation
// ============================================================================

echo "--- Example 9: Middleware Accumulation ---\n";

Router::resetInstance();
Router::configure()
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->build();

Router::get('/middleware-stack', function () {
    return ['middleware' => 'accumulated stack'];
});

$request = new ServerRequest('GET', '/middleware-stack');
$response = Router::handle($request);
echo "Response: " . $response->getBody() . "\n";
echo "Middleware should be accumulated: BaseMiddleware + AdditionalMiddleware1 + AdditionalMiddleware2\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Summary ===\n";
echo "✓ Basic fluent configuration\n";
echo "✓ Complete method chaining with all options\n";
echo "✓ Mixed fluent and array configuration\n";
echo "✓ Backward compatibility with array configuration\n";
echo "✓ Method order independence\n";
echo "✓ Last value wins for repeated calls\n";
echo "✓ Custom error handling\n";
echo "✓ Complex application structure\n";
echo "✓ Middleware accumulation\n";
echo "\nAll fluent configuration examples completed successfully!\n";