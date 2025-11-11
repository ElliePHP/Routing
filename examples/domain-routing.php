<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;

// Configure the router with domain settings
Router::configure([
    'debug_mode' => true,
    'cache_enabled' => false,
    'enforce_domain' => false, // Set to true to reject requests from unlisted domains
    'allowed_domains' => [
        'example.com',
        'api.example.com',
        'admin.example.com',
        '{account}.example.com',
    ],
]);

// Routes for main domain
Router::get('/', static function () {
    return ['message' => 'Welcome to example.com'];
}, ['domain' => 'example.com']);

Router::get('/about', static function () {
    return ['message' => 'About example.com'];
}, ['domain' => 'example.com']);

// API subdomain routes
Router::group(['domain' => 'api.example.com', 'prefix' => '/v1'], static function () {
    Router::get('/users', static function () {
        return [
            'api' => 'v1',
            'users' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
            ]
        ];
    });

    Router::get('/users/{id}', static function ($request, $params) {
        return [
            'api' => 'v1',
            'user' => [
                'id' => $params['id'],
                'name' => 'User ' . $params['id']
            ]
        ];
    });
});

// Admin subdomain routes
Router::group(['domain' => 'admin.example.com'], static function () {
    Router::get('/dashboard', static function () {
        return ['message' => 'Admin Dashboard', 'domain' => 'admin.example.com'];
    });

    Router::get('/settings', static function () {
        return ['message' => 'Admin Settings', 'domain' => 'admin.example.com'];
    });
});

// Multi-tenant routes with domain parameters
Router::group(['domain' => '{account}.example.com'], static function () {
    Router::get('/dashboard', static function ($request, $params) {
        return [
            'message' => 'Tenant Dashboard',
            'account' => $params['account'] ?? 'unknown',
            'tenant_id' => $params['account'] ?? 'unknown'
        ];
    });

    Router::get('/profile', static function ($request, $params) {
        return [
            'message' => 'Tenant Profile',
            'account' => $params['account'] ?? 'unknown'
        ];
    });

    Router::get('/users/{id}', static function ($request, $params) {
        return [
            'message' => 'Tenant User',
            'account' => $params['account'] ?? 'unknown',
            'user_id' => $params['id']
        ];
    });
});

// Print route table
echo Router::printRoutes();

// Test different domain scenarios
echo "\n=== Testing Domain Routing ===\n";

// Test 1: Main domain
echo "\n--- Test 1: GET / on example.com ---\n";
$request = new ServerRequest('GET', 'http://example.com/');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 2: API subdomain
echo "\n--- Test 2: GET /v1/users on api.example.com ---\n";
$request = new ServerRequest('GET', 'http://api.example.com/v1/users');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 3: Admin subdomain
echo "\n--- Test 3: GET /dashboard on admin.example.com ---\n";
$request = new ServerRequest('GET', 'http://admin.example.com/dashboard');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 4: Multi-tenant with domain parameter
echo "\n--- Test 4: GET /dashboard on acme.example.com (tenant) ---\n";
$request = new ServerRequest('GET', 'http://acme.example.com/dashboard');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 5: Multi-tenant with both domain and path parameters
echo "\n--- Test 5: GET /users/42 on widgets.example.com (tenant) ---\n";
$request = new ServerRequest('GET', 'http://widgets.example.com/users/42');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 6: Wrong domain for route
echo "\n--- Test 6: GET /dashboard on example.com (should fail) ---\n";
$request = new ServerRequest('GET', 'http://example.com/dashboard');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";

// Test 7: API route with path parameter
echo "\n--- Test 7: GET /v1/users/123 on api.example.com ---\n";
$request = new ServerRequest('GET', 'http://api.example.com/v1/users/123');
$response = Router::handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getBody() . "\n";
