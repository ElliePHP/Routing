<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use Eris\Generator;
use Eris\TestTrait;
use ElliePHP\Components\Routing\Core\PendingGroup;
use ElliePHP\Components\Routing\Core\PendingRoute;
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class FluentApiTest extends TestCase
{
    use TestTrait;
    protected function setUp(): void
    {
        Router::resetInstance();
        Router::reset();
    }

    public function testFluentRouteDefinition(): void
    {
        // Test fluent API with method chaining
        Router::get('/users', function () {
            return ['users' => []];
        })
            ->middleware(['AuthMiddleware'])
            ->name('users.index')
            ->domain('api.example.com');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        
        $route = $routes[0];
        $this->assertEquals('GET', $route['method']);
        $this->assertEquals('/users', $route['path']);
        $this->assertEquals(['AuthMiddleware'], $route['middleware']);
        $this->assertEquals('users.index', $route['name']);
        $this->assertEquals('api.example.com', $route['domain']);
    }

    public function testFluentRouteExecution(): void
    {
        Router::get('/test', function () {
            return ['message' => 'fluent'];
        })
            ->name('test.route');

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('fluent', $body['message']);
    }

    public function testBackwardCompatibilityWithArraySyntax(): void
    {
        // Old array syntax should still work
        Router::get('/old', function () {
            return ['style' => 'array'];
        }, [
            'middleware' => ['TestMiddleware'],
            'name' => 'old.route'
        ]);

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        
        $route = $routes[0];
        $this->assertEquals(['TestMiddleware'], $route['middleware']);
        $this->assertEquals('old.route', $route['name']);
    }

    public function testMixedFluentAndArraySyntax(): void
    {
        // Fluent syntax
        Router::get('/fluent', function () {
            return ['type' => 'fluent'];
        })
            ->middleware(['FluentMiddleware']);

        // Array syntax
        Router::post('/array', function () {
            return ['type' => 'array'];
        }, [
            'middleware' => ['ArrayMiddleware']
        ]);

        $routes = Router::getRoutes();
        $this->assertCount(2, $routes);
        
        $this->assertEquals(['FluentMiddleware'], $routes[0]['middleware']);
        $this->assertEquals(['ArrayMiddleware'], $routes[1]['middleware']);
    }

    public function testAllHttpMethodsWithFluentApi(): void
    {
        Router::get('/get', fn() => ['method' => 'GET'])->name('get');
        Router::post('/post', fn() => ['method' => 'POST'])->name('post');
        Router::put('/put', fn() => ['method' => 'PUT'])->name('put');
        Router::delete('/delete', fn() => ['method' => 'DELETE'])->name('delete');
        Router::patch('/patch', fn() => ['method' => 'PATCH'])->name('patch');

        $routes = Router::getRoutes();
        $this->assertCount(5, $routes);
        
        $methods = array_column($routes, 'method');
        $this->assertEquals(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $methods);
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 1: Route methods return PendingRoute
     * **Validates: Requirements 1.1, 6.1**
     */
    public function testRouteMethodsReturnPendingRoute(): void
    {
        $this->forAll(
            Generator\elements('get', 'post', 'put', 'delete', 'patch'),
            Generator\string(),
            Generator\elements('closure', 'class', 'array')
        )
            ->then(function (string $method, string $url, string $handlerType) {
                Router::resetInstance();
                Router::reset();

                // Create different handler types
                $handler = match ($handlerType) {
                    'closure' => fn() => ['test' => true],
                    'class' => 'TestController',
                    'array' => ['TestController', 'index'],
                };

                // Call the route method without options array
                $result = Router::$method($url, $handler);

                // Verify it returns a PendingRoute instance
                $this->assertInstanceOf(
                    PendingRoute::class,
                    $result,
                    "Router::$method() should return a PendingRoute instance when called without options array"
                );

                // Verify the PendingRoute can be chained
                $chainResult = $result->middleware(['TestMiddleware']);
                $this->assertInstanceOf(
                    PendingRoute::class,
                    $chainResult,
                    "PendingRoute methods should return PendingRoute for chaining"
                );
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 4: PendingGroup factory methods return PendingGroup
     * **Validates: Requirements 2.1, 7.1, 7.2, 7.3, 7.4**
     */
    public function testPendingGroupFactoryMethodsReturnPendingGroup(): void
    {
        $this->forAll(
            Generator\elements('prefix', 'middleware', 'domain', 'name'),
            Generator\string(),
            Generator\seq(Generator\string())
        )
            ->then(function (string $factoryMethod, string $stringParam, array $arrayParam) {
                Router::resetInstance();
                Router::reset();

                // Call the factory method with appropriate parameter
                $result = match ($factoryMethod) {
                    'prefix' => Router::prefix($stringParam),
                    'middleware' => Router::middleware($arrayParam),
                    'domain' => Router::domain($stringParam),
                    'name' => Router::name($stringParam),
                };

                // Verify it returns a PendingGroup instance
                $this->assertInstanceOf(
                    PendingGroup::class,
                    $result,
                    "Router::$factoryMethod() should return a PendingGroup instance"
                );

                // Verify the PendingGroup can be chained with other methods
                $chainResult = $result->prefix('/test');
                $this->assertInstanceOf(
                    PendingGroup::class,
                    $chainResult,
                    "PendingGroup methods should return PendingGroup for chaining"
                );

                // Verify we can chain multiple methods
                $multiChainResult = $result
                    ->middleware(['TestMiddleware'])
                    ->name('test')
                    ->domain('example.com');
                $this->assertInstanceOf(
                    PendingGroup::class,
                    $multiChainResult,
                    "Multiple chained PendingGroup methods should return PendingGroup"
                );
            });
    }

    /**
     * @test
     * Feature: fluent-route-api, Property 7: Backward compatibility with array syntax
     * **Validates: Requirements 3.1, 3.3**
     */
    public function testBackwardCompatibilityArraySyntaxProperty(): void
    {
        $this->forAll(
            Generator\elements('get', 'post', 'put', 'delete', 'patch'),
            Generator\string(),
            Generator\seq(Generator\string()),
            Generator\string(),
            Generator\string()
        )
            ->then(function (
                string $method,
                string $url,
                array $middleware,
                string $name,
                string $domain
            ) {
                Router::resetInstance();
                Router::reset();

                $handler = fn() => ['test' => true];

                // Define route using array syntax (old way)
                Router::$method($url, $handler, [
                    'middleware' => $middleware,
                    'name' => $name,
                    'domain' => $domain,
                ]);

                $routes = Router::getRoutes();
                $this->assertCount(1, $routes, 'Array syntax should register exactly one route');

                $route = $routes[0];

                // Verify the route was registered with correct configuration
                $this->assertEquals(strtoupper($method), $route['method'], 'Method should match');
                $this->assertEquals($middleware, $route['middleware'], 'Middleware should match');
                
                if (!empty($name)) {
                    $this->assertEquals($name, $route['name'], 'Name should match');
                }
                
                if (!empty($domain)) {
                    $this->assertEquals($domain, $route['domain'], 'Domain should match');
                }

                // Now test that the same configuration works with fluent syntax
                Router::resetInstance();
                Router::reset();

                Router::$method($url, $handler)
                    ->middleware($middleware)
                    ->name($name)
                    ->domain($domain);

                $fluentRoutes = Router::getRoutes();
                $this->assertCount(1, $fluentRoutes, 'Fluent syntax should register exactly one route');

                $fluentRoute = $fluentRoutes[0];

                // Both syntaxes should produce identical route configurations
                $this->assertEquals($route['method'], $fluentRoute['method'], 'Methods should be identical');
                $this->assertEquals($route['middleware'], $fluentRoute['middleware'], 'Middleware should be identical');
                
                if (!empty($name)) {
                    $this->assertEquals($route['name'], $fluentRoute['name'], 'Names should be identical');
                }
                
                if (!empty($domain)) {
                    $this->assertEquals($route['domain'], $fluentRoute['domain'], 'Domains should be identical');
                }
            });
    }

    /**
     * Unit test: Router::prefix() returns PendingGroup
     * _Requirements: 7.1_
     */
    public function testRouterPrefixReturnsPendingGroup(): void
    {
        $result = Router::prefix('/api');
        
        $this->assertInstanceOf(
            PendingGroup::class,
            $result,
            'Router::prefix() should return a PendingGroup instance'
        );
    }

    /**
     * Unit test: Router::middleware() returns PendingGroup
     * _Requirements: 7.2_
     */
    public function testRouterMiddlewareReturnsPendingGroup(): void
    {
        $result = Router::middleware(['AuthMiddleware', 'LogMiddleware']);
        
        $this->assertInstanceOf(
            PendingGroup::class,
            $result,
            'Router::middleware() should return a PendingGroup instance'
        );
    }

    /**
     * Unit test: Router::domain() returns PendingGroup
     * _Requirements: 7.3_
     */
    public function testRouterDomainReturnsPendingGroup(): void
    {
        $result = Router::domain('api.example.com');
        
        $this->assertInstanceOf(
            PendingGroup::class,
            $result,
            'Router::domain() should return a PendingGroup instance'
        );
    }

    /**
     * Unit test: Router::name() returns PendingGroup
     * _Requirements: 7.4_
     */
    public function testRouterNameReturnsPendingGroup(): void
    {
        $result = Router::name('api');
        
        $this->assertInstanceOf(
            PendingGroup::class,
            $result,
            'Router::name() should return a PendingGroup instance'
        );
    }

    /**
     * Unit test: Verify all Router facade fluent methods can be chained
     * _Requirements: 7.1, 7.2, 7.3, 7.4_
     */
    public function testRouterFacadeFluentMethodsCanBeChained(): void
    {
        // Test that we can chain all fluent methods together
        $result = Router::prefix('/api')
            ->middleware(['AuthMiddleware'])
            ->domain('api.example.com')
            ->name('api');
        
        $this->assertInstanceOf(
            PendingGroup::class,
            $result,
            'Chained Router facade methods should return PendingGroup'
        );
        
        // Test that the group can be executed with a callback
        $result->group(function () {
            Router::get('/users', fn() => ['users' => []]);
        });
        
        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        
        $route = $routes[0];
        $this->assertEquals('/api/users', $route['path']);
        $this->assertEquals(['AuthMiddleware'], $route['middleware']);
        $this->assertEquals('api.example.com', $route['domain']);
        $this->assertEquals('api', $route['name']);
    }
}
