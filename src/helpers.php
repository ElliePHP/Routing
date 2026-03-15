<?php

declare(strict_types=1);

use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Router;

if (!function_exists('route')) {
    /**
     * Generate a URL for a given route name.
     *
     * This helper function provides a convenient way to generate URLs for routes
     * defined in your application. It handles parameter replacement, domain
     * routing, and automatically appends any unused parameters as a query string.
     *
     * Usage Examples:
     * ```php
     * // Basic usage
     * route('home'); // Returns "http://localhost/"
     *
     * // Relative URL
     * route('home', [], false); // Returns "/"
     *
     * // With parameters
     * // Route: /users/{id}
     * route('users.show', ['id' => 123]); // Returns "http://localhost/users/123"
     *
     * // With extra parameters (query string)
     * route('users.index', ['page' => 2, 'sort' => 'desc']); 
     * // Returns "http://localhost/users?page=2&sort=desc"
     *
     * // With domain patterns
     * // Route: {account}.example.com/profile
     * route('profile', ['account' => 'john']); // Returns "http://john.example.com/profile"
     * ```
     *
     * @param string $name The name of the route to generate a URL for.
     * @param array $parameters An associative array of parameters to fill in the route's path or domain. 
     *                          Any parameters not used in the path will be appended as a query string.
     * @param bool $absolute Whether to return an absolute URL (with domain and scheme). Defaults to true.
     * @return string The generated URL.
     * @throws RouteNotFoundException If no route with the given name exists.
     * @throws InvalidArgumentException If required, parameters for the route are missing.
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return Router::route($name, $parameters, $absolute);
    }
}
