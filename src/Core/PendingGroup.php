<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

/**
 * PendingGroup - Builder for fluent group configuration
 * 
 * Accumulates group configuration through method chaining before executing
 * the group callback with the accumulated configuration.
 */
class PendingGroup
{
    private Routing $router;
    private array $options;

    /**
     * Create a new pending group
     * 
     * @param Routing $router The router instance
     * @param array $initialOptions Initial group options (prefix, middleware, name, domain)
     */
    public function __construct(Routing $router, array $initialOptions = [])
    {
        $this->router = $router;
        $this->options = $initialOptions;
    }

    /**
     * Set or append prefix for the group
     * 
     * Can be called multiple times - last value wins.
     * 
     * @param string $prefix URL prefix (e.g., "/api", "/admin")
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->options['prefix'] = $prefix;
        return $this;
    }

    /**
     * Add middleware to the group
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
     * Set the name prefix for routes in the group
     * 
     * Can be called multiple times - last value wins.
     * 
     * @param string $name Name prefix (e.g., "api", "admin")
     * @return self
     */
    public function name(string $name): self
    {
        $this->options['name'] = $name;
        return $this;
    }

    /**
     * Set the domain constraint for the group
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
     * Execute the group callback with accumulated configuration
     * 
     * This is a terminal method - it executes the callback and doesn't return anything.
     * 
     * @param callable $callback Callback to define routes within the group
     * @return void
     */
    public function group(callable $callback): void
    {
        $this->router->group($this->options, $callback);
    }
}
