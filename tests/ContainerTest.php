<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\Routing;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ContainerTest extends TestCase
{
    public function testControllerResolvedFromContainer(): void
    {
        $container = new class implements ContainerInterface {
            private array $services = [];
            
            public function get(string $id): ContainerTestController
            {
                if ($id === ContainerTestController::class) {
                    $controller = new ContainerTestController();
                    $controller->fromContainer = true;
                    return $controller;
                }
                throw new \Exception("Service not found: $id");
            }
            
            public function has(string $id): bool
            {
                return $id === ContainerTestController::class;
            }
        };

        $router = new Routing(
            routes_directory: '/',
            container: $container
        );

        $router->get('/test', [ContainerTestController::class, 'index']);

        $request = new ServerRequest('GET', '/test');
        $response = $router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['from_container']);
    }

    public function testMiddlewareResolvedFromContainer(): void
    {
        $container = new class implements ContainerInterface {
            private array $services = [];
            
            public function get(string $id)
            {
                if ($id === ContainerTestMiddleware::class) {
                    return new ContainerTestMiddleware('from-container');
                }
                throw new \Exception("Service not found: $id");
            }
            
            public function has(string $id): bool
            {
                return $id === ContainerTestMiddleware::class;
            }
        };

        $router = new Routing(
            routes_directory: '/',
            container: $container
        );

        $router->get('/test', function() {
            return ['message' => 'success'];
        }, ['middleware' => [ContainerTestMiddleware::class]]);

        $request = new ServerRequest('GET', '/test');
        $response = $router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('from-container', $response->getHeaderLine('X-Test-Middleware'));
    }

    public function testGlobalMiddlewareResolvedFromContainer(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id)
            {
                if ($id === ContainerTestMiddleware::class) {
                    return new ContainerTestMiddleware('global-container');
                }
                throw new \Exception("Service not found: $id");
            }
            
            public function has(string $id): bool
            {
                return $id === ContainerTestMiddleware::class;
            }
        };

        $router = new Routing(
            routes_directory: '/',
            globalMiddleware: [ContainerTestMiddleware::class],
            container: $container
        );

        $router->get('/test', function() {
            return ['message' => 'success'];
        });

        $request = new ServerRequest('GET', '/test');
        $response = $router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('global-container', $response->getHeaderLine('X-Test-Middleware'));
    }

    public function testFallbackToNewInstanceWhenNotInContainer(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id)
            {
                throw new \Exception("Service not found: $id");
            }
            
            public function has(string $id): bool
            {
                return false;
            }
        };

        $router = new Routing(
            routes_directory: '/',
            container: $container
        );

        $router->get('/test', [ContainerTestController::class, 'index']);

        $request = new ServerRequest('GET', '/test');
        $response = $router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['from_container']);
    }
}

class ContainerTestController
{
    public bool $fromContainer = false;

    public function index(): array
    {
        return ['from_container' => $this->fromContainer];
    }
}

class ContainerTestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $source = 'default'
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $handler->handle($request)->withHeader('X-Test-Middleware', $this->source);
    }
}
