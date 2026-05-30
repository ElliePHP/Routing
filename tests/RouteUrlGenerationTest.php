<?php

namespace ElliePHP\Tests\Routing;

use ElliePHP\Components\Routing\Router;
use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use PHPUnit\Framework\TestCase;

class RouteUrlGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        Router::resetInstance();
        Router::configure(['base_domain' => 'localhost']);
    }

    public function testGenerateUrlForBasicRoute()
    {
        Router::get('/users', function() {}, ['name' => 'users.index']);
        
        $url = Router::route('users.index', [], false);
        $this->assertEquals('/users', $url);
    }

    public function testGenerateUrlWithParameters()
    {
        Router::get('/users/{id}', function() {}, ['name' => 'users.show']);
        
        $url = Router::route('users.show', ['id' => 123], false);
        $this->assertEquals('/users/123', $url);
    }

    public function testGenerateUrlWithMultipleParameters()
    {
        Router::get('/posts/{post_id}/comments/{comment_id}', function() {}, ['name' => 'comments.show']);
        
        $url = Router::route('comments.show', ['post_id' => 1, 'comment_id' => 2], false);
        $this->assertEquals('/posts/1/comments/2', $url);
    }

    public function testGenerateUrlWithOptionalParameters()
    {
        Router::get('/archive/[{year}]', function() {}, ['name' => 'archive']);
        
        $url = Router::route('archive', ['year' => 2023], false);
        $this->assertEquals('/archive/2023', $url);
        
        $url = Router::route('archive', [], false);
        $this->assertEquals('/archive/', $url);
    }

    public function testGenerateUrlWithLaravelOptionalParameters()
    {
        Router::get('/posts/{id?}', function() {}, ['name' => 'posts.show']);
        
        $url = Router::route('posts.show', ['id' => 123], false);
        $this->assertEquals('/posts/123', $url);
        
        $url = Router::route('posts.show', [], false);
        $this->assertEquals('/posts/', $url);
    }

    public function testGlobalRouteHelper()
    {
        Router::get('/global', function() {}, ['name' => 'global']);
        
        $url = route('global', [], false);
        $this->assertEquals('/global', $url);
    }

    public function testGenerateUrlWithDomain()
    {
        Router::domain('api.example.com')->group(function() {
            Router::get('/users', function() {}, ['name' => 'api.users']);
        });
        
        $url = Router::route('api.users');
        $this->assertEquals('https://api.example.com/users', $url);
    }

    public function testGenerateUrlWithDomainParameters()
    {
        Router::domain('{account}.example.com')->group(function() {
            Router::get('/users', function() {}, ['name' => 'account.users']);
        });
        
        $url = Router::route('account.users', ['account' => 'acme']);
        $this->assertEquals('https://acme.example.com/users', $url);
    }

    public function testGenerateUrlThrowsExceptionIfRouteNotFound()
    {
        $this->expectException(RouteNotFoundException::class);
        Router::route('non.existent');
    }

    public function testGenerateUrlThrowsExceptionIfRequiredParameterMissing()
    {
        Router::get('/users/{id}', function() {}, ['name' => 'users.show']);
        
        $this->expectException(\InvalidArgumentException::class);
        Router::route('users.show');
    }

    public function testGenerateUrlWithQueryParameters()
    {
        Router::get('/users', function() {}, ['name' => 'users.index']);
        
        $url = Router::route('users.index', ['page' => 1, 'sort' => 'asc'], false);
        $this->assertEquals('/users?page=1&sort=asc', $url);
    }

    public function testGenerateUrlWithParametersAndQueryParameters()
    {
        Router::get('/users/{id}', function() {}, ['name' => 'users.show']);
        
        $url = Router::route('users.show', ['id' => 123, 'extra' => 'info'], false);
        $this->assertEquals('/users/123?extra=info', $url);
    }

    public function testFluentRouteNaming()
    {
        Router::get('/fluent', function() {})->name('fluent.route');
        
        $url = Router::route('fluent.route');
        $this->assertEquals('https://localhost/fluent', $url);
    }
}
