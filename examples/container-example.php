<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Example: Using PSR-11 Container with ElliePHP Routing
 * 
 * This example demonstrates how to integrate a PSR-11 container
 * for dependency injection of controllers and middleware.
 */

// Example service that will be injected
class UserRepository
{
    public function findById(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
    }
}

// Controller with dependency injection
class UserController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function show(ServerRequestInterface $request, string $id): array
    {
        $user = $this->userRepository->findById((int) $id);
        
        return [
            'user' => $user,
            'resolved_from' => 'container'
        ];
    }
}

// Simple PSR-11 container implementation
class SimpleContainer implements ContainerInterface
{
    private array $services = [];
    private array $factories = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        // Return cached service if exists
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Create service from factory
        if (isset($this->factories[$id])) {
            $this->services[$id] = $this->factories[$id]($this);
            return $this->services[$id];
        }

        throw new \Exception("Service not found: $id");
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->services[$id]);
    }
}

// Setup container
$container = new SimpleContainer();

// Register services
$container->set(UserRepository::class, fn() => new UserRepository());

$container->set(UserController::class, function($c) {
    return new UserController($c->get(UserRepository::class));
});

// Configure router with container
Router::configure([
    'container' => $container,
    'debug_mode' => true,
]);

// Define routes - controllers will be resolved from container
Router::get('/users/{id}', [UserController::class, 'show']);

Router::get('/', function() {
    return [
        'message' => 'PSR-11 Container Example',
        'endpoints' => [
            'GET /users/{id}' => 'Get user by ID (with DI)',
        ]
    ];
});

// Create PSR-7 request (simulating a request for demo purposes)
$uri = $_SERVER['REQUEST_URI'] ?? '/users/123';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$psr17Factory = new Psr17Factory();
$request = $psr17Factory->createServerRequest($method, $uri);

// Handle request
$response = Router::handle($request);

// Output response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}
echo $response->getBody();
