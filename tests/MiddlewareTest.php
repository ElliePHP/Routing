<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Test-Middleware', 'executed');
    }
}

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        Router::resetInstance();
        Router::reset();
    }

    public function testMiddlewareExecution(): void
    {
        Router::get('/test', function () {
            return ['message' => 'success'];
        }, [
            'middleware' => [TestMiddleware::class]
        ]);

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertTrue($response->hasHeader('X-Test-Middleware'));
        $this->assertEquals('executed', $response->getHeaderLine('X-Test-Middleware'));
    }

    public function testClosureMiddleware(): void
    {
        Router::get('/test', function () {
            return ['message' => 'success'];
        }, [
            'middleware' => [
                function ($request, $next) {
                    $response = $next($request);
                    return $response->withHeader('X-Closure', 'works');
                }
            ]
        ]);

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertTrue($response->hasHeader('X-Closure'));
    }

    public function testMultipleMiddleware(): void
    {
        Router::get('/test', function () {
            return ['message' => 'success'];
        }, [
            'middleware' => [
                function ($request, $next) {
                    $response = $next($request);
                    return $response->withHeader('X-First', '1');
                },
                function ($request, $next) {
                    $response = $next($request);
                    return $response->withHeader('X-Second', '2');
                }
            ]
        ]);

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertTrue($response->hasHeader('X-First'));
        $this->assertTrue($response->hasHeader('X-Second'));
    }

    public function testGroupMiddleware(): void
    {
        Router::group(['middleware' => [TestMiddleware::class]], function () {
            Router::get('/test', function () {
                return ['message' => 'success'];
            });
        });

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertTrue($response->hasHeader('X-Test-Middleware'));
    }

    public function testMiddlewareOrder(): void
    {
        $order = [];

        Router::get('/test', function () use (&$order) {
            $order[] = 'handler';
            return ['message' => 'success'];
        }, [
            'middleware' => [
                function ($request, $next) use (&$order) {
                    $order[] = 'middleware1-before';
                    $response = $next($request);
                    $order[] = 'middleware1-after';
                    return $response;
                },
                function ($request, $next) use (&$order) {
                    $order[] = 'middleware2-before';
                    $response = $next($request);
                    $order[] = 'middleware2-after';
                    return $response;
                }
            ]
        ]);

        $request = new ServerRequest('GET', '/test');
        Router::handle($request);

        $expected = [
            'middleware1-before',
            'middleware2-before',
            'handler',
            'middleware2-after',
            'middleware1-after'
        ];

        $this->assertEquals($expected, $order);
    }
}
