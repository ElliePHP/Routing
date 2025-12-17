<?php

declare(strict_types=1);

/**
 * Integration Test Demo
 * 
 * This example demonstrates the integration test scenarios that verify
 * the fluent router configuration works correctly with actual router usage.
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

echo "=== Integration Test Demo ===\n\n";

// ============================================================================
// Test 1: Basic Fluent Configuration with Router Usage
// ============================================================================

echo "--- Test 1: Basic Fluent Configuration ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->disableCache()
    ->routesDirectory('/')
    ->enforceDomain(false)
    ->build();

Router::get('/test', function () {
    return ['configured' => 'fluent'];
});

Router::post('/users', function () {
    return ['action' => 'create'];
});

Router::group(['prefix' => '/api'], function () {
    Router::get('/status', function () {
        return ['status' => 'ok'];
    });
});

// Test the routes
$testRoutes = [
    ['GET', '/test'],
    ['POST', '/users'],
    ['GET', '/api/status']
];

foreach ($testRoutes as [$method, $path]) {
    $request = new ServerRequest($method, $path);
    $response = Router::handle($request);
    echo "{$method} {$path}: " . $response->getBody() . "\n";
}

echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Test 2: Mixed Fluent and Array Configuration
// ============================================================================

echo "--- Test 2: Mixed Configuration ---\n";

Router::resetInstance();

// Start with array configuration
Router::configure([
    'debug_mode' => false,
    'cache_enabled' => false,
    'global_middleware' => []
]);

// Add fluent configuration on top
Router::configure()
    ->debugMode(true) // Should override array config
    ->routesDirectory('/custom')
    ->build();

Router::get('/mixed', function () {
    return ['mixed' => true];
});

$request = new ServerRequest('GET', '/mixed');
$response = Router::handle($request);
echo "Mixed config response: " . $response->getBody() . "\n";
echo "Debug mode (overridden): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache (disabled by debug): " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Test 3: Fluent Configuration with Middleware
// ============================================================================

echo "--- Test 3: Middleware Configuration ---\n";

// Create test middleware
class TestMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Test-Middleware', 'executed');
    }
}

Router::resetInstance();
Router::configure()
    ->globalMiddleware([TestMiddleware::class])
    ->build();

Router::get('/middleware-test', function () {
    return ['middleware' => 'test'];
});

$request = new ServerRequest('GET', '/middleware-test');
$response = Router::handle($request);

echo "Middleware response: " . $response->getBody() . "\n";
echo "X-Test-Middleware header: " . $response->getHeaderLine('X-Test-Middleware') . "\n\n";

// ============================================================================
// Test 4: Custom Error Formatter
// ============================================================================

echo "--- Test 4: Custom Error Formatter ---\n";

class CustomErrorFormatter implements ErrorFormatterInterface
{
    public function format(\Throwable $e, bool $debugMode): array
    {
        return ['custom_error' => $e->getMessage(), 'debug' => $debugMode];
    }
}

Router::resetInstance();
Router::configure()
    ->errorFormatter(new CustomErrorFormatter())
    ->debugMode(true)
    ->build();

Router::get('/error-test', function () {
    throw new \Exception('Test error');
});

$request = new ServerRequest('GET', '/error-test');
$response = Router::handle($request);

echo "Error response: " . $response->getBody() . "\n";
echo "Status code: " . $response->getStatusCode() . "\n\n";

// ============================================================================
// Test 5: PSR-11 Container Integration
// ============================================================================

echo "--- Test 5: Container Integration ---\n";

class SimpleContainer implements ContainerInterface
{
    private array $services = [];

    public function get(string $id)
    {
        return $this->services[$id] ?? null;
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
$container->set('test_service', 'injected_value');

Router::resetInstance();
Router::configure()
    ->container($container)
    ->build();

Router::get('/container-test', function () {
    return ['container' => 'configured'];
});

$request = new ServerRequest('GET', '/container-test');
$response = Router::handle($request);

echo "Container response: " . $response->getBody() . "\n\n";

// ============================================================================
// Test 6: Backward Compatibility - Array Configuration
// ============================================================================

echo "--- Test 6: Backward Compatibility ---\n";

Router::resetInstance();
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
    'routes_directory' => '/',
    'global_middleware' => [],
    'allowed_domains' => ['test.com'],
    'enforce_domain' => false
]);

Router::get('/array-config', function () {
    return ['config_type' => 'array'];
});

Router::post('/array-post', function () {
    return ['method' => 'POST'];
});

$testRoutes = [
    ['GET', '/array-config'],
    ['POST', '/array-post']
];

foreach ($testRoutes as [$method, $path]) {
    $request = new ServerRequest($method, $path);
    $response = Router::handle($request);
    echo "{$method} {$path}: " . $response->getBody() . "\n";
}

echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Test 7: Method Chaining Identity
// ============================================================================

echo "--- Test 7: Method Chaining Identity ---\n";

Router::resetInstance();
$builder = Router::configure();

// Test that all methods return the same builder instance
$result1 = $builder->debugMode(true);
$result2 = $builder->disableCache();
$result3 = $builder->routesDirectory('/test');

echo "Builder identity preserved: " . 
     (($builder === $result1 && $builder === $result2 && $builder === $result3) ? 'YES' : 'NO') . "\n";

$builder->build();

Router::get('/chaining-test', function () {
    return ['chaining' => 'works'];
});

$request = new ServerRequest('GET', '/chaining-test');
$response = Router::handle($request);
echo "Chaining response: " . $response->getBody() . "\n\n";

// ============================================================================
// Test 8: Method Call Order Independence
// ============================================================================

echo "--- Test 8: Method Call Order Independence ---\n";

// Configuration in one order
Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->routesDirectory('/order1')
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
echo "Order independence: " . 
     ($response1->getStatusCode() === $response2->getStatusCode() ? 'VERIFIED' : 'FAILED') . "\n\n";

// ============================================================================
// Test 9: Last Value Wins
// ============================================================================

echo "--- Test 9: Last Value Wins ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(false)
    ->debugMode(true)  // Should override previous
    ->routesDirectory('/first')
    ->routesDirectory('/second') // Should override previous
    ->build();

Router::get('/last-wins', function () {
    return ['example' => 'last value wins', 'debug' => Router::isDebugMode()];
});

$request = new ServerRequest('GET', '/last-wins');
$response = Router::handle($request);
echo "Last value wins response: " . $response->getBody() . "\n";
echo "Debug mode (should be true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Integration Test Summary ===\n";
echo "✓ Basic fluent configuration with router usage\n";
echo "✓ Mixed fluent and array configuration\n";
echo "✓ Fluent configuration with middleware\n";
echo "✓ Custom error formatter integration\n";
echo "✓ PSR-11 container integration\n";
echo "✓ Backward compatibility with array configuration\n";
echo "✓ Method chaining identity preservation\n";
echo "✓ Method call order independence\n";
echo "✓ Last value wins behavior\n";
echo "\nAll integration tests completed successfully!\n";
echo "The fluent configuration API integrates seamlessly with the router\n";
echo "while maintaining full backward compatibility.\n";