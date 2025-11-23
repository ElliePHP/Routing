<?php

namespace ElliePHP\Components\Routing;

use Closure;
use ElliePHP\Components\Routing\Core\PendingGroup;
use ElliePHP\Components\Routing\Core\PendingRoute;
use ElliePHP\Components\Routing\Core\Routing as EllieRouter;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Router Facade
 * 
 * Provides a static interface to the routing system.
 * 
 * @mixin EllieRouter
 * 
 * @method static PendingRoute|null get(string $url, Closure|callable|string|array $handler, array $options = []) Register a GET route
 * @method static PendingRoute|null post(string $url, Closure|callable|string|array $handler, array $options = []) Register a POST route
 * @method static PendingRoute|null put(string $url, Closure|callable|string|array $handler, array $options = []) Register a PUT route
 * @method static PendingRoute|null delete(string $url, Closure|callable|string|array $handler, array $options = []) Register a DELETE route
 * @method static PendingRoute|null patch(string $url, Closure|callable|string|array $handler, array $options = []) Register a PATCH route
 * @method static PendingGroup prefix(string $prefix) Create a PendingGroup with a prefix
 * @method static PendingGroup middleware(array $middleware) Create a PendingGroup with middleware
 * @method static PendingGroup domain(string $domain) Create a PendingGroup with a domain constraint
 * @method static PendingGroup name(string $name) Create a PendingGroup with a name prefix
 * @method static void group(array $options, callable $callback) Create a route group
 * @method static ResponseInterface handle(ServerRequestInterface $request) Handle an incoming request
 * @method static void reset() Reset router state
 * @method static array getRoutes() Get all registered routes
 * @method static array getFormattedRoutes() Get formatted routes for debugging
 * @method static string printRoutes() Print route table
 * @method static void clearCache() Clear route cache
 * @method static bool isDebugMode() Check if debug mode is enabled
 * @method static bool isCacheEnabled() Check if cache is enabled
 * @method static void registerRoutes(array $routes) Register routes from array
 * @method static void addRoute(string $method, string $url, string $class = "", Closure|callable|string|array|null $handler = null, array $middleware = [], ?string $name = null) Register a route with the router
 */
final class Router
{
    /**
     * The single instance of the router library.
     */
    private static ?EllieRouter $instance = null;

    /**
     * Configuration for the router instance
     */
    private static array $config = [
        'routes_directory' => '/',
        'debug_mode' => false,
        'cache_enabled' => false,
        'cache_directory' => null,
        'error_formatter' => null,
        'enforce_domain' => false,
        'allowed_domains' => [],
        'global_middleware' => [],
        'container' => null,
    ];

    /**
     * Configure the router before first use
     * 
     * @param array $config Configuration options:
     *   - routes_directory: Directory containing route files
     *   - debug_mode: Enable debug mode with detailed errors and timing
     *   - cache_enabled: Enable route caching for production
     *   - cache_directory: Directory for cache files
     *   - error_formatter: Custom error formatter instance
     *   - enforce_domain: Reject requests from domains not in allowed_domains
     *   - allowed_domains: Array of allowed domains (supports patterns like {tenant}.example.com)
     *   - global_middleware: Array of middleware classes to apply to all routes
     *   - container: PSR-11 container for dependency injection
     * 
     * @throws RouterException
     */
    public static function configure(array $config): void
    {
        if (self::$instance !== null) {
            throw new RouterException("Cannot configure router after it has been initialized");
        }
        
        // Security: Warn if debug mode is enabled
        if (isset($config['debug_mode']) && $config['debug_mode'] === true) {
            if ((isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') || getenv('APP_ENV') === 'production') {
                trigger_error(
                    'WARNING: Debug mode is enabled in production environment. This exposes sensitive information.',
                    E_USER_WARNING
                );
            }
        }
        
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Get the shared router instance, creating it if it doesn't exist.
     */
    public static function getInstance(): EllieRouter
    {
        if (!self::$instance instanceof EllieRouter) {
            self::$instance = new EllieRouter(
                self::$config['routes_directory'],
                self::$config['debug_mode'],
                self::$config['cache_enabled'],
                self::$config['cache_directory'],
                self::$config['error_formatter'],
                self::$config['enforce_domain'],
                self::$config['allowed_domains'],
                self::$config['global_middleware'],
                self::$config['container']
            );
        }

        return self::$instance;
    }

    /**
     * Reset the router instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$config = [
            'routes_directory' => '/',
            'debug_mode' => false,
            'cache_enabled' => false,
            'cache_directory' => null,
            'error_formatter' => null,
            'enforce_domain' => false,
            'allowed_domains' => [],
            'global_middleware' => [],
            'container' => null,
        ];
    }

    /**
     * Dynamically proxy static method calls to the underlying router instance.
     *
     * This magic method is the core of the facade. Any static call to this
     * class (e.g., Router::get(...)) will be passed to the RouterLibrary instance.
     *
     * @param string $method The name of the method being callednks..
     * @param array $parameters The parameters passed to the method.
     * @return mixed
     * @throws RouterException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $instance = self::getInstance();

        if (!method_exists($instance, $method)) {
            throw new RouterException("Method $method does not exist on the Router.");
        }

        return $instance->{$method}(...$parameters);
    }
}