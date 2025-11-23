<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fluent API Examples
 * 
 * This file demonstrates the fluent method chaining API for defining routes and groups.
 * The fluent API provides a more expressive and readable alternative to array-based configuration.
 */

// Example middleware classes
class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Auth', 'authenticated');
    }
}

class ApiMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-API-Version', 'v1');
    }
}

class AdminMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Admin', 'true');
    }
}

// Configure router
Router::configure(['debug_mode' => true]);

echo "=== Fluent API Examples ===\n\n";

// ============================================================================
// Example 1: Basic Fluent Route Configuration
// ============================================================================

echo "Example 1: Basic Fluent Route Configuration\n";
echo "--------------------------------------------\n";

// Simple route with middleware
Router::get('/users', function () {
    return ['users' => ['Alice', 'Bob', 'Charlie']];
})
    ->middleware([AuthMiddleware::class])
    ->name('users.index');

// Route with multiple configuration options
Router::post('/users', function () {
    return ['message' => 'User created'];
})
    ->middleware([AuthMiddleware::class, ApiMiddleware::class])
    ->name('users.store')
    ->domain('api.example.com');

// Test the route
$request = new ServerRequest('GET', '/users');
$response = Router::handle($request);
echo "GET /users: " . $response->getBody() . "\n";
echo "Headers: X-Auth = " . $response->getHeaderLine('X-Auth') . "\n\n";

// ============================================================================
// Example 2: Fluent Group Configuration
// ============================================================================

echo "Example 2: Fluent Group Configuration\n";
echo "--------------------------------------\n";

// Start group with prefix
Router::prefix('/api/v1')
    ->middleware([ApiMiddleware::class])
    ->name('api.v1')
    ->group(function () {
        Router::get('/products', function () {
            return ['products' => ['Product 1', 'Product 2']];
        })
            ->name('products');
        
        Router::get('/categories', function () {
            return ['categories' => ['Category A', 'Category B']];
        })
            ->name('categories');
    });

// Test the grouped route
$request = new ServerRequest('GET', '/api/v1/products');
$response = Router::handle($request);
echo "GET /api/v1/products: " . $response->getBody() . "\n";
echo "Headers: X-API-Version = " . $response->getHeaderLine('X-API-Version') . "\n\n";

// ============================================================================
// Example 3: Start Group with Different Methods
// ============================================================================

echo "Example 3: Start Group with Different Methods\n";
echo "----------------------------------------------\n";

// Start with middleware
Router::middleware([AuthMiddleware::class])
    ->prefix('/dashboard')
    ->group(function () {
        Router::get('/', function () {
            return ['page' => 'dashboard'];
        });
    });

// Start with domain
Router::domain('admin.example.com')
    ->prefix('/admin')
    ->middleware([AuthMiddleware::class, AdminMiddleware::class])
    ->group(function () {
        Router::get('/users', function () {
            return ['admin_users' => []];
        });
    });

// Start with name
Router::name('blog')
    ->prefix('/blog')
    ->group(function () {
        Router::get('/posts', function () {
            return ['posts' => []];
        })
            ->name('posts'); // Full name: blog.posts
    });

$request = new ServerRequest('GET', '/dashboard');
$response = Router::handle($request);
echo "GET /dashboard: " . $response->getBody() . "\n";
echo "Headers: X-Auth = " . $response->getHeaderLine('X-Auth') . "\n\n";

// ============================================================================
// Example 4: Configuration Order Independence
// ============================================================================

echo "Example 4: Configuration Order Independence\n";
echo "--------------------------------------------\n";

// These three routes are functionally identical
Router::get('/order1', function () {
    return ['order' => 1];
})
    ->middleware([AuthMiddleware::class])
    ->name('order.one')
    ->domain('example.com');

Router::get('/order2', function () {
    return ['order' => 2];
})
    ->name('order.two')
    ->domain('example.com')
    ->middleware([AuthMiddleware::class]);

Router::get('/order3', function () {
    return ['order' => 3];
})
    ->domain('example.com')
    ->middleware([AuthMiddleware::class])
    ->name('order.three');

echo "All three routes configured with same options in different order\n";
echo "Route /order1, /order2, /order3 are functionally identical\n\n";

// ============================================================================
// Example 5: Multiple Middleware Calls (Merging)
// ============================================================================

echo "Example 5: Multiple Middleware Calls\n";
echo "-------------------------------------\n";

// Calling middleware() multiple times merges the arrays
Router::get('/admin/reports', function () {
    return ['reports' => []];
})
    ->middleware([AuthMiddleware::class])
    ->middleware([AdminMiddleware::class])
    ->middleware([ApiMiddleware::class])
    ->name('admin.reports');

$request = new ServerRequest('GET', '/admin/reports');
$response = Router::handle($request);
echo "GET /admin/reports: " . $response->getBody() . "\n";
echo "Headers:\n";
echo "  X-Auth = " . $response->getHeaderLine('X-Auth') . "\n";
echo "  X-Admin = " . $response->getHeaderLine('X-Admin') . "\n";
echo "  X-API-Version = " . $response->getHeaderLine('X-API-Version') . "\n\n";

// ============================================================================
// Example 6: Nested Groups with Fluent Syntax
// ============================================================================

echo "Example 6: Nested Groups\n";
echo "------------------------\n";

Router::prefix('/api')
    ->middleware([ApiMiddleware::class])
    ->group(function () {
        Router::prefix('/v2')
            ->name('api.v2')
            ->group(function () {
                Router::middleware([AuthMiddleware::class])
                    ->group(function () {
                        Router::get('/secure-data', function () {
                            return ['data' => 'secure'];
                        })
                            ->name('secure.data');
                    });
            });
    });

$request = new ServerRequest('GET', '/api/v2/secure-data');
$response = Router::handle($request);
echo "GET /api/v2/secure-data: " . $response->getBody() . "\n";
echo "Headers:\n";
echo "  X-API-Version = " . $response->getHeaderLine('X-API-Version') . "\n";
echo "  X-Auth = " . $response->getHeaderLine('X-Auth') . "\n\n";

// ============================================================================
// Example 7: Multi-Tenant with Fluent Syntax
// ============================================================================

echo "Example 7: Multi-Tenant Routing\n";
echo "--------------------------------\n";

Router::domain('{tenant}.example.com')
    ->middleware([AuthMiddleware::class])
    ->group(function () {
        Router::get('/dashboard', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'page' => 'dashboard'
            ];
        })
            ->name('tenant.dashboard');
        
        Router::get('/settings', function ($request, $params) {
            return [
                'tenant' => $params['tenant'],
                'page' => 'settings'
            ];
        })
            ->name('tenant.settings');
    });

echo "Multi-tenant routes configured for {tenant}.example.com\n";
echo "Routes: /dashboard, /settings\n\n";

// ============================================================================
// Example 8: Mixed Syntax (Array and Fluent)
// ============================================================================

echo "Example 8: Mixed Syntax Usage\n";
echo "------------------------------\n";

// You can mix array and fluent syntax in the same application
Router::prefix('/mixed')
    ->group(function () {
        // Fluent syntax
        Router::get('/fluent', function () {
            return ['type' => 'fluent'];
        })
            ->middleware([ApiMiddleware::class]);
        
        // Array syntax (still works)
        Router::get('/array', function () {
            return ['type' => 'array'];
        }, [
            'middleware' => [ApiMiddleware::class]
        ]);
    });

$request = new ServerRequest('GET', '/mixed/fluent');
$response = Router::handle($request);
echo "GET /mixed/fluent: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/mixed/array');
$response = Router::handle($request);
echo "GET /mixed/array: " . $response->getBody() . "\n";
echo "Both syntaxes work together seamlessly\n\n";

// ============================================================================
// Example 9: Real-World API Structure
// ============================================================================

echo "Example 9: Real-World API Structure\n";
echo "------------------------------------\n";

// Public API endpoints
Router::prefix('/api/public')
    ->middleware([ApiMiddleware::class])
    ->name('api.public')
    ->group(function () {
        Router::get('/status', function () {
            return ['status' => 'operational'];
        })
            ->name('status');
        
        Router::get('/version', function () {
            return ['version' => '1.0.0'];
        })
            ->name('version');
    });

// Authenticated API endpoints
Router::prefix('/api/private')
    ->middleware([ApiMiddleware::class, AuthMiddleware::class])
    ->name('api.private')
    ->group(function () {
        Router::get('/profile', function () {
            return ['user' => 'John Doe'];
        })
            ->name('profile');
        
        Router::get('/notifications', function () {
            return ['notifications' => []];
        })
            ->name('notifications');
    });

// Admin API endpoints
Router::prefix('/api/admin')
    ->middleware([ApiMiddleware::class, AuthMiddleware::class, AdminMiddleware::class])
    ->name('api.admin')
    ->group(function () {
        Router::get('/stats', function () {
            return ['stats' => ['users' => 100, 'posts' => 500]];
        })
            ->name('stats');
    });

$request = new ServerRequest('GET', '/api/public/status');
$response = Router::handle($request);
echo "GET /api/public/status: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/api/private/profile');
$response = Router::handle($request);
echo "GET /api/private/profile: " . $response->getBody() . "\n";

$request = new ServerRequest('GET', '/api/admin/stats');
$response = Router::handle($request);
echo "GET /api/admin/stats: " . $response->getBody() . "\n\n";

// ============================================================================
// Example 10: Progressive Configuration
// ============================================================================

echo "Example 10: Progressive Configuration\n";
echo "--------------------------------------\n";

// Build configuration conditionally
$requiresAuth = true;
$isProduction = false;

$route = Router::get('/conditional', function () {
    return ['message' => 'Conditional configuration'];
})
    ->name('conditional');

if ($requiresAuth) {
    $route->middleware([AuthMiddleware::class]);
    echo "Auth middleware added\n";
}

if ($isProduction) {
    $route->domain('production.example.com');
    echo "Production domain set\n";
} else {
    echo "Development mode - no domain restriction\n";
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo "=== Summary ===\n";
echo "The fluent API provides:\n";
echo "  ✓ More readable and expressive syntax\n";
echo "  ✓ Better IDE autocomplete support\n";
echo "  ✓ Flexible method chaining in any order\n";
echo "  ✓ Full backward compatibility with array syntax\n";
echo "  ✓ Ability to mix both syntaxes in the same application\n";
echo "\n";
echo "All routes registered successfully!\n";
echo "Total routes: " . count(Router::getRoutes()) . "\n";

