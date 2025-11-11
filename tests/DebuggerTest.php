<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\RouteDebugger;
use PHPUnit\Framework\TestCase;

class DebuggerTest extends TestCase
{
    private RouteDebugger $debugger;

    protected function setUp(): void
    {
        $this->debugger = new RouteDebugger();
    }

    public function testFormatRoutes(): void
    {
        $routes = [
            [
                'method' => 'GET',
                'path' => '/users',
                'name' => 'users.index',
                'class' => 'UserController',
                'handler' => 'index',
                'middleware' => []
            ]
        ];

        $formatted = $this->debugger->formatRoutes($routes);

        $this->assertCount(1, $formatted);
        $this->assertEquals('GET', $formatted[0]['method']);
        $this->assertEquals('/users', $formatted[0]['path']);
        $this->assertEquals('users.index', $formatted[0]['name']);
    }

    public function testGenerateRouteTable(): void
    {
        $routes = [
            [
                'method' => 'GET',
                'path' => '/users',
                'name' => 'users.index',
                'class' => 'UserController',
                'handler' => 'index',
                'middleware' => []
            ]
        ];

        $table = $this->debugger->generateRouteTable($routes);

        $this->assertStringContainsString('GET', $table);
        $this->assertStringContainsString('/users', $table);
        $this->assertStringContainsString('users.index', $table);
        $this->assertStringContainsString('Total routes: 1', $table);
    }

    public function testGenerateRouteTableEmpty(): void
    {
        $table = $this->debugger->generateRouteTable([]);
        $this->assertStringContainsString('No routes registered', $table);
    }

    public function testGetTimingInfo(): void
    {
        $startTime = microtime(true);
        usleep(1000); // Sleep for 1ms
        $timing = $this->debugger->getTimingInfo($startTime);

        $this->assertArrayHasKey('start', $timing);
        $this->assertArrayHasKey('end', $timing);
        $this->assertArrayHasKey('duration_ms', $timing);
        $this->assertGreaterThan(0, $timing['duration_ms']);
    }
}
