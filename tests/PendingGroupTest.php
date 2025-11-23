<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use Eris\Generator;
use Eris\TestTrait;
use ElliePHP\Components\Routing\Core\PendingGroup;
use ElliePHP\Components\Routing\Core\Routing;
use PHPUnit\Framework\TestCase;

class PendingGroupTest extends TestCase
{
    use TestTrait;

    private function createRouter(): Routing
    {
        return new Routing('/', false, false);
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 5: PendingGroup chaining returns self
     * **Validates: Requirements 2.2, 2.3, 2.4**
     */
    public function testPendingGroupChainingReturnsSelf(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\vector(1, Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (string $prefix, array $middleware, string $name, string $domain) {
                $router = $this->createRouter();

                // Create a PendingGroup
                $pendingGroup = new PendingGroup($router);

                // Test that prefix() returns the same instance
                $result1 = $pendingGroup->prefix($prefix);
                $this->assertSame($pendingGroup, $result1, 'prefix() should return the same PendingGroup instance');

                // Test that middleware() returns the same instance
                $result2 = $pendingGroup->middleware($middleware);
                $this->assertSame($pendingGroup, $result2, 'middleware() should return the same PendingGroup instance');

                // Test that name() returns the same instance
                $result3 = $pendingGroup->name($name);
                $this->assertSame($pendingGroup, $result3, 'name() should return the same PendingGroup instance');

                // Test that domain() returns the same instance
                $result4 = $pendingGroup->domain($domain);
                $this->assertSame($pendingGroup, $result4, 'domain() should return the same PendingGroup instance');

                // Test chaining multiple methods
                $result5 = $pendingGroup
                    ->prefix('/api')
                    ->middleware(['TestMiddleware'])
                    ->name('api')
                    ->domain('example.com');
                $this->assertSame($pendingGroup, $result5, 'Chained methods should return the same PendingGroup instance');
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 6: PendingGroup applies configuration to nested routes
     * **Validates: Requirements 2.5**
     */
    public function testPendingGroupAppliesConfigurationToNestedRoutes(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (string $prefix, array $middleware, string $name, string $domain) {
                $router = $this->createRouter();

                // Create a PendingGroup with configuration
                $pendingGroup = new PendingGroup($router);
                $pendingGroup
                    ->prefix($prefix)
                    ->middleware($middleware)
                    ->name($name)
                    ->domain($domain);

                // Get initial route count
                $initialCount = count($router->getRoutes());

                // Execute the group with nested routes
                $pendingGroup->group(function (Routing $r) {
                    $r->get('/users', fn() => ['users' => true]);
                    $r->post('/users', fn() => ['created' => true]);
                });

                // Verify routes were registered
                $routes = $router->getRoutes();
                $this->assertCount($initialCount + 2, $routes, 'Two routes should be registered');

                // Get the newly added routes
                $newRoutes = array_slice($routes, $initialCount);

                // Verify each route has the group configuration applied
                foreach ($newRoutes as $route) {
                    // Verify prefix is applied
                    if (!empty($prefix)) {
                        // Normalize prefix for comparison (router adds leading slash)
                        $normalizedPrefix = $prefix;
                        if ($normalizedPrefix !== '' && $normalizedPrefix[0] !== '/') {
                            $normalizedPrefix = '/' . $normalizedPrefix;
                        }
                        $this->assertStringStartsWith($normalizedPrefix, $route['path'], 'Route path should start with group prefix');
                    }

                    // Verify middleware is applied
                    if (!empty($middleware)) {
                        foreach ($middleware as $mw) {
                            $this->assertContains($mw, $route['middleware'], 'Route should have group middleware');
                        }
                    }

                    // Verify domain is applied
                    if (!empty($domain)) {
                        $this->assertEquals($domain, $route['domain'], 'Route should have group domain');
                    }

                    // Verify name prefix is applied (if name is not empty)
                    if (!empty($name)) {
                        $this->assertStringStartsWith($name, $route['name'], 'Route name should start with group name prefix');
                    }
                }
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 12: Group configuration merging
     * **Validates: Requirements 7.5**
     */
    public function testGroupConfigurationMerging(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (
                string $prefix,
                array $middleware1,
                array $middleware2,
                string $name,
                string $domain
            ) {
                $router = $this->createRouter();

                // Create a PendingGroup and chain multiple configuration methods
                $pendingGroup = new PendingGroup($router);
                
                // Call methods in various combinations
                $pendingGroup
                    ->prefix($prefix)
                    ->middleware($middleware1)
                    ->middleware($middleware2)  // Call middleware twice - should merge
                    ->name($name)
                    ->domain($domain);

                // Get initial route count
                $initialCount = count($router->getRoutes());

                // Execute the group
                $pendingGroup->group(function (Routing $r) {
                    $r->get('/test', fn() => ['test' => true]);
                });

                // Verify route was registered
                $routes = $router->getRoutes();
                $this->assertCount($initialCount + 1, $routes, 'One route should be registered');

                // Get the newly added route
                $route = end($routes);

                // Verify all configurations were merged correctly
                // Middleware should be merged
                $expectedMiddleware = array_merge($middleware1, $middleware2);
                if (!empty($expectedMiddleware)) {
                    $this->assertEquals($expectedMiddleware, $route['middleware'], 'Middleware should be merged');
                }

                // Prefix should be applied
                if (!empty($prefix)) {
                    $normalizedPrefix = $prefix;
                    if ($normalizedPrefix !== '' && $normalizedPrefix[0] !== '/') {
                        $normalizedPrefix = '/' . $normalizedPrefix;
                    }
                    $this->assertStringStartsWith($normalizedPrefix, $route['path'], 'Prefix should be applied');
                }

                // Name should be applied
                if (!empty($name)) {
                    $this->assertStringStartsWith($name, $route['name'], 'Name should be applied');
                }

                // Domain should be applied
                if (!empty($domain)) {
                    $this->assertEquals($domain, $route['domain'], 'Domain should be applied');
                }
            });
    }
}
