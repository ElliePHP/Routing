<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

// Example controller
class UserController
{
    public function index(ServerRequestInterface $request): array
    {
        return [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
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

    public function store(ServerRequestInterface $request): array
    {
        // In real app, you'd parse the request body
        return [
            'message' => 'User created',
            'user' => ['id' => 3, 'name' => 'New User']
        ];
    }

    public function update(ServerRequestInterface $request, string $id): array
    {
        return [
            'message' => 'User updated',
            'user' => ['id' => $id]
        ];
    }

    public function destroy(ServerRequestInterface $request, string $id): array
    {
        return [
            'message' => 'User deleted',
            'id' => $id
        ];
    }
}

// Configure router
Router::configure(['debug_mode' => true]);

// Register controller routes
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
Router::post('/users', [UserController::class, 'store']);
Router::put('/users/{id}', [UserController::class, 'update']);
Router::delete('/users/{id}', [UserController::class, 'destroy']);

// Alternative syntax
Router::get('/alt/users', 'UserController@index');

// Print routes
echo Router::printRoutes();

// Test the routes
echo "\n--- GET /users ---\n";
$request = new ServerRequest('GET', '/users');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- GET /users/42 ---\n";
$request = new ServerRequest('GET', '/users/42');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- POST /users ---\n";
$request = new ServerRequest('POST', '/users');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- PUT /users/42 ---\n";
$request = new ServerRequest('PUT', '/users/42');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n\n";

echo "--- DELETE /users/42 ---\n";
$request = new ServerRequest('DELETE', '/users/42');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";
