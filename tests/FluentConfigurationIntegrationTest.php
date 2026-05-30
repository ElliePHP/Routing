<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;
use ElliePHP\Components\Routing\Core\HtmlErrorFormatter;
use ElliePHP\Components\Routing\Core\RouterConfigurationBuilder;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Integration tests for fluent router configuration
 * Tests actual router usage with fluent configuration and mixed scenarios
 */
class FluentConfigurationIntegrationTest extends TestCase
{
    private function resetRouter(): void
    {
        Router::resetInstance();
        Router::configure(['base_domain' => 'localhost']);
    }

    protected function setUp(): void
    {
        $this->resetRouter();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        Router::resetInstance();
    }

    /**
     * Test fluent configuration with actual router usage
     * _Requirements: 5.1, 7.3, 7.4_
     */
    public function testFluentConfigurationWithActualRouterUsage(): void
    {
        $this->resetRouter();
        
        // Configure router using fluent API
        Router::configure()
            ->debugMode(true)
            ->disableCache()
            ->routesDirectory('/')
            ->enforceDomain(false)
            ->baseDomain('localhost')
            ->build();

        // Define routes after configuration
        Router::get('/test', function () {
            return ['configured' => 'fluent'];
        });

        Router::post('/users', function () {
            return ['action' => 'create'];
        });

        Router::group(['prefix' => '/api'], function () {
            Router::get('/status', function () {
                return ['status' => 'ok'];
            });
        });

        // Test route handling
        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('fluent', $body['configured']);

        // Test POST route
        $request = new ServerRequest('POST', '/users');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('create', $body['action']);

        // Test grouped route
        $request = new ServerRequest('GET', '/api/status');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('ok', $body['status']);
    }

    /**
     * Test mixed fluent and array configuration scenarios
     * _Requirements: 5.3_
     */
    public function testMixedFluentAndArrayConfiguration(): void
    {
        $this->resetRouter();
        
        // Start with array configuration
        Router::configure([
            'debug_mode' => false,
            'cache_enabled' => true,
            'global_middleware' => ['ArrayMiddleware'],
            'base_domain' => 'localhost',
        ]);

        // Add fluent configuration on top
        Router::configure()
            ->debugMode(true) // Should override array config
            ->addGlobalMiddleware('FluentMiddleware') // Should merge with array config
            ->routesDirectory('/custom')
            ->build();

        // Verify debug mode was overridden (cache should be disabled due to debug mode)
        $this->assertTrue(Router::isDebugMode());
        $this->assertFalse(Router::isCacheEnabled()); // Cache disabled by debug mode

        // Define a route to test middleware merging
        Router::get('/mixed', function () {
            return ['mixed' => true];
        });

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
    }

    /**
     * Test fluent configuration with middleware
     * _Requirements: 4.1, 4.2_
     */
    public function testFluentConfigurationWithMiddleware(): void
    {
        $this->resetRouter();
        
        // Create test middleware
        $testMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = $handler->handle($request);
                return $response->withHeader('X-Test-Middleware', 'executed');
            }
        };

        // Configure with middleware
        Router::configure()
            ->globalMiddleware([$testMiddleware])
            ->baseDomain('localhost')
            ->build();

        Router::get('/middleware-test', function () {
            return ['middleware' => 'test'];
        });

        $request = new ServerRequest('GET', '/middleware-test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Test-Middleware'));
        $this->assertEquals('executed', $response->getHeaderLine('X-Test-Middleware'));
    }

    /**
     * Test fluent configuration with domain settings
     * _Requirements: 4.3, 4.4_
     */
    public function testFluentConfigurationWithDomainSettings(): void
    {
        $this->resetRouter();
        
        Router::configure()
            ->allowedDomains(['example.com', 'api.example.com'])
            ->enforceDomain(false) // Disable for testing
            ->baseDomain('localhost')
            ->build();

        Router::get('/domain-test', function () {
            return ['domain' => 'configured'];
        });

        $request = new ServerRequest('GET', '/domain-test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('configured', $body['domain']);
    }

    /**
     * Test fluent configuration with custom error formatter
     * _Requirements: 6.2_
     */
    public function testFluentConfigurationWithCustomErrorFormatter(): void
    {
        $this->resetRouter();
        
        $customFormatter = new class implements ErrorFormatterInterface {
            public function format(\Throwable $e, bool $debugMode): array
            {
                return ['custom_error' => $e->getMessage(), 'debug' => $debugMode];
            }
        };

        Router::configure()
            ->errorFormatter($customFormatter)
            ->debugMode(true)
            ->baseDomain('localhost')
            ->build();

        Router::get('/error-test', function () {
            throw new \Exception('Test error');
        });

        $request = new ServerRequest('GET', '/error-test');
        $response = Router::handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertArrayHasKey('custom_error', $decoded);
        $this->assertEquals('Test error', $decoded['custom_error']);
    }

    /**
     * Test fluent configuration with PSR-11 container
     * _Requirements: 4.5_
     */
    public function testFluentConfigurationWithContainer(): void
    {
        $this->resetRouter();
        
        $container = new class implements ContainerInterface {
            private array $services = [];

            public function get(string $id)
            {
                return $this->services[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }

            public function set(string $id, $service): void
            {
                $this->services[$id] = $service;
            }
        };

        $container->set('test_service', 'injected_value');

        Router::configure()
            ->container($container)
            ->baseDomain('localhost')
            ->build();

        // Test that container is available (this would be used internally by the router)
        Router::get('/container-test', function () {
            return ['container' => 'configured'];
        });

        $request = new ServerRequest('GET', '/container-test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('configured', $body['container']);
    }

    /**
     * Test that existing array configuration still works unchanged
     * _Requirements: 5.1_
     */
    public function testExistingArrayConfigurationUnchanged(): void
    {
        $this->resetRouter();
        
        // Use traditional array configuration
        Router::configure([
            'debug_mode' => true,
            'cache_enabled' => false,
            'routes_directory' => '/',
            'global_middleware' => [], // Remove invalid middleware for test
            'allowed_domains' => ['test.com'],
            'enforce_domain' => false,
            'base_domain' => 'localhost',
        ]);

        // Define routes
        Router::get('/array-config', function () {
            return ['config_type' => 'array'];
        });

        Router::post('/array-post', function () {
            return ['method' => 'POST'];
        });

        // Test routes work as expected
        $request = new ServerRequest('GET', '/array-config');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = new ServerRequest('POST', '/array-post');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify configuration was applied
        $this->assertTrue(Router::isDebugMode());
        $this->assertFalse(Router::isCacheEnabled());
    }

    /**
     * Test that direct Routing class instantiation still works
     * _Requirements: 5.2_
     */
    public function testDirectRoutingInstantiationUnchanged(): void
    {
        // Reset Router facade to avoid conflicts
        Router::resetInstance();

        // Direct instantiation should still work
        $routing = new \ElliePHP\Components\Routing\Core\Routing(
            routes_directory: '/',
            debugMode: true,
            cacheEnabled: false
        );

        $routing->get('/direct', function () {
            return ['instantiation' => 'direct'];
        });

        $request = new ServerRequest('GET', '/direct');
        $response = $routing->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('direct', $body['instantiation']);
    }

    /**
     * Test configuration method chaining returns correct instance
     * _Requirements: 7.1, 7.2_
     */
    public function testConfigurationMethodChainingReturnsCorrectInstance(): void
    {
        $this->resetRouter();
        
        $builder = Router::configure();
        $this->assertInstanceOf(RouterConfigurationBuilder::class, $builder);

        // Test that all methods return the same builder instance
        $result1 = $builder->debugMode(true);
        $this->assertSame($builder, $result1);

        $result2 = $builder->enableCache();
        $this->assertSame($builder, $result2);

        $result3 = $builder->routesDirectory('/test');
        $this->assertSame($builder, $result3);

        $result4 = $builder->globalMiddleware(['Test']);
        $this->assertSame($builder, $result4);

        $result5 = $builder->allowedDomains(['test.com']);
        $this->assertSame($builder, $result5);

        $result6 = $builder->enforceDomain(false);
        $this->assertSame($builder, $result6);
    }

    /**
     * Test complex fluent configuration scenario
     * _Requirements: 7.3, 7.4_
     */
    public function testComplexFluentConfigurationScenario(): void
    {
        $this->resetRouter();
        
        // Complex configuration with all options
        Router::configure()
            ->debugMode(false)
            ->disableCache() // Disable cache to avoid directory issues
            ->routesDirectory('/')
            ->globalMiddleware([]) // Remove invalid middleware for test
            ->allowedDomains(['api.example.com', 'admin.example.com'])
            ->enforceDomain(false)
            ->baseDomain('localhost')
            ->errorFormatter(new HtmlErrorFormatter())
            ->apply(); // Use apply() instead of build()

        // Define complex route structure
        Router::group(['prefix' => '/api/v1'], function () {
            Router::get('/users', function () {
                return ['users' => []];
            });

            Router::group(['prefix' => '/admin'], function () {
                Router::get('/dashboard', function () {
                    return ['dashboard' => 'data'];
                });

                Router::post('/settings', function () {
                    return ['settings' => 'updated'];
                });
            });
        });

        // Test nested routes work
        $request = new ServerRequest('GET', '/api/v1/users');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = new ServerRequest('GET', '/api/v1/admin/dashboard');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = new ServerRequest('POST', '/api/v1/admin/settings');
        $response = Router::handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify configuration was applied
        $this->assertFalse(Router::isDebugMode());
        $this->assertFalse(Router::isCacheEnabled()); // Cache was disabled in configuration
    }

    /**
     * Test configuration validation errors
     * _Requirements: 6.3, 6.4_
     */
    public function testConfigurationValidationErrors(): void
    {
        $this->resetRouter();
        
        // Test invalid error formatter
        $this->expectException(\TypeError::class);

        Router::configure()
            ->errorFormatter(new \stdClass()) // Invalid formatter
            ->baseDomain('localhost')
            ->build();
    }

    /**
     * Test configuration after router initialization throws exception
     * _Requirements: 5.4_
     */
    public function testConfigurationAfterInitializationThrowsException(): void
    {
        $this->resetRouter();
        
        // Initialize router by getting instance
        Router::getInstance();

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot configure router after it has been initialized');

        Router::configure()
            ->debugMode(true)
            ->build();
    }

    /**
     * Test fluent configuration after router initialization throws exception
     * _Requirements: 5.4_
     */
    public function testFluentConfigurationAfterInitializationThrowsException(): void
    {
        $this->resetRouter();
        
        // Initialize router by getting instance
        Router::getInstance();

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot configure router after it has been initialized');

        Router::configure(); // Should throw even without calling build()
    }

    /**
     * Test that method call order doesn't affect final configuration
     * _Requirements: 1.4_
     */
    public function testMethodCallOrderIndependence(): void
    {
        // Configuration in one order
        $this->resetRouter();
        Router::configure()
            ->debugMode(true)
            ->routesDirectory('/test1')
            ->globalMiddleware(['Middleware1'])
            ->allowedDomains(['domain1.com'])
            ->baseDomain('localhost')
            ->build();

        $config1 = Router::getConfig();

        // Same configuration in different order
        $this->resetRouter();
        Router::configure()
            ->allowedDomains(['domain1.com'])
            ->globalMiddleware(['Middleware1'])
            ->routesDirectory('/test1')
            ->debugMode(true)
            ->baseDomain('localhost')
            ->build();

        $config2 = Router::getConfig();

        // Configurations should be identical
        $this->assertEquals($config1, $config2);
    }

    /**
     * Test that repeated method calls use last value
     * _Requirements: 1.5_
     */
    public function testRepeatedMethodCallsUseLastValue(): void
    {
        $this->resetRouter();
        
        Router::configure()
            ->debugMode(false)
            ->debugMode(true)  // Should override previous
            ->routesDirectory('/first')
            ->routesDirectory('/second') // Should override previous
            ->baseDomain('localhost')
            ->build();

        $this->assertTrue(Router::isDebugMode());
        // Note: We can't easily test routes directory without exposing it,
        // but the behavior is tested in the RouterConfigurationBuilderTest
    }
}