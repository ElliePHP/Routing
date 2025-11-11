<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure the router
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
]);

// Define routes
Router::get('/', static function () {
    return ['message' => 'Welcome to ElliePHP Routing!'];
});

Router::get('/hello/{name}', static function ($request, $params) {
    return ['message' => "Hello, {$params['name']}!"];
});

Router::group(['prefix' => '/api'], static function () {
    Router::get('/users', static function () {
        return [
            'users' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
            ]
        ];
    });

    Router::get('/users/{id}', static function ($request, $params) {
        return [
            'user' => [
                'id' => $params['id'],
                'name' => 'User ' . $params['id']
            ]
        ];
    });

    Router::post('/users', static function () {
        return [
            'message' => 'User created',
            'user' => ['id' => 3, 'name' => 'New User']
        ];
    });
});

// Print route table
echo Router::printRoutes();

// Simulate handling a request
echo "\n--- Handling GET / ---\n";
$request = new ServerRequest('GET', '/');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

echo "\n--- Handling GET /hello/World ---\n";
$request = new ServerRequest('GET', '/hello/World');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

echo "\n--- Handling GET /api/users ---\n";
$request = new ServerRequest('GET', '/api/users');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

echo "\n--- Handling GET /api/users/42 ---\n";
$request = new ServerRequest('GET', '/api/users/42');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

echo "\n--- Handling 404 ---\n";
$request = new ServerRequest('GET', '/not-found');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";
