<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouterException;
use ElliePHP\Components\Routing\Router;
use Psr\Container\ContainerInterface;

/**
 * RouterConfigurationBuilder - Fluent configuration builder for the router
 *
 * Provides a chainable interface for configuring router options before
 * applying them to the router instance.
 */
class RouterConfigurationBuilder
{
    private array $config;

    public function __construct()
    {
        // Start with empty config - only store explicitly set values
        $this->config = [];
    }

    /**
     * Enable or disable debug mode
     *
     * @param bool $enabled Whether to enable debug mode (default: true)
     * @return self
     */
    public function debugMode(bool $enabled = true): self
    {
        $this->config['debug_mode'] = $enabled;
        return $this;
    }

    /**
     * Enable route caching
     *
     * @return self
     */
    public function enableCache(): self
    {
        $this->config['cache_enabled'] = true;
        return $this;
    }

    /**
     * Disable route caching
     *
     * @return self
     */
    public function disableCache(): self
    {
        $this->config['cache_enabled'] = false;
        return $this;
    }

    /**
     * Set the cache directory path
     *
     * @param string $path Path to the cache directory
     * @return self
     */
    public function cacheDirectory(string $path): self
    {
        $this->config['cache_directory'] = $path;
        return $this;
    }

    /**
     * Set the routes directory path
     *
     * @param string $path Path to the routes directory
     * @return self
     */
    public function routesDirectory(string $path): self
    {
        $this->config['routes_directory'] = $path;
        return $this;
    }

    /**
     * Set global middleware array
     *
     * @param array $middleware Array of middleware classes or instances
     * @return self
     */
    public function globalMiddleware(array $middleware): self
    {
        $this->config['global_middleware'] = $middleware;
        return $this;
    }

    /**
     * Add middleware to the global middleware stack
     *
     * Supports adding a single middleware or multiple middleware at once.
     *
     * @param string|object|array $middleware Middleware class name, instance, or array of middleware
     * @return self
     *
     * @example
     * // Add single middleware
     * ->addGlobalMiddleware(AuthMiddleware::class)
     *
     * // Add multiple middleware
     * ->addGlobalMiddleware([
     *     AuthMiddleware::class,
     *     CorsMiddleware::class,
     *     RateLimitMiddleware::class
     * ])
     */
    public function addGlobalMiddleware(string|object|array $middleware): self
    {
        if (!isset($this->config['global_middleware'])) {
            $this->config['global_middleware'] = [];
        }

        if (is_array($middleware)) {
            $this->config['global_middleware'] = array_merge(
                $this->config['global_middleware'],
                $middleware
            );
        } else {
            $this->config['global_middleware'][] = $middleware;
        }

        return $this;
    }

    /**
     * Set allowed domains list
     *
     * @param array $domains Array of allowed domain patterns
     * @return self
     */
    public function allowedDomains(array $domains): self
    {
        $this->config['allowed_domains'] = $domains;
        return $this;
    }

    /**
     * Enable or disable domain enforcement
     *
     * @param bool $enforce Whether to enforce domain restrictions (default: true)
     * @return self
     */
    public function enforceDomain(bool $enforce = true): self
    {
        $this->config['enforce_domain'] = $enforce;
        return $this;
    }

    /**
     * Set the PSR-11 container instance
     *
     * @param ContainerInterface $container PSR-11 container for dependency injection
     * @return self
     */
    public function container(ContainerInterface $container): self
    {
        $this->config['container'] = $container;
        return $this;
    }

    /**
     * Set custom error formatter
     *
     * @param ErrorFormatterInterface $formatter Custom error formatter instance
     * @return self
     */
    public function errorFormatter(ErrorFormatterInterface $formatter): self
    {
        $this->config['error_formatter'] = $formatter;
        return $this;
    }

    /**
     * Apply the configuration to the Router (terminal method)
     *
     * @return void
     * @throws RouterException If configuration is invalid or router already initialized
     */
    public function build(): void
    {
        $this->validateConfiguration();
        $this->applyConfiguration();
    }

    /**
     * Alias for build() method (terminal method)
     *
     * @return void
     * @throws RouterException If configuration is invalid or router already initialized
     */
    public function apply(): void
    {
        $this->build();
    }

    /**
     * Validate the current configuration
     *
     * @throws RouterException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        $routerClass = Router::class;
        $existingConfig = $routerClass::getConfig();
        $mergedConfig = $this->mergeConfigurations($existingConfig, $this->config);

        // Validate routes directory
        if ($mergedConfig['routes_directory'] !== '/' && !empty($mergedConfig['routes_directory'])) {
            $this->validateRoutesDirectory($mergedConfig['routes_directory']);
        }

        // Validate cache directory
        if (isset($mergedConfig['cache_directory'])) {
            $this->validateCacheDirectory($mergedConfig['cache_directory']);
        }

        // Validate error formatter
        if (isset($mergedConfig['error_formatter']) &&
            !($mergedConfig['error_formatter'] instanceof ErrorFormatterInterface)) {
            throw new RouterException('Error formatter must implement ErrorFormatterInterface');
        }

        // Validate container
        if (isset($mergedConfig['container']) &&
            !($mergedConfig['container'] instanceof ContainerInterface)) {
            throw new RouterException('Container must implement PSR-11 ContainerInterface');
        }

        // Validate middleware
        $this->validateMiddleware($mergedConfig);

        // Validate domains
        $this->validateDomains($mergedConfig);

        // Validate boolean options
        $this->validateBooleanOptions($mergedConfig);
    }

    /**
     * Validate middleware configuration
     *
     * @param array $config Configuration array
     * @throws RouterException If middleware is invalid
     */
    private function validateMiddleware(array $config): void
    {
        if (!isset($config['global_middleware'])) {
            return;
        }

        if (!is_array($config['global_middleware'])) {
            throw new RouterException('Global middleware must be an array');
        }

        foreach ($config['global_middleware'] as $index => $middleware) {
            if (!is_string($middleware) && !is_object($middleware) && !is_callable($middleware)) {
                throw new RouterException(
                    "Invalid middleware at index $index: must be string, object, or callable"
                );
            }
        }
    }

    /**
     * Validate domain configuration
     *
     * @param array $config Configuration array
     * @throws RouterException If domains are invalid
     */
    private function validateDomains(array $config): void
    {
        if (!isset($config['allowed_domains'])) {
            return;
        }

        if (!is_array($config['allowed_domains'])) {
            throw new RouterException('Allowed domains must be an array');
        }

        foreach ($config['allowed_domains'] as $index => $domain) {
            if (!is_string($domain) || empty($domain)) {
                throw new RouterException(
                    "Invalid domain at index $index: must be non-empty string"
                );
            }
        }
    }

    /**
     * Validate boolean configuration options
     *
     * @param array $config Configuration array
     * @throws RouterException If boolean options are invalid
     */
    private function validateBooleanOptions(array $config): void
    {
        $booleanOptions = ['debug_mode', 'cache_enabled', 'enforce_domain'];

        foreach ($booleanOptions as $option) {
            if (isset($config[$option]) && !is_bool($config[$option])) {
                $name = str_replace('_', ' ', ucfirst($option));
                throw new RouterException("$name must be a boolean value");
            }
        }
    }

    /**
     * Validate routes directory path
     *
     * @param string $path Routes directory path
     * @throws RouterException If path is invalid
     */
    private function validateRoutesDirectory(string $path): void
    {
        if (str_contains($path, '..')) {
            throw new RouterException('Path traversal detected in routes directory');
        }

        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            throw new RouterException("Routes directory is not a valid directory: $path");
        }

        if (!is_readable($path)) {
            throw new RouterException("Routes directory is not readable: $path");
        }
    }

    /**
     * Validate cache directory path
     *
     * @param string $path Cache directory path
     * @throws RouterException If path is invalid
     */
    private function validateCacheDirectory(string $path): void
    {
        if (str_contains($path, '..')) {
            throw new RouterException('Path traversal detected in cache directory');
        }

        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            throw new RouterException("Cache directory is not a valid directory: $path");
        }

        if (!is_writable($path)) {
            throw new RouterException("Cache directory is not writable: $path");
        }
    }

    /**
     * Apply the configuration to the Router facade
     *
     * @throws RouterException If router is already initialized
     */
    private function applyConfiguration(): void
    {
        $routerClass = Router::class;
        $existingConfig = $routerClass::getConfig();
        $mergedConfig = $this->mergeConfigurations($existingConfig, $this->config);

        // Router::configure will apply configuration rules
        $routerClass::configure($mergedConfig);
    }

    /**
     * Merge existing configuration with fluent configuration
     *
     * @param array $existingConfig Current router configuration
     * @param array $fluentConfig Configuration from fluent builder
     * @return array Merged configuration with fluent taking precedence
     */
    private function mergeConfigurations(array $existingConfig, array $fluentConfig): array
    {
        $merged = $existingConfig;

        foreach ($fluentConfig as $key => $value) {
            if ($key === 'global_middleware' &&
                isset($merged[$key]) &&
                is_array($merged[$key]) &&
                is_array($value)) {
                // Merge middleware arrays to combine existing and new middleware
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                // For all other options, fluent configuration takes precedence
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}