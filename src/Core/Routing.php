<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\ClassNotFoundException;
use ElliePHP\Components\Routing\Exceptions\MiddlewareNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use function FastRoute\simpleDispatcher;

class Routing
{
    private ?Dispatcher $dispatcher = null;
    private array $routes = [];
    private array $groupStack = [];
    private readonly ResponseFactoryInterface $responseFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly string $routesDirectory;
    private readonly bool $debugMode;
    private readonly bool $cacheEnabled;
    private readonly RouteCache $cache;
    private readonly RouteDebugger $debugger;
    private readonly ErrorFormatterInterface $errorFormatter;
    private ?float $requestStartTime = null;

    public function __construct(
        ?string $routes_directory = '/',
        bool $debugMode = false,
        bool $cacheEnabled = false,
        ?string $cacheDirectory = null,
        ?ErrorFormatterInterface $errorFormatter = null
    ) {
        $factory = new Psr17Factory();
        $this->responseFactory = $factory;
        $this->streamFactory = $factory;
        $this->routesDirectory = $routes_directory;
        $this->debugMode = $debugMode;
        $this->cacheEnabled = $cacheEnabled && !$debugMode; // Disable cache in debug mode
        $this->cache = new RouteCache($cacheDirectory ?? sys_get_temp_dir());
        $this->debugger = new RouteDebugger();
        $this->errorFormatter = $errorFormatter ?? new ErrorFormatter();
    }

    /**
     * Create a route group with shared attributes
     * 
     * @param array $options Group options (prefix, middleware, name)
     * @param callable $callback Callback to define routes within the group
     */
    public function group(array $options, callable $callback): void
    {
        $parentGroup = $this->getCurrentGroupOptions();

        // Merge prefixes
        if (isset($options["prefix"])) {
            $parentPrefix = $parentGroup["prefix"] ?? "";
            $options["prefix"] = $parentPrefix . $options["prefix"];
        }

        // Merge names
        if (isset($options["name"], $parentGroup["name"])) {
            $options["name"] = $parentGroup["name"] . "." . $options["name"];
        }

        // Merge middleware
        if (isset($options["middleware"], $parentGroup["middleware"])) {
            $options["middleware"] = array_merge($parentGroup["middleware"], $options["middleware"]);
        } elseif (isset($parentGroup["middleware"])) {
            $options["middleware"] = $parentGroup["middleware"];
        }

        $newGroup = array_merge($parentGroup, $options);
        $this->groupStack[] = $newGroup;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Register a route with the router
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Route path (supports FastRoute patterns)
     * @param string $class Controller class name
     * @param mixed $handler Handler method name or closure
     * @param array $middleware Array of middleware to apply
     * @param string|null $name Optional route name
     */
    public function addRoute(
        string  $method,
        string  $url,
        string  $class = "",
        mixed   $handler = null,
        array   $middleware = [],
        ?string $name = null,
    ): void
    {
        $groupOptions = $this->getCurrentGroupOptions();
        $prefix = $groupOptions["prefix"] ?? "";
        $path = $prefix . $url;

        // Ensure leading slash
        if ($path === "" || $path[0] !== "/") {
            $path = "/" . $path;
        }
        if ($path !== "/") {
            $path = rtrim($path, "/");
        }

        // Merge group middleware with route middleware
        $groupMiddleware = $groupOptions["middleware"] ?? [];
        $allMiddleware = array_merge($groupMiddleware, $middleware);

        // Normalize handler/class
        if (is_array($handler) && count($handler) === 2) {
            [$class, $methodHandler] = $handler;
            $handler = $methodHandler;
        } elseif (is_string($handler) && class_exists($handler)) {
            $class = $handler;
            $handler = "process"; // default method
        }

        $route = [
            "method" => strtoupper($method),
            "path" => $path,
            "class" => $class,
            "handler" => $handler,
            "middleware" => $allMiddleware,
            "name" => $groupOptions["name"] ?? ($name ?? null),
        ];

        // Generate name if not provided
        if ($route["name"] === null) {
            $route["name"] = $this->generateRouteName($route);
        }

        $this->routes[] = $route;
        $this->dispatcher = null;
    }


    private function getCurrentGroupOptions(): array
    {
        return end($this->groupStack) ?: [];
    }

    private function generateRouteName(array $route): string
    {
        $method = strtolower($route['method'] ?? 'unknown');
        $path = $route['path'] ?? '/';
        
        // Clean path for name: /users/{id} -> users.id
        $cleanPath = trim($path, '/');
        $cleanPath = preg_replace('/\{([^}]+)}/', '$1', $cleanPath);
        $cleanPath = str_replace(['/', '-'], '.', $cleanPath);
        $cleanPath = $cleanPath ?: 'root';
        
        return $method . '.' . $cleanPath;
    }

    /**
     * @throws JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->debugMode) {
            $this->requestStartTime = microtime(true);
        }

        try {
            $this->ensureInitialized();
            $response = $this->routeRequest($request);

            // Add debug headers if in debug mode
            if ($this->debugMode && $this->requestStartTime !== null) {
                $timing = $this->debugger->getTimingInfo($this->requestStartTime);
                $response = $response
                    ->withHeader('X-Debug-Time', (string)$timing['duration_ms'] . 'ms')
                    ->withHeader('X-Debug-Routes', (string)count($this->routes));
            }

            return $response;
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function ensureInitialized(): void
    {
        if ($this->dispatcher instanceof Dispatcher) {
            return;
        }

        // Try to load from cache first
        if ($this->cacheEnabled && $this->cache->exists() && $this->routes === []) {
            try {
                $this->routes = $this->cache->load();
            } catch (Throwable $e) {
                // Cache load failed, fall back to loading routes
                $this->routes = [];
            }
        }

        // Only load routes if not already loaded
        if ($this->routes === []) {
            $this->loadRoutes();
            
            // Save to cache if enabled
            if ($this->cacheEnabled) {
                try {
                    $this->cache->save($this->routes);
                } catch (Throwable $e) {
                    // Cache save failed, continue without caching
                }
            }
        }

        $this->buildDispatcher();
    }

    private function buildDispatcher(): void
    {
        $routes = $this->routes;

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) use ($routes): void {
            foreach ($routes as $route) {
                $r->addRoute($route["method"], $route["path"], $route);
            }
        });
    }

    private function routeRequest(ServerRequestInterface $request): ResponseInterface
    {
        $uri = rawurldecode($request->getUri()->getPath());

        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        $method = $request->getMethod();
        $result = $this->dispatcher->dispatch($method, $uri);
        $status = $result[0];

        return match ($status) {
            Dispatcher::NOT_FOUND => throw new RouteNotFoundException("Route Not Found", 404),
            Dispatcher::METHOD_NOT_ALLOWED => throw new RouterException("Method Not Allowed", 405),
            Dispatcher::FOUND => $this->handleFoundRoute($request, $result[1], $result[2]),
            default => throw new RouterException("Unexpected routing error", 500),
        };
    }

    private function handleFoundRoute(
        ServerRequestInterface $request,
        array                  $route,
        array                  $vars
    ): ResponseInterface
    {
        $middlewares = $route["middleware"] ?? [];

        // Create the final handler for the route
        $finalHandler = new CallableRequestHandler(
            fn(ServerRequestInterface $req): ResponseInterface => $this->executeRoute($route, $req, $vars)
        );

        // Process middleware stack in reverse order (PSR-15 style)
        $handler = $finalHandler;
        foreach (array_reverse($middlewares) as $middlewareDefinition) {
            try {
                $middleware = (new MiddlewareAdapter)->adapt($middlewareDefinition);
                $handler = new MiddlewareHandler($middleware, $handler);
            } catch (Throwable $e) {
                throw new MiddlewareNotFoundException(
                    "Failed to load middleware: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $handler->handle($request);
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    private function executeRoute(
        array                  $route,
        ServerRequestInterface $request,
        array                  $params
    ): ResponseInterface
    {
        [$class, $method] = [$route["class"], $route["handler"]];

        // Handle closure routes
        if ($class === "" && is_callable($method)) {
            $result = $method($request, $params);
            return $result instanceof ResponseInterface
                ? $result
                : $this->createResponse($result);
        }

        // Handle controller routes
        if (!class_exists($class)) {
            throw new ClassNotFoundException("Class not found: $class");
        }
        if (!method_exists($class, $method)) {
            throw new ClassNotFoundException("Method not found: $method in $class");
        }

        $reflection = new ReflectionMethod($class, $method);
        $args = $this->matchParameters($reflection, $params, $request);
        $controller = new $class();

        $result = $reflection->invokeArgs($controller, $args);
        return $result instanceof ResponseInterface
            ? $result
            : $this->createResponse($result);
    }

    /**
     * @throws ReflectionException
     */
    private function matchParameters(
        ReflectionMethod       $method,
        array                  $params,
        ServerRequestInterface $request,
    ): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $name = $param->getName();

            // Fix: Correct type checking order
            if ($type instanceof ReflectionNamedType &&
                !$type->isBuiltin() &&
                is_a($type->getName(), ServerRequestInterface::class, true)) {
                $args[] = $request;
            } elseif (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RouterException("Missing required parameter: $name", 400);
            }
        }

        return $args;
    }

    /**
     * @throws JsonException
     */
    private function createResponse(mixed $data): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(200)
            ->withHeader("Content-Type", "application/json");

        $stream = $this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream);
    }

    /**
     * @throws JsonException
     */
    public function handleException(Throwable $e): ResponseInterface
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        if ($e instanceof RouteNotFoundException) {
            $status = 404;
        }

        $data = $this->errorFormatter->format($e, $this->debugMode);

        // Handle HTML formatter
        if (isset($data['html'])) {
            $response = $this->responseFactory
                ->createResponse($data['status'] ?? $status)
                ->withHeader("Content-Type", "text/html");
            $stream = $this->streamFactory->createStream($data['html']);
            return $response->withBody($stream);
        }

        // Default JSON response
        $response = $this->createResponse($data);
        return $response->withStatus($status);
    }

    private function loadRoutes(): void
    {
        if (!is_dir($this->routesDirectory)) {
            throw new RouterException("Routes directory not found: $this->routesDirectory");
        }

        $found = false;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->routesDirectory),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === "php") {
                try {
                    // Pass router instance to route files
                    $router = $this;
                    require $file->getPathname();
                    $found = true;
                } catch (Throwable $e) {
                    throw new RouterException(
                        "Error loading route file {$file->getPathname()}: {$e->getMessage()}",
                        0,
                        $e
                    );
                }
            }
        }

        if (!$found) {
            throw new RouterException("No route files found in: $this->routesDirectory");
        }
    }

    // Helper methods for route definition
    
    /**
     * Register a GET route
     * 
     * @param string $url Route path
     * @param mixed $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name)
     */
    public function get(string $url, mixed $handler, array $options = []): void
    {
        $this->addRoute('GET', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null
        );
    }

    /**
     * Register a POST route
     * 
     * @param string $url Route path
     * @param mixed $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name)
     */
    public function post(string $url, mixed $handler, array $options = []): void
    {
        $this->addRoute('POST', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null
        );
    }

    /**
     * Register a PUT route
     * 
     * @param string $url Route path
     * @param mixed $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name)
     */
    public function put(string $url, mixed $handler, array $options = []): void
    {
        $this->addRoute('PUT', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null
        );
    }

    /**
     * Register a DELETE route
     * 
     * @param string $url Route path
     * @param mixed $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name)
     */
    public function delete(string $url, mixed $handler, array $options = []): void
    {
        $this->addRoute('DELETE', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null
        );
    }

    /**
     * Register a PATCH route
     * 
     * @param string $url Route path
     * @param mixed $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name)
     */
    public function patch(string $url, mixed $handler, array $options = []): void
    {
        $this->addRoute('PATCH', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null
        );
    }

    // Reset router state (useful for testing)
    public function reset(): void
    {
        $this->routes = [];
        $this->groupStack = [];
        $this->dispatcher = null;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get formatted routes for debugging
     */
    public function getFormattedRoutes(): array
    {
        return $this->debugger->formatRoutes($this->routes);
    }

    /**
     * Print route table to output
     */
    public function printRoutes(): string
    {
        $this->ensureInitialized();
        return $this->debugger->generateRouteTable($this->routes);
    }

    /**
     * Clear route cache
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Check if cache is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * Register routes from an array (useful for testing or programmatic registration)
     */
    public function registerRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->addRoute(
                $route['method'] ?? 'GET',
                $route['path'] ?? '/',
                $route['class'] ?? '',
                $route['handler'] ?? null,
                $route['middleware'] ?? [],
                $route['name'] ?? null
            );
        }
    }

}
