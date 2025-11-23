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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Throwable;
use function FastRoute\simpleDispatcher;

class Routing
{
    private ?Dispatcher $dispatcher = null;
    private array $dispatcherCache = [];
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
    private readonly bool $enforceDomain;
    private readonly array $allowedDomains;
    private array $domainRegexCache = [];
    private ?int $routesHash = null;
    private array $reflectionCache = [];
    private array $globalMiddleware;
    private ?ContainerInterface $container;

    public function __construct(
        ?string $routes_directory = '/',
        bool $debugMode = false,
        bool $cacheEnabled = false,
        ?string $cacheDirectory = null,
        ?ErrorFormatterInterface $errorFormatter = null,
        bool $enforceDomain = false,
        array $allowedDomains = [],
        array $globalMiddleware = [],
        ?ContainerInterface $container = null
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
        $this->enforceDomain = $enforceDomain;
        $this->allowedDomains = $allowedDomains;
        $this->globalMiddleware = $globalMiddleware;
        $this->container = $container;
    }

    /**
     * Create a route group with shared attributes
     * 
     * @param array $options Group options (prefix, middleware, name, domain)
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

        // Domain inheritance - child domain overrides parent
        if (!isset($options["domain"]) && isset($parentGroup["domain"])) {
            $options["domain"] = $parentGroup["domain"];
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
     * @param callable|string|array|null $handler Handler method name, closure, or [Class, 'method'] array
     * @param array $middleware Array of middleware to apply
     * @param string|null $name Optional route name
     * @param string|null $domain Optional domain constraint
     */
    public function addRoute(
        string  $method,
        string  $url,
        string  $class = "",
        callable|string|array|null $handler = null,
        array   $middleware = [],
        ?string $name = null,
        ?string $domain = null,
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

        // Get domain from group if not specified
        $routeDomain = $domain ?? $groupOptions["domain"] ?? null;

        // Normalize handler/class
        if (is_array($handler) && count($handler) === 2) {
            [$class, $methodHandler] = $handler;
            $handler = $methodHandler;
        } elseif (is_string($handler) && class_exists($handler)) {
            // Handler is a class name, use it as the controller class
            /** @var string $handler */
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
            "domain" => $routeDomain,
        ];

        // Generate name if not provided
        if ($route["name"] === null) {
            $route["name"] = $this->generateRouteName($route);
        }

        $this->routes[] = $route;
        $this->dispatcher = null;
        $this->dispatcherCache = [];
        $this->routesHash = null;
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
            // Get the host from the request for domain-aware initialization
            $host = $request->getUri()->getHost();
            $this->ensureInitialized($host);
            $response = $this->routeRequest($request);

            // Add debug headers if in debug mode
            if ($this->debugMode && $this->requestStartTime !== null) {
                $timing = $this->debugger->getTimingInfo($this->requestStartTime);
                $response = $response
                    ->withHeader('X-Debug-Time', $timing['duration_ms'] . 'ms')
                    ->withHeader('X-FRV', 'ElliePHP Router')
                    ->withHeader('X-Debug-Routes', (string)count($this->routes));
            }

            return $response;
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function ensureInitialized(?string $domain = null): void
    {
        // Try to load from cache first
        if ($this->cacheEnabled && $this->routes === [] && $this->cache->exists()) {
            try {
                // Check if cache is still valid
                if ($this->cache->isValid($this->routesDirectory)) {
                    $this->routes = $this->cache->load();
                }
            } catch (Throwable) {
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
                    $this->cache->save($this->routes, $this->routesDirectory);
                } catch (Throwable) {
                    // Cache save failed, continue without caching
                }
            }
        }

        // Get or build dispatcher for the current domain
        // This allows different routes for different domains
        $this->getDispatcherForDomain($domain);
    }

    /**
     * Get dispatcher for a specific domain, using cache if available
     */
    private function getDispatcherForDomain(?string $domain): void
    {
        $cacheKey = $domain ?? 'default';
        
        // Check if we have a cached dispatcher for this domain
        // Only check hash if routes might have changed (when routesHash is null, it means routes changed)
        // If routes hash is null, routes were modified, so rebuild
        if (isset($this->dispatcherCache[$cacheKey]['hash']) && $this->routesHash !== null && $this->dispatcherCache[$cacheKey]['hash'] === $this->routesHash) {
            $this->dispatcher = $this->dispatcherCache[$cacheKey]['dispatcher'];
            return;
        }
        
        // Build new dispatcher
        $this->buildDispatcher($domain);
        
        // Get or calculate routes hash (lazy, only when needed)
        if ($this->routesHash === null) {
            $this->routesHash = $this->calculateRoutesHash();
        }
        
        // Cache it
        $this->dispatcherCache[$cacheKey] = [
            'dispatcher' => $this->dispatcher,
            'hash' => $this->routesHash,
        ];

    }

    /**
     * Calculate hash of current routes for cache invalidation
     * Creates a hash based on route structure without serializing closures
     * This is only called when routes change, not on every request
     */
    private function calculateRoutesHash(): int
    {
        $routeData = [];
        foreach ($this->routes as $route) {
            // Create a serializable representation excluding closures
            $routeData[] = [
                'method' => $route['method'] ?? '',
                'path' => $route['path'] ?? '',
                'class' => $route['class'] ?? '',
                'handler' => is_callable($route['handler'] ?? null) && !is_string($route['handler'] ?? null) 
                    ? 'closure' 
                    : ($route['handler'] ?? null),
                'middleware' => array_map(
                    static function ($mw) {
                        if (is_string($mw)) {
                            return $mw;
                        }
                        if (is_object($mw)) {
                            return get_class($mw);
                        }
                        return 'closure';
                    },
                    $route['middleware'] ?? []
                ),
                'name' => $route['name'] ?? null,
                'domain' => $route['domain'] ?? null,
            ];
        }
        return crc32(serialize($routeData));
    }

    private function buildDispatcher(?string $domain = null): void
    {
        $routes = $this->routes;
        
        // If domain is provided, filter routes to only those matching the domain
        if ($domain !== null) {
            $matchedRoutes = [];
            $patternRoutes = [];
            
            foreach ($routes as $route) {
                // If route has no domain constraint, include it
                if (!isset($route['domain'])) {
                    $matchedRoutes[] = $route;
                    continue;
                }
                
                // Check if domain matches
                $match = $this->matchDomain($route['domain'], $domain);
                if ($match !== false) {
                    // Prioritize exact matches over pattern matches
                    if ($route['domain'] === $domain) {
                        $matchedRoutes[] = $route;
                    } else {
                        $patternRoutes[] = $route;
                    }
                }
            }
            
            // For routes with same method+path, prefer exact domain matches
            $routeKeys = [];
            $filteredRoutes = [];
            
            // First add exact matches
            foreach ($matchedRoutes as $route) {
                $key = $route['method'] . ':' . $route['path'];
                $routeKeys[$key] = true;
                $filteredRoutes[] = $route;
            }
            
            // Then add pattern matches only if no exact match exists
            foreach ($patternRoutes as $route) {
                $key = $route['method'] . ':' . $route['path'];
                if (!isset($routeKeys[$key])) {
                    $filteredRoutes[] = $route;
                }
            }
            
            $routes = $filteredRoutes;
        }

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

        // Get the host from the request
        $host = $request->getUri()->getHost();

        // Check domain enforcement
        if ($this->enforceDomain && !empty($this->allowedDomains) && !$this->isDomainAllowed($host)) {
            throw new RouterException("Domain not allowed: $host", 403);
        }

        // Get dispatcher for this specific domain (uses cache if available)
        $this->getDispatcherForDomain($host);

        $method = $request->getMethod();
        $result = $this->dispatcher->dispatch($method, $uri);
        $status = $result[0];

        return match ($status) {
            Dispatcher::NOT_FOUND => throw new RouteNotFoundException(
                "Route not found: $method $uri",
                404
            ),
            Dispatcher::METHOD_NOT_ALLOWED => throw new RouterException(
                "Method $method not allowed for route: $uri",
                405
            ),
            Dispatcher::FOUND => $this->handleFoundRoute($request, $result[1], $result[2], $host),
            default => throw new RouterException(
                "Unexpected dispatcher status: $status. This indicates a bug in FastRoute integration.",
                500
            ),
        };
    }

    private function handleFoundRoute(
        ServerRequestInterface $request,
        array                  $route,
        array                  $vars,
        string                 $host
    ): ResponseInterface
    {
        // Check if route has domain constraint
        if (isset($route["domain"])) {
            $domainMatch = $this->matchDomain($route["domain"], $host);
            if ($domainMatch === false) {
                throw new RouteNotFoundException("Route not found for domain: $host", 404);
            }
            
            // Add domain parameters to route vars
            if (is_array($domainMatch)) {
                $vars = array_merge($domainMatch, $vars);
            }
        }

        $routeMiddlewares = $route["middleware"] ?? [];
        
        // Merge global middleware with route-specific middleware
        // Global middleware executes first (outer layer), then route middleware (inner layer)
        $middlewares = array_merge($this->globalMiddleware, $routeMiddlewares);

        // Create the final handler for the route
        $finalHandler = new CallableRequestHandler(
            fn(ServerRequestInterface $req): ResponseInterface => $this->executeRoute($route, $req, $vars)
        );

        // Process middleware stack in reverse order (PSR-15 style)
        // This ensures global middleware wraps around route middleware
        $handler = $finalHandler;
        foreach (array_reverse($middlewares) as $middlewareDefinition) {
            try {
                $middleware = new MiddlewareAdapter($this->container)->adapt($middlewareDefinition);
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

        // Cache reflection metadata for performance (major speedup!)
        $cacheKey = $class . '::' . $method;
        if (!isset($this->reflectionCache[$cacheKey])) {
            $this->reflectionCache[$cacheKey] = $this->extractParameterMetadata($class, $method);
        }

        $paramMetadata = $this->reflectionCache[$cacheKey];
        $args = $this->matchParametersFast($paramMetadata, $params, $request);
        
        // Resolve controller from container if available
        $controller = $this->resolveController($class);

        $result = $controller->$method(...$args);
        return $result instanceof ResponseInterface
            ? $result
            : $this->createResponse($result);
    }

    /**
     * Extract parameter metadata once and cache it (performance optimization)
     * @throws ReflectionException
     */
    private function extractParameterMetadata(string $class, string $method): array
    {
        $reflectionClass = new ReflectionClass($class);
        $reflection = $reflectionClass->getMethod($method);
        $metadata = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $metadata[] = [
                'name' => $param->getName(),
                'type' => $type instanceof ReflectionNamedType ? $type->getName() : null,
                'isBuiltin' => $type instanceof ReflectionNamedType && $type->isBuiltin(),
                'isOptional' => $param->isOptional(),
                'defaultValue' => $param->isOptional() ? $param->getDefaultValue() : null,
            ];
        }

        return $metadata;
    }

    /**
     * Fast parameter matching using cached metadata (much faster than reflection on every request)
     */
    private function matchParametersFast(
        array                  $parameterMetadata,
        array                  $params,
        ServerRequestInterface $request,
    ): array
    {
        $args = [];

        foreach ($parameterMetadata as $param) {
            $name = $param['name'];
            $type = $param['type'];
            $isBuiltin = $param['isBuiltin'];

            // Check if it's a ServerRequestInterface type
            if ($type !== null && !$isBuiltin && is_a($type, ServerRequestInterface::class, true)) {
                $args[] = $request;
            } elseif (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($param['isOptional']) {
                $args[] = $param['defaultValue'];
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

        // Use status from formatter if provided, otherwise use calculated status
        $finalStatus = $data['status'] ?? $status;

        // Handle HTML formatter
        if (isset($data['html'])) {
            $response = $this->responseFactory
                ->createResponse($finalStatus)
                ->withHeader("Content-Type", "text/html");
            $stream = $this->streamFactory->createStream($data['html']);
            return $response->withBody($stream);
        }

        // Default JSON response
        $response = $this->createResponse($data);
        return $response->withStatus($finalStatus);
    }

    private function loadRoutes(): void
    {
        // If routes directory is default or invalid, skip file loading
        // Routes can be defined programmatically
        if ($this->routesDirectory === '/' || empty($this->routesDirectory)) {
            // Routes will be defined programmatically, not from files
            return;
        }

        // Validate and normalize the routes directory path
        try {
            $routesDirectory = $this->validateRoutesDirectory($this->routesDirectory);
        } catch (RouterException) {
            // If validation fails, skip file loading (routes defined programmatically)
            return;
        }
        
        if (!is_dir($routesDirectory)) {
            // Routes defined programmatically, not from files
            return;
        }

        $found = false;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($routesDirectory),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === "php") {
                    // Additional security check: ensure file is within routes directory
                    $realPath = $file->getRealPath();
                    if ($realPath === false || !str_starts_with($realPath, $routesDirectory)) {
                        throw new RouterException("Security violation: Route file outside allowed directory");
                    }
                    
                    try {
                        // Pass router instance to route files
                        $router = $this;
                        require $realPath;
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
        } catch (Throwable $e) {
            // If directory iteration fails, allow programmatic route definition
            if ($e instanceof RouterException && str_contains($e->getMessage(), 'Security violation')) {
                throw $e;
            }
            // For other errors, allow programmatic routes
            return;
        }
    }

    /**
     * Validate and normalize the routes directory path to prevent path traversal
     */
    private function validateRoutesDirectory(string $path): string
    {
        // Resolve to absolute path
        $realPath = realpath($path);
        
        if ($realPath === false) {
            throw new RouterException("Invalid routes directory path: $path");
        }
        
        // Ensure no path traversal attempts
        if (str_contains($path, '..')) {
            throw new RouterException("Path traversal detected in routes directory");
        }
        
        // Ensure it's a directory
        if (!is_dir($realPath)) {
            throw new RouterException("Routes directory is not a valid directory: $path");
        }
        
        // Ensure it's readable
        if (!is_readable($realPath)) {
            throw new RouterException("Routes directory is not readable: $path");
        }
        
        return $realPath;
    }

    // Helper methods for route definition
    
    /**
     * Create a PendingGroup with a prefix
     * 
     * @param string $prefix URL prefix for the group
     * @return PendingGroup
     */
    public function prefix(string $prefix): PendingGroup
    {
        return new PendingGroup($this, ['prefix' => $prefix]);
    }

    /**
     * Create a PendingGroup with middleware
     * 
     * @param array $middleware Middleware array
     * @return PendingGroup
     */
    public function middleware(array $middleware): PendingGroup
    {
        return new PendingGroup($this, ['middleware' => $middleware]);
    }

    /**
     * Create a PendingGroup with a domain constraint
     * 
     * @param string $domain Domain pattern
     * @return PendingGroup
     */
    public function domain(string $domain): PendingGroup
    {
        return new PendingGroup($this, ['domain' => $domain]);
    }

    /**
     * Create a PendingGroup with a name prefix
     * 
     * @param string $name Name prefix
     * @return PendingGroup
     */
    public function name(string $name): PendingGroup
    {
        return new PendingGroup($this, ['name' => $name]);
    }

    /**
     * Register a GET route
     * 
     * @param string $url Route path
     * @param callable|string|array $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name, domain)
     * @return PendingRoute|void Returns PendingRoute for fluent chaining when options is empty
     */
    public function get(string $url, callable|string|array $handler, array $options = []): PendingRoute|null
    {
        if (empty($options)) {
            return new PendingRoute($this, 'GET', $url, $handler);
        }
        
        $this->addRoute('GET', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null,
            $options['domain'] ?? null
        );
        
        return null;
    }

    /**
     * Register a POST route
     * 
     * @param string $url Route path
     * @param callable|string|array $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name, domain)
     * @return PendingRoute|void Returns PendingRoute for fluent chaining when options is empty
     */
    public function post(string $url, callable|string|array $handler, array $options = []): PendingRoute|null
    {
        if (empty($options)) {
            return new PendingRoute($this, 'POST', $url, $handler);
        }
        
        $this->addRoute('POST', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null,
            $options['domain'] ?? null
        );
        
        return null;
    }

    /**
     * Register a PUT route
     * 
     * @param string $url Route path
     * @param callable|string|array $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name, domain)
     * @return PendingRoute|void Returns PendingRoute for fluent chaining when options is empty
     */
    public function put(string $url, callable|string|array $handler, array $options = []): PendingRoute|null
    {
        if (empty($options)) {
            return new PendingRoute($this, 'PUT', $url, $handler);
        }
        
        $this->addRoute('PUT', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null,
            $options['domain'] ?? null
        );
        
        return null;
    }

    /**
     * Register a DELETE route
     * 
     * @param string $url Route path
     * @param callable|string|array $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name, domain)
     * @return PendingRoute|void Returns PendingRoute for fluent chaining when options is empty
     */
    public function delete(string $url, callable|string|array $handler, array $options = []): PendingRoute|null
    {
        if (empty($options)) {
            return new PendingRoute($this, 'DELETE', $url, $handler);
        }
        
        $this->addRoute('DELETE', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null,
            $options['domain'] ?? null
        );
        
        return null;
    }

    /**
     * Register a PATCH route
     * 
     * @param string $url Route path
     * @param callable|string|array $handler Controller class, method, or closure
     * @param array $options Additional options (class, middleware, name, domain)
     * @return PendingRoute|void Returns PendingRoute for fluent chaining when options is empty
     */
    public function patch(string $url, callable|string|array $handler, array $options = []): PendingRoute|null
    {
        if (empty($options)) {
            return new PendingRoute($this, 'PATCH', $url, $handler);
        }
        
        $this->addRoute('PATCH', $url,
            $options['class'] ?? '',
            $handler,
            $options['middleware'] ?? [],
            $options['name'] ?? null,
            $options['domain'] ?? null
        );
        
        return null;
    }

    // Reset router state (useful for testing)
    public function reset(): void
    {
        $this->routes = [];
        $this->groupStack = [];
        $this->dispatcher = null;
        $this->dispatcherCache = [];
        $this->domainRegexCache = [];
        $this->routesHash = null;
        $this->reflectionCache = [];
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
        // Load routes without building dispatcher
        if ($this->routes === []) {
            // Try to load from cache first
            if ($this->cacheEnabled && $this->cache->exists()) {
                try {
                    $this->routes = $this->cache->load();
                } catch (Throwable) {
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
                        $this->cache->save($this->routes, $this->routesDirectory);
                    } catch (Throwable) {
                        // Cache save failed, continue without caching
                    }
                }
            }
        }
        
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
                $route['name'] ?? null,
                $route['domain'] ?? null
            );
        }
    }

    /**
     * Resolve controller instance from container or create new instance
     */
    private function resolveController(string $class): object
    {
        if ($this->container !== null && $this->container->has($class)) {
            try {
                return $this->container->get($class);
            } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {

            }
        }
        
        return new $class();
    }

    /**
     * Check if a domain is allowed
     */
    private function isDomainAllowed(string $host): bool
    {
        if (empty($this->allowedDomains)) {
            return true;
        }

        return array_any($this->allowedDomains, fn($allowedDomain) => $this->matchDomain($allowedDomain, $host) !== false);
    }

    /**
     * Match a domain pattern against a host
     * 
     * @param string $pattern Domain pattern (e.g., "example.com" or "{account}.example.com")
     * @param string $host The actual host from the request
     * @return bool|array False if no match, true if match without params, array of params if match with params
     */
    private function matchDomain(string $pattern, string $host): bool|array
    {
        // Exact match
        if ($pattern === $host) {
            return true;
        }

        // Check if pattern has parameters
        if (!str_contains($pattern, '{')) {
            return false;
        }

        // Check cache for compiled regex
        if (!isset($this->domainRegexCache[$pattern])) {
            // Convert domain pattern to regex
            // {account}.example.com -> ^(?P<account>[^.]+)\.example\.com$
            // First replace parameters with placeholders, then quote, then replace placeholders with regex
            $paramMap = [];
            $paramIndex = 0;
            
            // Extract parameters and replace with placeholders
            $patternWithPlaceholders = preg_replace_callback(
                '/\{([a-zA-Z_]\w*)}/',
                static function ($matches) use (&$paramMap, &$paramIndex) {
                    $placeholder = '___PARAM_%s___';
                    $key = sprintf($placeholder, $paramIndex++);
                    $paramMap[$key] = $matches[1];
                    return $key;
                },
                $pattern
            );
            
            // Quote the pattern (now placeholders are safe)
            $quotedPattern = preg_quote($patternWithPlaceholders, '/');
            
            // Replace placeholders with regex patterns
            foreach ($paramMap as $key => $paramName) {
                $quotedPattern = str_replace($key, '(?P<' . $paramName . '>[^.]+)', $quotedPattern);
            }
            
            $regex = '/^' . $quotedPattern . '$/i';
            
            // Cache the compiled regex and param map
            $this->domainRegexCache[$pattern] = [
                'regex' => $regex,
                'paramMap' => $paramMap,
            ];
        }

        $cached = $this->domainRegexCache[$pattern];
        $regex = $cached['regex'];

        if (preg_match($regex, $host, $matches)) {
            // Extract named parameters
            $params = array_filter($matches, static function ($key) {
                return is_string($key);
            }, ARRAY_FILTER_USE_KEY);
            return $params ?: true;
        }

        return false;
    }

}
