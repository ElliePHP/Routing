<?php

declare(strict_types=1);

/**
 * Simple Bootstrap Example - Non-Facade Usage
 * 
 * This is a minimal example showing how to bootstrap the router
 * without using the static facade.
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Core\Routing;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// ============================================
// Bootstrap: Create Router Instance
// ============================================

$router = new Routing(
    routes_directory: __DIR__ . '/routes',  // Optional: path to route files
    debugMode: $_ENV['APP_DEBUG'] ?? false,
    cacheEnabled: $_ENV['APP_ENV'] === 'production',
    cacheDirectory: __DIR__ . '/storage/cache'
);

// ============================================
// Define Your Routes
// ============================================

$router->get('/', static function () {
    return response()->text('RadioAPI Core');
});

$router->get('/api/status', static function () {
    return ['status' => 'ok', 'timestamp' => time()];
});

$router->get('/users/{id}', static function ($request, $params) {
    return ['user_id' => $params['id']];
});

// ============================================
// Handle Request
// ============================================

$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$request = $creator->fromGlobals();
$response = $router->handle($request);

// ============================================
// Send Response
// ============================================

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();

