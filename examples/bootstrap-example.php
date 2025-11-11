<?php

declare(strict_types=1);

/**
 * Example: Using Router without Facade in Bootstrap
 * 
 * This demonstrates how to use the Routing class directly
 * instead of the static Router facade.
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Core\Routing;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// ============================================
// Step 1: Create Router Instance
// ============================================

$router = new Routing(
    routes_directory: __DIR__ . '/routes',  // Directory containing route files (optional)
    debugMode: $_ENV['APP_DEBUG'] ?? false,  // Enable debug mode
    cacheEnabled: $_ENV['APP_ENV'] === 'production',  // Enable cache in production
    cacheDirectory: __DIR__ . '/storage/cache',  // Cache directory (optional)
    errorFormatter: null,  // Use default error formatter (optional)
    enforceDomain: false,  // Domain enforcement (optional)
    allowedDomains: []  // Allowed domains (optional)
);

// ============================================
// Step 2: Define Routes Programmatically
// ============================================

// Simple closure route
$router->get('/', static function () {
    return ['message' => 'Welcome to RadioAPI Core'];
});

// Route with parameters
$router->get('/users/{id}', static function ($request, $params) {
    return [
        'user_id' => $params['id'],
        'message' => 'User details'
    ];
});

// Route with controller class
class UserController
{
    public function index($request): array
    {
        return ['users' => ['user1', 'user2']];
    }
    
    public function show($request, string $id): array
    {
        return ['user' => ['id' => $id, 'name' => 'John Doe']];
    }
}

$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);

// Route groups
$router->group(['prefix' => '/api/v1'], function ($router) {
    $router->get('/posts', static function () {
        return ['posts' => []];
    });
    
    $router->post('/posts', static function ($request) {
        return ['created' => true];
    });
});

// Routes with middleware
class AuthMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler
    ): \Psr\Http\Message\ResponseInterface {
        // Check authentication
        $response = $handler->handle($request);
        return $response->withHeader('X-Authenticated', 'true');
    }
}

$router->get('/protected', static function () {
    return ['protected' => true];
}, [
    'middleware' => [AuthMiddleware::class]
]);

// ============================================
// Step 3: Handle Incoming Request
// ============================================

// Create PSR-7 request from globals
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory, // ServerRequestFactory
    $psr17Factory, // UriFactory
    $psr17Factory, // UploadedFileFactory
    $psr17Factory  // StreamFactory
);

$request = $creator->fromGlobals();

// Handle the request
$response = $router->handle($request);

// ============================================
// Step 4: Send Response
// ============================================

// Send status code
http_response_code($response->getStatusCode());

// Send headers
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}

// Send body
echo $response->getBody();

// ============================================
// Alternative: Using Route Files
// ============================================

/*
If you want to load routes from files instead of defining them programmatically:

1. Create a routes directory:
   mkdir -p routes

2. Create route files (e.g., routes/web.php):
   <?php
   $router->get('/', function() {
       return ['message' => 'Home'];
   });

3. Initialize router with routes directory:
   $router = new Routing(
       routes_directory: __DIR__ . '/routes',
       debugMode: true,
       cacheEnabled: false
   );
   
   Routes will be automatically loaded from PHP files in the directory.
*/

// ============================================
// Helper Methods Available
// ============================================

// Get all registered routes
$routes = $router->getRoutes();

// Get formatted routes for debugging
$formatted = $router->getFormattedRoutes();

// Print route table
if ($router->isDebugMode()) {
    echo $router->printRoutes();
}

// Clear cache
$router->clearCache();

// Reset router (useful for testing)
// $router->reset();

