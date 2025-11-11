<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouterException;

/**
 * Handles caching of compiled routes for production environments
 */
class RouteCache
{
    private string $cacheFile;

    public function __construct(string $cacheDirectory = '/tmp')
    {
        $this->cacheFile = rtrim($cacheDirectory, '/') . '/ellie_routes.cache';
    }

    /**
     * Check if cached routes exist and are valid
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    /**
     * Load routes from cache
     */
    public function load(): array
    {
        if (!$this->exists()) {
            throw new RouterException("Route cache file does not exist");
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            throw new RouterException("Failed to read route cache file");
        }

        $routes = unserialize($content, ['allowed_classes' => false]);
        if (!is_array($routes)) {
            throw new RouterException("Invalid route cache format");
        }

        return $routes;
    }

    /**
     * Save routes to cache
     */
    public function save(array $routes): void
    {
        $content = serialize($routes);
        $result = file_put_contents($this->cacheFile, $content, LOCK_EX);
        
        if ($result === false) {
            throw new RouterException("Failed to write route cache file");
        }
    }

    /**
     * Clear the route cache
     */
    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->cacheFile);
        }
    }

    /**
     * Get cache file path
     */
    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }
}
