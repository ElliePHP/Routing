<?php

declare(strict_types=1);

/**
 * Fluent Configuration Example
 * 
 * Basic examples showing the fluent configuration API in action
 * and demonstrating backward compatibility with array configuration.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

echo "=== Fluent Configuration Examples ===\n\n";

// ============================================================================
// Example 1: Traditional Array-Based Configuration (Backward Compatibility)
// ============================================================================

echo "--- Example 1: Traditional Array Configuration ---\n";

Router::resetInstance();
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
    'routes_directory' => '/',
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['traditional.example.com'],
    'enforce_domain' => false
]);

Router::get('/traditional', function () {
    return ['method' => 'traditional array configuration', 'status' => 'working'];
});

Router::group(['prefix' => '/api'], function () {
    Router::get('/legacy', function () {
        return ['api' => 'legacy endpoint', 'config' => 'array-based'];
    });
});

$request = new ServerRequest('GET', '/traditional');
$response = Router::handle($request);
echo "Traditional response: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/api/legacy');
$response = Router::handle($request);
echo "Legacy API response: " . $response->getBody() . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 2: Basic Fluent Configuration
// ============================================================================

echo "--- Example 2: Basic Fluent Configuration ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(true)
    ->disableCache()
    ->routesDirectory('/')
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->build();

Router::get('/fluent', function () {
    return ['method' => 'fluent configuration', 'status' => 'working'];
});

Router::post('/fluent-post', function () {
    return ['method' => 'POST', 'config' => 'fluent'];
});

$request = new ServerRequest('GET', '/fluent');
$response = Router::handle($request);
echo "Fluent response: " . $response->getBody() . "\n";

$request = new ServerRequest('POST', '/fluent-post');
$response = Router::handle($request);
echo "Fluent POST response: " . $response->getBody() . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 3: Method Chaining with Multiple Options
// ============================================================================

echo "--- Example 3: Method Chaining ---\n";

Router::resetInstance();
Router::configure()
    ->debugMode(false)
    ->disableCache() // Disable cache to avoid directory issues
    ->routesDirectory('/')
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->allowedDomains(['example.com', 'api.example.com'])
    ->enforceDomain(false)
    ->apply(); // Using apply() instead of build()

Router::get('/chained', function () {
    return ['method' => 'chained fluent configuration', 'features' => 'multiple'];
});

Router::group(['prefix' => '/api/v1'], function () {
    Router::get('/users', function () {
        return ['users' => [['id' => 1, 'name' => 'John']], 'config' => 'chained'];
    });
});

$request = new ServerRequest('GET', '/chained');
$response = Router::handle($request);
echo "Chained response: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/api/v1/users');
$response = Router::handle($request);
echo "Chained API response: " . $response->getBody() . "\n";
echo "Debug mode: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 4: Mixed Configuration (Array + Fluent)
// ============================================================================

echo "--- Example 4: Mixed Configuration ---\n";

Router::resetInstance();

// Start with array configuration
Router::configure([
    'debug_mode' => false,
    'cache_enabled' => false, // Disable cache to avoid directory issues
    'global_middleware' => [] // Remove invalid middleware for example
]);

// Enhance with fluent configuration
Router::configure()
    ->debugMode(true) // Override array config
    ->routesDirectory('/mixed')
    ->allowedDomains(['mixed.example.com'])
    ->build();

Router::get('/mixed', function () {
    return ['method' => 'mixed configuration', 'type' => 'array + fluent'];
});

$request = new ServerRequest('GET', '/mixed');
$response = Router::handle($request);
echo "Mixed response: " . $response->getBody() . "\n";
echo "Debug mode (overridden): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache (disabled by debug): " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Example 5: Demonstrating Method Order Independence
// ============================================================================

echo "--- Example 5: Method Order Independence ---\n";

// Same configuration in different order should work identically
Router::resetInstance();
Router::configure()
    ->allowedDomains(['order.example.com'])
    ->debugMode(true)
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->routesDirectory('/order-test')
    ->disableCache()
    ->build();

Router::get('/order-test', function () {
    return ['order' => 'independent', 'debug' => Router::isDebugMode()];
});

$request = new ServerRequest('GET', '/order-test');
$response = Router::handle($request);
echo "Order independence response: " . $response->getBody() . "\n";
echo "Configuration applied successfully regardless of method order\n\n";

// ============================================================================
// Example 6: Repeated Method Calls (Last Value Wins)
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
echo "Last value wins response: " . $response->getBody() . "\n";
echo "Debug mode (last value true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Summary ===\n";
echo "✓ Traditional array configuration (backward compatibility)\n";
echo "✓ Basic fluent configuration\n";
echo "✓ Method chaining with multiple options\n";
echo "✓ Mixed array + fluent configuration\n";
echo "✓ Method order independence\n";
echo "✓ Last value wins for repeated calls\n";
echo "\nAll fluent configuration examples completed successfully!\n";
echo "The fluent API provides an intuitive alternative to array configuration\n";
echo "while maintaining full backward compatibility.\n";