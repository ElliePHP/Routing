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
    /**
     * Configuration state storage
     */
    private array $config;

    /**
     * Create a new configuration builder
     */
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
     * Add a single middleware to the global middleware stack
     * 
     * @param string|object $middleware Middleware class name or instance
     * @return self
     */
    public function addGlobalMiddleware(string|object $middleware): self
    {
        if (!isset($this->config['global_middleware'])) {
            $this->config['global_middleware'] = [];
        }
        $this->config['global_middleware'][] = $middleware;
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
        // Timing validation is handled by Router::configure() method
        // We don't need to check here as the Router facade will handle initialization timing

        // Get merged configuration for validation
        $routerClass = Router::class;
        $existingConfig = $routerClass::getConfig();
        $mergedConfig = $this->mergeConfigurations($existingConfig, $this->config);
        $finalConfig = $this->applyConfigurationRules($mergedConfig);

        // Validate routes directory if specified and not default
        if ($finalConfig['routes_directory'] !== '/' && !empty($finalConfig['routes_directory'])) {
            $this->validateRoutesDirectory($finalConfig['routes_directory']);
        }

        // Validate cache directory if specified
        if (isset($finalConfig['cache_directory'])) {
            $this->validateCacheDirectory($finalConfig['cache_directory']);
        }

        // Validate error formatter interface
        if (isset($finalConfig['error_formatter']) &&
            !($finalConfig['error_formatter'] instanceof ErrorFormatterInterface)) {
            throw new RouterException('Error formatter must implement ErrorFormatterInterface');
        }

        // Validate container interface
        if (isset($finalConfig['container']) &&
            !($finalConfig['container'] instanceof ContainerInterface)) {
            throw new RouterException('Container must implement PSR-11 ContainerInterface');
        }

        // Validate middleware array
        if (isset($finalConfig['global_middleware']) && !is_array($finalConfig['global_middleware'])) {
            throw new RouterException('Global middleware must be an array');
        }

        // Validate individual middleware items
        if (isset($finalConfig['global_middleware'])) {
            foreach ($finalConfig['global_middleware'] as $index => $middleware) {
                if (!is_string($middleware) && !is_object($middleware) && !is_callable($middleware)) {
                    throw new RouterException("Invalid middleware at index $index: must be string, object, or callable");
                }
            }
        }

        // Validate allowed domains array
        if (isset($finalConfig['allowed_domains']) && !is_array($finalConfig['allowed_domains'])) {
            throw new RouterException('Allowed domains must be an array');
        }

        // Validate individual domain patterns
        if (isset($finalConfig['allowed_domains'])) {
            foreach ($finalConfig['allowed_domains'] as $index => $domain) {
                if (!is_string($domain) || empty($domain)) {
                    throw new RouterException("Invalid domain at index $index: must be non-empty string");
                }
            }
        }

        // Validate boolean configuration options
        if (isset($finalConfig['debug_mode']) && !is_bool($finalConfig['debug_mode'])) {
            throw new RouterException('Debug mode must be a boolean value');
        }

        if (isset($finalConfig['cache_enabled']) && !is_bool($finalConfig['cache_enabled'])) {
            throw new RouterException('Cache enabled must be a boolean value');
        }

        if (isset($finalConfig['enforce_domain']) && !is_bool($finalConfig['enforce_domain'])) {
            throw new RouterException('Enforce domain must be a boolean value');
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
        // Check for path traversal attempts
        if (str_contains($path, '..')) {
            throw new RouterException('Path traversal detected in routes directory');
        }

        // If path exists, validate it's a readable directory
        if (file_exists($path)) {
            if (!is_dir($path)) {
                throw new RouterException("Routes directory is not a valid directory: $path");
            }
            
            if (!is_readable($path)) {
                throw new RouterException("Routes directory is not readable: $path");
            }
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
        // Check for path traversal attempts
        if (str_contains($path, '..')) {
            throw new RouterException('Path traversal detected in cache directory');
        }

        // If path exists, validate it's a writable directory
        if (file_exists($path)) {
            if (!is_dir($path)) {
                throw new RouterException("Cache directory is not a valid directory: $path");
            }
            
            if (!is_writable($path)) {
                throw new RouterException("Cache directory is not writable: $path");
            }
        }
    }

    /**
     * Apply the configuration to the Router facade
     * 
     * @throws RouterException If router is already initialized
     */
    private function applyConfiguration(): void
    {
        // Import the Router class to access its configure method
        $routerClass = Router::class;
        
        // Get existing configuration from Router to merge with fluent configuration
        $existingConfig = $routerClass::getConfig();
        
        // Merge configurations with fluent configuration taking precedence
        $mergedConfig = $this->mergeConfigurations($existingConfig, $this->config);
        
        // Apply debug mode and cache interaction rules
        $finalConfig = $this->applyConfigurationRules($mergedConfig);
        
        // Call the existing configure method with merged configuration
        $routerClass::configure($finalConfig);
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
            // Special handling for arrays that should be merged rather than replaced
            if ($key === 'global_middleware' && isset($merged[$key]) && is_array($merged[$key]) && is_array($value)) {
                // For global middleware, merge arrays to combine existing and new middleware
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                // For all other configuration options, fluent configuration takes precedence
                // This includes allowed_domains which should be replaced, not merged
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }

    /**
     * Apply configuration rules and interactions
     * 
     * @param array $config Configuration to process
     * @return array Configuration with rules applied
     */
    private function applyConfigurationRules(array $config): array
    {
        // Rule: Cache is disabled when debug mode is enabled
        if (isset($config['debug_mode']) && $config['debug_mode'] === true) {
            $config['cache_enabled'] = false;
            
            // Emit warning if debug mode is enabled in production
            if ((isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') || getenv('APP_ENV') === 'production') {
                trigger_error(
                    'WARNING: Debug mode is enabled in production environment. This exposes sensitive information.',
                    E_USER_WARNING
                );
            }
        }
        
        return $config;
    }
}