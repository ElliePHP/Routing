<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\RouteCache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private RouteCache $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/router_test_' . uniqid('', true);
        mkdir($this->cacheDir);
        $this->cache = new RouteCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testCacheDoesNotExistInitially(): void
    {
        $this->assertFalse($this->cache->exists());
    }

    public function testSaveAndLoadCache(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/test', 'handler' => 'TestHandler']
        ];

        $this->cache->save($routes);
        $this->assertTrue($this->cache->exists());

        $loaded = $this->cache->load();
        $this->assertEquals($routes, $loaded);
    }

    public function testClearCache(): void
    {
        $routes = [['method' => 'GET', 'path' => '/test']];
        $this->cache->save($routes);
        $this->assertTrue($this->cache->exists());

        $this->cache->clear();
        $this->assertFalse($this->cache->exists());
    }

    public function testGetCacheFile(): void
    {
        $cacheFile = $this->cache->getCacheFile();
        $this->assertStringContainsString('ellie_routes_', $cacheFile);
        $this->assertStringEndsWith('.cache', $cacheFile);
    }
}
