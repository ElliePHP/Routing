<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Tests;

use ElliePHP\Components\Routing\Core\Routing;
use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class DomainRoutingTest extends TestCase
{
    private Routing $router;
    private string $tempDir;

    protected function setUp(): void
    {
        // Create a temporary directory for routes
        $this->tempDir = sys_get_temp_dir() . '/ellie_test_routes_' . uniqid();
        mkdir($this->tempDir);
        file_put_contents($this->tempDir . '/empty.php', '<?php // Empty route file');
        
        $this->router = new Routing(
            routes_directory: $this->tempDir,
            debugMode: false,
            cacheEnabled: false,
            enforceDomain: false,
            allowedDomains: ['example.com', 'api.example.com', '{tenant}.example.com']
        );
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (file_exists($this->tempDir . '/empty.php')) {
            unlink($this->tempDir . '/empty.php');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testRouteWithSpecificDomain(): void
    {
        $this->router->get('/', function () {
            return ['message' => 'main'];
        }, ['domain' => 'example.com']);

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('main', $body['message']);
    }

    public function testRouteWithSubdomain(): void
    {
        $this->router->get('/users', function () {
            return ['message' => 'api users'];
        }, ['domain' => 'api.example.com']);

        $request = new ServerRequest('GET', 'http://api.example.com/users');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('api users', $body['message']);
    }

    public function testRouteWithDomainParameter(): void
    {
        $this->router->get('/dashboard', function ($request, $params) {
            return ['tenant' => $params['tenant'] ?? 'none'];
        }, ['domain' => '{tenant}.example.com']);

        $request = new ServerRequest('GET', 'http://acme.example.com/dashboard');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('acme', $body['tenant']);
    }

    public function testRouteWithDomainAndPathParameters(): void
    {
        $this->router->get('/users/{id}', function ($request, $params) {
            return [
                'tenant' => $params['tenant'] ?? 'none',
                'user_id' => $params['id']
            ];
        }, ['domain' => '{tenant}.example.com']);

        $request = new ServerRequest('GET', 'http://widgets.example.com/users/42');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('widgets', $body['tenant']);
        $this->assertEquals('42', $body['user_id']);
    }

    public function testDomainGroupRoutes(): void
    {
        $this->router->group(['domain' => 'admin.example.com'], function ($router) {
            $router->get('/dashboard', function () {
                return ['message' => 'admin dashboard'];
            });
            $router->get('/settings', function () {
                return ['message' => 'admin settings'];
            });
        });

        $request = new ServerRequest('GET', 'http://admin.example.com/dashboard');
        $response = $this->router->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = new ServerRequest('GET', 'http://admin.example.com/settings');
        $response = $this->router->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testWrongDomainReturns404(): void
    {
        $this->router->get('/admin', function () {
            return ['message' => 'admin'];
        }, ['domain' => 'admin.example.com']);

        $request = new ServerRequest('GET', 'http://example.com/admin');
        $response = $this->router->handle($request);
        
        // Should return 404 because domain doesn't match
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testEnforceDomainRejectsUnlistedDomains(): void
    {
        // Create a temporary directory for routes
        $tempDir = sys_get_temp_dir() . '/ellie_test_routes_enforce_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/empty.php', '<?php // Empty route file');
        
        $router = new Routing(
            routes_directory: $tempDir,
            debugMode: false,
            cacheEnabled: false,
            enforceDomain: true,
            allowedDomains: ['example.com', 'api.example.com']
        );

        $router->get('/', function () {
            return ['message' => 'home'];
        });

        $request = new ServerRequest('GET', 'http://evil.com/');
        $response = $router->handle($request);
        
        // Should return 403 because domain is not allowed
        $this->assertEquals(403, $response->getStatusCode());
        
        // Clean up
        unlink($tempDir . '/empty.php');
        rmdir($tempDir);
    }

    public function testRouteWithoutDomainMatchesAnyDomain(): void
    {
        $this->router->get('/public', function () {
            return ['message' => 'public'];
        });

        // Should work on any domain
        $request1 = new ServerRequest('GET', 'http://example.com/public');
        $response1 = $this->router->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        $request2 = new ServerRequest('GET', 'http://api.example.com/public');
        $response2 = $this->router->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testNestedDomainGroups(): void
    {
        $this->router->group(['domain' => 'api.example.com'], function ($router) {
            $router->group(['prefix' => '/v1'], function ($router) {
                $router->get('/users', function () {
                    return ['version' => 'v1', 'resource' => 'users'];
                });
            });
        });

        $request = new ServerRequest('GET', 'http://api.example.com/v1/users');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('v1', $body['version']);
    }

    public function testDomainParameterWithMultiplePlaceholders(): void
    {
        $this->router->get('/', function ($request, $params) {
            return [
                'subdomain' => $params['subdomain'] ?? 'none',
                'region' => $params['region'] ?? 'none'
            ];
        }, ['domain' => '{subdomain}.{region}.example.com']);

        $request = new ServerRequest('GET', 'http://app.us-east.example.com/');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('app', $body['subdomain']);
        $this->assertEquals('us-east', $body['region']);
    }
}
