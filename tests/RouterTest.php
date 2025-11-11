<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::resetInstance();
        Router::reset();
    }

    public function testBasicGetRoute(): void
    {
        Router::get('/test', function () {
            return ['message' => 'success'];
        });

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('success', $body['message']);
    }

    public function testRouteWithParameters(): void
    {
        Router::get('/users/{id}', function ($request, $params) {
            return ['user_id' => $params['id']];
        });

        $request = new ServerRequest('GET', '/users/123');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('123', $body['user_id']);
    }

    public function testMultipleParameters(): void
    {
        Router::get('/users/{userId}/posts/{postId}', function ($request, $params) {
            return [
                'user_id' => $params['userId'],
                'post_id' => $params['postId']
            ];
        });

        $request = new ServerRequest('GET', '/users/5/posts/10');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('5', $body['user_id']);
        $this->assertEquals('10', $body['post_id']);
    }

    public function testRouteGroup(): void
    {
        Router::group(['prefix' => '/api'], function () {
            Router::get('/users', function () {
                return ['users' => []];
            });
        });

        $request = new ServerRequest('GET', '/api/users');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNestedRouteGroups(): void
    {
        Router::group(['prefix' => '/api'], function () {
            Router::group(['prefix' => '/v1'], function () {
                Router::get('/users', function () {
                    return ['version' => 'v1'];
                });
            });
        });

        $request = new ServerRequest('GET', '/api/v1/users');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('v1', $body['version']);
    }

    public function testPostRoute(): void
    {
        Router::post('/users', function () {
            return ['created' => true];
        });

        $request = new ServerRequest('POST', '/users');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPutRoute(): void
    {
        Router::put('/users/{id}', function ($request, $params) {
            return ['updated' => $params['id']];
        });

        $request = new ServerRequest('PUT', '/users/1');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('1', $body['updated']);
    }

    public function testDeleteRoute(): void
    {
        Router::delete('/users/{id}', function ($request, $params) {
            return ['deleted' => $params['id']];
        });

        $request = new ServerRequest('DELETE', '/users/1');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('1', $body['deleted']);
    }

    public function testPatchRoute(): void
    {
        Router::patch('/users/{id}', function ($request, $params) {
            return ['patched' => $params['id']];
        });

        $request = new ServerRequest('PATCH', '/users/1');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('1', $body['patched']);
    }

    public function testRouteNotFound(): void
    {
        Router::get('/exists', function () {
            return ['ok' => true];
        });

        $request = new ServerRequest('GET', '/does-not-exist');
        $response = Router::handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMethodNotAllowed(): void
    {
        Router::get('/users', function () {
            return ['users' => []];
        });

        $request = new ServerRequest('POST', '/users');
        $response = Router::handle($request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testTrailingSlashNormalization(): void
    {
        Router::get('/users', function () {
            return ['normalized' => true];
        });

        $request = new ServerRequest('GET', '/users/');
        $response = Router::handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRootRoute(): void
    {
        Router::get('/', function () {
            return ['root' => true];
        });

        $request = new ServerRequest('GET', '/');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['root']);
    }

    public function testGetRoutes(): void
    {
        Router::get('/test1', function () {});
        Router::post('/test2', function () {});

        $routes = Router::getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('POST', $routes[1]['method']);
    }

    public function testRegisterRoutes(): void
    {
        Router::registerRoutes([
            [
                'method' => 'GET',
                'path' => '/test',
                'handler' => function () {
                    return ['registered' => true];
                }
            ]
        ]);

        $request = new ServerRequest('GET', '/test');
        $response = Router::handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['registered']);
    }

    public function testReset(): void
    {
        Router::get('/test', function () {});
        $this->assertCount(1, Router::getRoutes());

        Router::reset();
        $this->assertCount(0, Router::getRoutes());
    }
}
