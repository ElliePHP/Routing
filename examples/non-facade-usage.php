<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Core\Routing;
use Nyholm\Psr7\ServerRequest;

echo "=== Non-Facade Usage Example ===\n\n";

// Create router instance directly (no facade)
$router = new Routing(
    routes_directory: '/',
    debugMode: true,
    cacheEnabled: false
);

// Define routes using the instance
$router->get('/', function () {
    return ['message' => 'Welcome! Using Routing class directly.'];
});

$router->get('/hello/{name}', function ($request, $params) {
    return ['greeting' => "Hello, {$params['name']}!"];
});

$router->group(['prefix' => '/api'], function ($router) {
    $router->get('/users', function () {
        return [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]
        ];
    });

    $router->get('/users/{id}', function ($request, $params) {
        return [
            'user' => [
                'id' => $params['id'],
                'name' => 'User ' . $params['id']
            ]
        ];
    });
});

// Print route table
echo $router->printRoutes();

// Test routes
echo "\n--- Testing GET / ---\n";
$request = new ServerRequest('GET', '/');
$response = $router->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- Testing GET /hello/Developer ---\n";
$request = new ServerRequest('GET', '/hello/Developer');
$response = $router->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- Testing GET /api/users ---\n";
$request = new ServerRequest('GET', '/api/users');
$response = $router->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- Testing GET /api/users/99 ---\n";
$request = new ServerRequest('GET', '/api/users/99');
$response = $router->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

// Show debug info
echo "--- Debug Information ---\n";
echo "Debug mode: " . ($router->isDebugMode() ? 'enabled' : 'disabled') . "\n";
echo "Cache enabled: " . ($router->isCacheEnabled() ? 'yes' : 'no') . "\n";
echo "Total routes: " . count($router->getRoutes()) . "\n";
