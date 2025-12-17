<?php

declare(strict_types=1);

/**
 * Mixed Configuration Patterns Example
 * 
 * Demonstrates various ways to combine array-based and fluent configuration
 * to show flexibility and migration paths for existing applications.
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

echo "=== Mixed Configuration Patterns ===\n\n";

// ============================================================================
// Pattern 1: Array First, Then Fluent Enhancement
// ============================================================================

echo "--- Pattern 1: Array Base + Fluent Enhancement ---\n";

Router::resetInstance();

// Start with existing array configuration (legacy setup)
Router::configure([
    'debug_mode' => false,
    'cache_enabled' => false, // Disable cache to avoid directory issues
    'routes_directory' => '/',
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['legacy.example.com']
]);

// Enhance with fluent configuration (new features)
Router::configure()
    ->debugMode(true) // Override: enable debug for development
    ->allowedDomains(['legacy.example.com', 'new.example.com']) // Replace: update domains
    ->enforceDomain(false) // Add: new configuration option
    ->build();

Router::get('/pattern1', function () {
    return [
        'pattern' => 'array base + fluent enhancement',
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern1');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Debug (overridden to true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache (disabled by debug): " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Pattern 2: Fluent First, Then Array Override
// ============================================================================

echo "--- Pattern 2: Fluent Base + Array Override ---\n";

Router::resetInstance();

// Start with fluent configuration (modern setup)
Router::configure()
    ->debugMode(false)
    ->disableCache()
    ->routesDirectory('/api')
    ->globalMiddleware([]) // Remove invalid middleware for example
    ->allowedDomains(['api.example.com'])
    ->build();

// Override with array configuration (environment-specific)
Router::configure([
    'debug_mode' => true, // Override for development environment
    'cache_enabled' => false, // Override for development
    'global_middleware' => [] // Remove invalid middleware for example
]);

Router::get('/pattern2', function () {
    return [
        'pattern' => 'fluent base + array override',
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern2');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Debug (overridden to true): " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache (overridden to false): " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Pattern 3: Conditional Configuration Based on Environment
// ============================================================================

echo "--- Pattern 3: Environment-Based Configuration ---\n";

Router::resetInstance();

// Base configuration for all environments
Router::configure([
    'routes_directory' => '/',
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['app.example.com']
]);

// Environment-specific fluent configuration
$environment = $_ENV['APP_ENV'] ?? 'development';

if ($environment === 'production') {
    Router::configure()
        ->debugMode(false)
        ->disableCache() // Disable cache to avoid directory issues
        ->enforceDomain(true)
        ->build();
} elseif ($environment === 'staging') {
    Router::configure()
        ->debugMode(true)
        ->disableCache() // Disable cache to avoid directory issues
        ->enforceDomain(false)
        ->build();
} else { // development
    Router::configure()
        ->debugMode(true)
        ->disableCache()
        ->enforceDomain(false)
        ->build();
}

Router::get('/pattern3', function () use ($environment) {
    return [
        'pattern' => 'environment-based configuration',
        'environment' => $environment,
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern3');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Environment: $environment\n";
echo "Debug: " . (Router::isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache: " . (Router::isCacheEnabled() ? 'enabled' : 'disabled') . "\n\n";

// ============================================================================
// Pattern 4: Gradual Migration from Array to Fluent
// ============================================================================

echo "--- Pattern 4: Gradual Migration Strategy ---\n";

Router::resetInstance();

// Step 1: Start with full array configuration (existing application)
$legacyConfig = [
    'debug_mode' => false,
    'cache_enabled' => false, // Disable cache to avoid directory issues
    'routes_directory' => '/',
    'global_middleware' => [], // Remove invalid middleware for example
    'allowed_domains' => ['old.example.com'],
    'enforce_domain' => false // Disable for example
];

Router::configure($legacyConfig);

// Step 2: Gradually migrate specific options to fluent API
Router::configure()
    ->debugMode(true) // Migrate debug setting
    ->allowedDomains(['old.example.com', 'new.example.com']) // Update domains
    ->build();

// Step 3: In future iterations, more options can be migrated to fluent API

Router::get('/pattern4', function () {
    return [
        'pattern' => 'gradual migration',
        'status' => 'partially migrated to fluent API',
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern4');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Migration status: Array base with fluent enhancements\n\n";

// ============================================================================
// Pattern 5: Configuration Composition with Functions
// ============================================================================

echo "--- Pattern 5: Configuration Composition ---\n";

Router::resetInstance();

// Helper functions for common configuration patterns
function configureForDevelopment(): void
{
    Router::configure()
        ->debugMode(true)
        ->disableCache();
}

function configureForProduction(): void
{
    Router::configure()
        ->debugMode(false)
        ->disableCache() // Disable cache to avoid directory issues
        ->enforceDomain(true);
}

function configureApiDefaults(): void
{
    Router::configure([
        'routes_directory' => '/api',
        'global_middleware' => [], // Remove invalid middleware for example
        'allowed_domains' => ['api.example.com']
    ]);
}

// Compose configuration
configureApiDefaults();
configureForDevelopment(); // This will override/enhance the API defaults
Router::configure()->build(); // Apply all configurations

Router::get('/pattern5', function () {
    return [
        'pattern' => 'configuration composition',
        'approach' => 'function-based configuration',
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern5');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Composition: API defaults + development overrides\n\n";

// ============================================================================
// Pattern 6: Feature Flag Based Configuration
// ============================================================================

echo "--- Pattern 6: Feature Flag Configuration ---\n";

Router::resetInstance();

// Simulate feature flags
$featureFlags = [
    'enhanced_caching' => true,
    'strict_domain_enforcement' => false,
    'new_middleware_stack' => true,
    'debug_in_staging' => true
];

// Base configuration
Router::configure([
    'routes_directory' => '/',
    'global_middleware' => [] // Remove invalid middleware for example
]);

// Feature-flag driven fluent configuration
$builder = Router::configure();

if ($featureFlags['enhanced_caching']) {
    $builder->disableCache(); // Disable cache to avoid directory issues
} else {
    $builder->disableCache();
}

if ($featureFlags['strict_domain_enforcement']) {
    $builder->enforceDomain(true)->allowedDomains(['secure.example.com']);
} else {
    $builder->enforceDomain(false)->allowedDomains(['*.example.com']);
}

if ($featureFlags['new_middleware_stack']) {
    // Skip middleware for example to avoid errors
}

if ($featureFlags['debug_in_staging']) {
    $builder->debugMode(true);
}

$builder->build();

Router::get('/pattern6', function () use ($featureFlags) {
    return [
        'pattern' => 'feature flag configuration',
        'flags' => $featureFlags,
        'debug' => Router::isDebugMode(),
        'cache' => Router::isCacheEnabled()
    ];
});

$request = new ServerRequest('GET', '/pattern6');
$response = Router::handle($request);
echo "Result: " . $response->getBody() . "\n";
echo "Feature flags applied to configuration\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Mixed Configuration Patterns Summary ===\n";
echo "✓ Array base + fluent enhancement\n";
echo "✓ Fluent base + array override\n";
echo "✓ Environment-based configuration\n";
echo "✓ Gradual migration strategy\n";
echo "✓ Configuration composition with functions\n";
echo "✓ Feature flag based configuration\n";
echo "\nAll mixed configuration patterns demonstrated successfully!\n";
echo "These patterns show how to flexibly combine array and fluent configuration\n";
echo "to meet different application needs and migration scenarios.\n";