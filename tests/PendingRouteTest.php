<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use Eris\Generator;
use Eris\TestTrait;
use ElliePHP\Components\Routing\Core\PendingRoute;
use ElliePHP\Components\Routing\Core\Routing;
use PHPUnit\Framework\TestCase;

class PendingRouteTest extends TestCase
{
    use TestTrait;

    private function createRouter(): Routing
    {
        return new Routing('/', false, false);
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 2: PendingRoute chaining returns self
     * **Validates: Requirements 1.2, 1.3, 1.4, 4.3**
     */
    public function testPendingRouteChainingReturnsSelf(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\vector(1, Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (string $url, array $middleware, string $name, string $domain) {
                $router = $this->createRouter();
                $handler = function () {
                    return ['test' => true];
                };

                // Create a PendingRoute
                $pendingRoute = new PendingRoute($router, 'GET', $url, $handler);

                // Test that middleware() returns the same instance
                $result1 = $pendingRoute->middleware($middleware);
                $this->assertSame($pendingRoute, $result1, 'middleware() should return the same PendingRoute instance');

                // Test that name() returns the same instance
                $result2 = $pendingRoute->name($name);
                $this->assertSame($pendingRoute, $result2, 'name() should return the same PendingRoute instance');

                // Test that domain() returns the same instance
                $result3 = $pendingRoute->domain($domain);
                $this->assertSame($pendingRoute, $result3, 'domain() should return the same PendingRoute instance');

                // Test chaining multiple methods
                $result4 = $pendingRoute
                    ->middleware(['TestMiddleware'])
                    ->name('test.route')
                    ->domain('example.com');
                $this->assertSame($pendingRoute, $result4, 'Chained methods should return the same PendingRoute instance');
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 3: PendingRoute registers route on destruction
     * **Validates: Requirements 1.5**
     */
    public function testPendingRouteRegistersOnDestruction(): void
    {
        $this->forAll(
            Generator\elements('GET', 'POST', 'PUT', 'DELETE', 'PATCH'),
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (string $method, string $url, array $middleware, string $name, string $domain) {
                $router = $this->createRouter();
                $handler = function () {
                    return ['test' => true];
                };

                // Get initial route count
                $initialCount = count($router->getRoutes());

                // Create a PendingRoute
                $pendingRoute = new PendingRoute($router, $method, $url, $handler);
                $pendingRoute
                    ->middleware($middleware)
                    ->name($name)
                    ->domain($domain);
                
                // Route should not be registered yet
                $this->assertCount($initialCount, $router->getRoutes(), 'Route should not be registered before destruction');
                
                // Explicitly destroy the PendingRoute
                unset($pendingRoute);

                // Route should now be registered
                $routes = $router->getRoutes();
                $this->assertCount($initialCount + 1, $routes, 'Route should be registered after destruction');

                // Verify the route has the correct configuration
                $lastRoute = end($routes);
                $this->assertEquals(strtoupper($method), $lastRoute['method'], 'Route method should match');
                
                // Verify middleware was applied
                if (!empty($middleware)) {
                    $this->assertEquals($middleware, $lastRoute['middleware'], 'Middleware should be applied');
                }
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 9: Configuration order independence
     * **Validates: Requirements 4.1**
     */
    public function testConfigurationOrderIndependence(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (string $url, array $middleware, string $name, string $domain) {
                $router1 = $this->createRouter();
                $router2 = $this->createRouter();
                $router3 = $this->createRouter();
                
                $handler = function () {
                    return ['test' => true];
                };

                // Apply configuration in different orders
                // Order 1: middleware -> name -> domain
                $pending1 = new PendingRoute($router1, 'GET', $url, $handler);
                $pending1->middleware($middleware)->name($name)->domain($domain);
                unset($pending1);

                // Order 2: name -> domain -> middleware
                $pending2 = new PendingRoute($router2, 'GET', $url, $handler);
                $pending2->name($name)->domain($domain)->middleware($middleware);
                unset($pending2);

                // Order 3: domain -> middleware -> name
                $pending3 = new PendingRoute($router3, 'GET', $url, $handler);
                $pending3->domain($domain)->middleware($middleware)->name($name);
                unset($pending3);

                // All three routes should have identical configuration
                $routes1 = $router1->getRoutes();
                $routes2 = $router2->getRoutes();
                $routes3 = $router3->getRoutes();

                $this->assertCount(1, $routes1);
                $this->assertCount(1, $routes2);
                $this->assertCount(1, $routes3);

                $route1 = $routes1[0];
                $route2 = $routes2[0];
                $route3 = $routes3[0];

                // Compare middleware
                $this->assertEquals($route1['middleware'], $route2['middleware'], 'Middleware should be the same regardless of order');
                $this->assertEquals($route1['middleware'], $route3['middleware'], 'Middleware should be the same regardless of order');

                // Compare names (if not empty)
                if (!empty($name)) {
                    $this->assertEquals($route1['name'], $route2['name'], 'Name should be the same regardless of order');
                    $this->assertEquals($route1['name'], $route3['name'], 'Name should be the same regardless of order');
                }

                // Compare domains (if not empty)
                if (!empty($domain)) {
                    $this->assertEquals($route1['domain'], $route2['domain'], 'Domain should be the same regardless of order');
                    $this->assertEquals($route1['domain'], $route3['domain'], 'Domain should be the same regardless of order');
                }
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 10: Multiple calls merge or override appropriately
     * **Validates: Requirements 4.2**
     */
    public function testMultipleCallsMergeOrOverride(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string(),
            Generator\string(),
            Generator\string()
        )
            ->then(function (
                string $url,
                array $middleware1,
                array $middleware2,
                string $name1,
                string $name2,
                string $domain1,
                string $domain2
            ) {
                $router = $this->createRouter();
                $handler = function () {
                    return ['test' => true];
                };

                // Create a PendingRoute and call methods multiple times
                $pendingRoute = new PendingRoute($router, 'GET', $url, $handler);
                
                // Call middleware() multiple times - should merge
                $pendingRoute->middleware($middleware1);
                $pendingRoute->middleware($middleware2);
                
                // Call name() multiple times - last should win
                $pendingRoute->name($name1);
                $pendingRoute->name($name2);
                
                // Call domain() multiple times - last should win
                $pendingRoute->domain($domain1);
                $pendingRoute->domain($domain2);
                
                unset($pendingRoute);

                $routes = $router->getRoutes();
                $this->assertCount(1, $routes);
                
                $route = $routes[0];

                // Verify middleware was merged
                $expectedMiddleware = array_merge($middleware1, $middleware2);
                $this->assertEquals($expectedMiddleware, $route['middleware'], 'Middleware should be merged when called multiple times');

                // Verify name was overridden (last wins)
                if (!empty($name2)) {
                    $this->assertEquals($name2, $route['name'], 'Name should be overridden by last call');
                }

                // Verify domain was overridden (last wins)
                if (!empty($domain2)) {
                    $this->assertEquals($domain2, $route['domain'], 'Domain should be overridden by last call');
                }
            });
    }
}
