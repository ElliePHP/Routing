<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\ErrorFormatterInterface;
use ElliePHP\Components\Routing\Core\RouterConfigurationBuilder;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use ElliePHP\Components\Routing\Router;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Throwable;

class RouterConfigurationBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        Router::resetInstance();
        Router::configure(['base_domain' => 'localhost']);
        Router::reset();
    }

    /**
     * Test individual configuration methods with known inputs
     * _Requirements: 2.1, 2.2, 2.3_
     */
    public function testDebugModeConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test enabling debug mode explicitly
        $result = $builder->debugMode(true);
        $this->assertSame($builder, $result, 'debugMode() should return same builder instance');
        
        // Test disabling debug mode
        $result = $builder->debugMode(false);
        $this->assertSame($builder, $result, 'debugMode(false) should return same builder instance');
        
        // Test default parameter (should enable debug mode)
        $result = $builder->debugMode();
        $this->assertSame($builder, $result, 'debugMode() without parameters should return same builder instance');
    }

    /**
     * Test cache configuration methods
     * _Requirements: 3.1, 3.2_
     */
    public function testCacheConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test enabling cache
        $result = $builder->enableCache();
        $this->assertSame($builder, $result, 'enableCache() should return same builder instance');
        
        // Test disabling cache
        $result = $builder->disableCache();
        $this->assertSame($builder, $result, 'disableCache() should return same builder instance');
        
        // Test setting cache directory
        $result = $builder->cacheDirectory('/tmp/cache');
        $this->assertSame($builder, $result, 'cacheDirectory() should return same builder instance');
    }

    /**
     * Test routes directory configuration
     * _Requirements: 2.3_
     */
    public function testRoutesDirectoryConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        $result = $builder->routesDirectory('/app/routes');
        $this->assertSame($builder, $result, 'routesDirectory() should return same builder instance');
    }

    /**
     * Test middleware configuration methods
     * _Requirements: 4.4_
     */
    public function testMiddlewareConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test setting global middleware array
        $middleware = ['AuthMiddleware', 'LogMiddleware'];
        $result = $builder->globalMiddleware($middleware);
        $this->assertSame($builder, $result, 'globalMiddleware() should return same builder instance');
        
        // Test adding single middleware
        $result = $builder->addGlobalMiddleware('CorsMiddleware');
        $this->assertSame($builder, $result, 'addGlobalMiddleware() should return same builder instance');
        
        // Test adding middleware object
        $middlewareObject = new class {
            public function handle() {}
        };
        $result = $builder->addGlobalMiddleware($middlewareObject);
        $this->assertSame($builder, $result, 'addGlobalMiddleware() with object should return same builder instance');
    }

    /**
     * Test domain configuration methods
     * _Requirements: 4.4_
     */
    public function testDomainConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test setting allowed domains
        $domains = ['example.com', 'api.example.com'];
        $result = $builder->allowedDomains($domains);
        $this->assertSame($builder, $result, 'allowedDomains() should return same builder instance');
        
        // Test enabling domain enforcement
        $result = $builder->enforceDomain(true);
        $this->assertSame($builder, $result, 'enforceDomain(true) should return same builder instance');
        
        // Test disabling domain enforcement
        $result = $builder->enforceDomain(false);
        $this->assertSame($builder, $result, 'enforceDomain(false) should return same builder instance');
        
        // Test default parameter (should enable enforcement)
        $result = $builder->enforceDomain();
        $this->assertSame($builder, $result, 'enforceDomain() without parameters should return same builder instance');
    }

    /**
     * Test container and error formatter configuration
     * _Requirements: 4.4_
     */
    public function testContainerAndErrorFormatterConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test setting container
        $container = $this->createMock(ContainerInterface::class);
        $result = $builder->container($container);
        $this->assertSame($builder, $result, 'container() should return same builder instance');
        
        // Test setting error formatter
        $errorFormatter = $this->createMock(ErrorFormatterInterface::class);
        $result = $builder->errorFormatter($errorFormatter);
        $this->assertSame($builder, $result, 'errorFormatter() should return same builder instance');
    }

    public function testBaseDomainConfiguration(): void
    {
        $builder = new RouterConfigurationBuilder();

        $result = $builder->baseDomain('example.com');
        $this->assertSame($builder, $result, 'baseDomain() should return same builder instance');
    }

    public function testMissingBaseDomainValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('base_domain is required');
        $builder->build();
    }

    /**
     * Test terminal methods (build and apply)
     * _Requirements: 2.1, 2.2, 2.3_
     */
    public function testTerminalMethods(): void
    {
        Router::resetInstance();
        
        $builder = Router::configure();
        $builder->debugMode(true);
        $builder->baseDomain('localhost');
        
        // Test build() method
        $builder->build();
        
        // After build(), router should be configured
        $this->assertTrue(Router::isDebugMode(), 'Debug mode should be enabled after build()');
        
        // Test apply() method (alias for build)
        Router::resetInstance();
        $builder2 = Router::configure();
        $builder2->debugMode(false);
        $builder2->baseDomain('localhost');
        $builder2->apply();
        
        $this->assertFalse(Router::isDebugMode(), 'Debug mode should be disabled after apply()');
    }

    /**
     * Test edge cases with empty arrays
     * _Requirements: 4.4_
     */
    public function testEmptyArrayEdgeCases(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test empty middleware array
        $result = $builder->globalMiddleware([]);
        $this->assertSame($builder, $result, 'globalMiddleware([]) should return same builder instance');
        
        // Test empty domains array
        $result = $builder->allowedDomains([]);
        $this->assertSame($builder, $result, 'allowedDomains([]) should return same builder instance');
        
        // Should be able to build with empty arrays
        Router::resetInstance();
        $builder->baseDomain('localhost');
        $builder->build();
        $this->assertTrue(true, 'Should be able to build with empty arrays');
    }

    /**
     * Test boundary conditions for paths
     * _Requirements: 2.3_
     */
    public function testPathBoundaryConditions(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Test root directory
        $result = $builder->routesDirectory('/');
        $this->assertSame($builder, $result, 'routesDirectory("/") should return same builder instance');
        
        // Test current directory
        $result = $builder->cacheDirectory('./cache');
        $this->assertSame($builder, $result, 'cacheDirectory("./cache") should return same builder instance');
        
        // Test absolute path
        $result = $builder->routesDirectory('/var/www/routes');
        $this->assertSame($builder, $result, 'Absolute path should return same builder instance');
    }

    /**
     * Test error conditions - invalid path traversal
     * _Requirements: 2.3_
     */
    public function testPathTraversalValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Test path traversal in routes directory
        $builder->routesDirectory('/app/../../../etc');
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Path traversal detected in routes directory');
        $builder->build();
    }

    /**
     * Test error conditions - invalid path traversal in cache directory
     * _Requirements: 3.2_
     */
    public function testCachePathTraversalValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Test path traversal in cache directory
        $builder->cacheDirectory('/tmp/../../../etc');
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Path traversal detected in cache directory');
        $builder->build();
    }

    /**
     * Test error conditions - invalid error formatter
     * _Requirements: 4.4_
     */
    public function testInvalidErrorFormatterValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Use reflection to set invalid error formatter directly
        $reflection = new \ReflectionClass($builder);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($builder);
        $config['error_formatter'] = new \stdClass(); // Invalid formatter
        $configProperty->setValue($builder, $config);
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Error formatter must implement ErrorFormatterInterface');
        $builder->build();
    }

    /**
     * Test error conditions - invalid container
     * _Requirements: 4.4_
     */
    public function testInvalidContainerValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Use reflection to set invalid container directly
        $reflection = new \ReflectionClass($builder);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($builder);
        $config['container'] = new \stdClass(); // Invalid container
        $configProperty->setValue($builder, $config);
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Container must implement PSR-11 ContainerInterface');
        $builder->build();
    }

    /**
     * Test error conditions - invalid middleware types
     * _Requirements: 4.4_
     */
    public function testInvalidMiddlewareValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Use reflection to set invalid middleware directly
        $reflection = new \ReflectionClass($builder);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($builder);
        $config['global_middleware'] = [123]; // Invalid middleware type
        $configProperty->setValue($builder, $config);
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Invalid middleware at index 0: must be string, object, or callable');
        $builder->build();
    }

    /**
     * Test error conditions - invalid domain types
     * _Requirements: 4.4_
     */
    public function testInvalidDomainValidation(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Use reflection to set invalid domain directly
        $reflection = new \ReflectionClass($builder);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($builder);
        $config['allowed_domains'] = ['']; // Empty domain string
        $configProperty->setValue($builder, $config);
        $builder->baseDomain('localhost');
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Invalid domain at index 0: must be non-empty string');
        $builder->build();
    }

    /**
     * Test integration with Router facade - fluent configuration
     * _Requirements: 2.1, 2.2, 2.3_
     */
    public function testRouterFacadeIntegration(): void
    {
        Router::resetInstance();
        
        // Test that Router::configure() returns RouterConfigurationBuilder
        $builder = Router::configure();
        $this->assertInstanceOf(RouterConfigurationBuilder::class, $builder);
        
        // Test fluent configuration through Router facade
        Router::configure()
            ->debugMode(true)
            ->enableCache()
            ->routesDirectory('/')
            ->globalMiddleware(['TestMiddleware'])
            ->baseDomain('localhost')
            ->build();
        
        // Verify configuration was applied
        $this->assertTrue(Router::isDebugMode());
        // Note: Cache should be disabled because debug mode is enabled
        $this->assertFalse(Router::isCacheEnabled());
    }

    /**
     * Test integration - configuration after initialization throws exception
     * _Requirements: 2.1_
     */
    public function testConfigurationAfterInitializationError(): void
    {
        Router::resetInstance();
        
        // Initialize router by getting instance
        Router::configure(['base_domain' => 'localhost']);
        Router::getInstance();
        
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot configure router after it has been initialized');
        
        Router::configure();
    }

    /**
     * Test method chaining preserves builder identity
     * _Requirements: 2.1, 2.2, 2.3_
     */
    public function testMethodChainingPreservesIdentity(): void
    {
        $builder = new RouterConfigurationBuilder();
        $originalBuilder = $builder;
        
        $result = $builder
            ->debugMode(true)
            ->enableCache()
            ->routesDirectory('/app')
            ->globalMiddleware(['Auth'])
            ->allowedDomains(['example.com'])
            ->enforceDomain(true);
        
        $this->assertSame($originalBuilder, $result, 'All chained methods should return the same builder instance');
    }

    /**
     * Test last value wins for repeated calls
     * _Requirements: 2.1, 2.2_
     */
    public function testLastValueWinsForRepeatedCalls(): void
    {
        Router::resetInstance();
        
        Router::configure()
            ->debugMode(true)
            ->debugMode(false)  // This should win
            ->enableCache()
            ->disableCache()    // This should win
            ->baseDomain('localhost')
            ->build();
        
        $this->assertFalse(Router::isDebugMode(), 'Last debugMode(false) call should win');
        $this->assertFalse(Router::isCacheEnabled(), 'Last disableCache() call should win');
    }

    /**
     * Test middleware accumulation with addGlobalMiddleware
     * _Requirements: 4.4_
     */
    public function testMiddlewareAccumulation(): void
    {
        $builder = new RouterConfigurationBuilder();
        
        // Start with empty middleware, then add multiple
        $builder
            ->addGlobalMiddleware('FirstMiddleware')
            ->addGlobalMiddleware('SecondMiddleware')
            ->addGlobalMiddleware('ThirdMiddleware');
        
        // Use reflection to verify middleware accumulation
        $reflection = new \ReflectionClass($builder);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($builder);
        
        $expectedMiddleware = ['FirstMiddleware', 'SecondMiddleware', 'ThirdMiddleware'];
        $this->assertEquals($expectedMiddleware, $config['global_middleware'], 'Middleware should accumulate in order');
    }

    /**
     * Test configuration merging with existing Router config
     * _Requirements: 2.1, 2.2, 2.3_
     */
    public function testConfigurationMerging(): void
    {
        Router::resetInstance();
        
        // Set initial configuration via array
        Router::configure([
            'debug_mode' => false,
            'global_middleware' => ['ExistingMiddleware'],
            'base_domain' => 'localhost',
        ]);
        
        Router::resetInstance(); // Reset instance but keep config
        
        // Add fluent configuration
        Router::configure()
            ->debugMode(true)  // Should override existing
            ->addGlobalMiddleware('NewMiddleware')  // Should merge with existing
            ->baseDomain('localhost')
            ->build();
        
        $this->assertTrue(Router::isDebugMode(), 'Fluent config should override array config');
    }

    /**
     * Test debug mode disables cache (configuration rule)
     * _Requirements: 2.1, 3.1_
     */
    public function testDebugModeDisablesCache(): void
    {
        Router::resetInstance();
        
        Router::configure()
            ->enableCache()     // Enable cache first
            ->debugMode(true)   // This should disable cache
            ->baseDomain('localhost')
            ->build();
        
        $this->assertTrue(Router::isDebugMode(), 'Debug mode should be enabled');
        $this->assertFalse(Router::isCacheEnabled(), 'Cache should be disabled when debug mode is enabled');
    }

    /**
     * Test valid middleware types are accepted
     * _Requirements: 4.4_
     */
    public function testValidMiddlewareTypes(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Test string middleware
        $builder->addGlobalMiddleware('StringMiddleware');
        
        // Test object middleware
        $objectMiddleware = new class {
            public function handle() {}
        };
        $builder->addGlobalMiddleware($objectMiddleware);
        
        // Test callable middleware
        $callableMiddleware = function() {};
        $builder->addGlobalMiddleware($callableMiddleware);
        $builder->baseDomain('localhost');
        
        // Should build without errors
        $builder->build();
        $this->assertTrue(true, 'Valid middleware types should be accepted');
    }

    /**
     * Test routes directory validation for existing directories
     * _Requirements: 2.3_
     */
    public function testRoutesDirectoryValidationExistingPath(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Test with existing directory (current directory should exist)
        $builder->routesDirectory(__DIR__);
        $builder->baseDomain('localhost');
        
        // Should build without errors
        $builder->build();
        $this->assertTrue(true, 'Existing readable directory should be valid');
    }

    /**
     * Test cache directory validation for existing directories
     * _Requirements: 3.2_
     */
    public function testCacheDirectoryValidationExistingPath(): void
    {
        Router::resetInstance();
        $builder = Router::configure();
        
        // Create a temporary directory for testing
        $tempDir = sys_get_temp_dir() . '/router_test_' . uniqid();
        mkdir($tempDir);
        
        try {
            $builder->cacheDirectory($tempDir);
            $builder->baseDomain('localhost');
            
            // Should build without errors
            $builder->build();
            $this->assertTrue(true, 'Existing writable directory should be valid');
        } finally {
            // Clean up
            rmdir($tempDir);
        }
    }
}