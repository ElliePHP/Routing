<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

/**
 * PendingRoute - Builder for fluent route configuration
 * 
 * Accumulates route configuration through method chaining before registering
 * the route with the router when the object is destructed.
 */
class PendingRoute
{
    private Routing $router;
    private string $method;
    private string $url;
    private mixed $handler;
    private array $options;

    /**
     * Create a new pending route
     * 
     * @param Routing $router The router instance
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Route path
     * @param callable|string|array $handler Route handler
     * @param array $initialOptions Initial options (middleware, name, domain, class)
     */
    public function __construct(
        Routing $router,
        string $method,
        string $url,
        callable|string|array $handler,
        array $initialOptions = []
    ) {
        $this->router = $router;
        $this->method = $method;
        $this->url = $url;
        $this->handler = $handler;
        $this->options = $initialOptions;
    }

    /**
     * Add middleware to the route
     * 
     * Can be called multiple times - middleware arrays will be merged.
     * 
     * @param array $middleware Array of middleware classes or instances
     * @return self
     */
    public function middleware(array $middleware): self
    {
        if (isset($this->options['middleware'])) {
            $this->options['middleware'] = array_merge($this->options['middleware'], $middleware);
        } else {
            $this->options['middleware'] = $middleware;
        }
        
        return $this;
    }

    /**
     * Set the route name
     * 
     * Can be called multiple times - last value wins.
     * 
     * @param string $name Route name
     * @return self
     */
    public function name(string $name): self
    {
        $this->options['name'] = $name;
        return $this;
    }

    /**
     * Set the domain constraint
     * 
     * Can be called multiple times - last value wins.
     * 
     * @param string $domain Domain pattern (e.g., "api.example.com" or "{tenant}.example.com")
     * @return self
     */
    public function domain(string $domain): self
    {
        $this->options['domain'] = $domain;
        return $this;
    }

    /**
     * Register the route when the object is destructed
     * 
     * This is called automatically when the PendingRoute goes out of scope.
     * Wraps the registration in a try-catch to handle errors gracefully.
     */
    public function __destruct()
    {
        try {
            $this->router->addRoute(
                $this->method,
                $this->url,
                $this->options['class'] ?? '',
                $this->handler,
                $this->options['middleware'] ?? [],
                $this->options['name'] ?? null,
                $this->options['domain'] ?? null
            );
        } catch (\Throwable $e) {
            // Log error but don't throw from destructor
            // Throwing from destructors can cause fatal errors
            error_log("Failed to register route: " . $e->getMessage());
        }
    }
}
