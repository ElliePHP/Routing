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
        // Use unique cache filename to prevent predictable attacks
        $uniqueId = md5(__DIR__);
        $this->cacheFile = rtrim($cacheDirectory, '/') . '/ellie_routes_' . $uniqueId . '.cache';
        
        // Ensure cache directory exists and is writable
        if (!is_dir($cacheDirectory)) {
            throw new RouterException("Cache directory does not exist: $cacheDirectory");
        }
        
        if (!is_writable($cacheDirectory)) {
            throw new RouterException("Cache directory is not writable: $cacheDirectory");
        }
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

        $routes = json_decode($content, true);
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
        $content = json_encode($routes);
        if ($content === false) {
            throw new RouterException("Failed to encode routes for caching");
        }
        
        $result = file_put_contents($this->cacheFile, $content, LOCK_EX);
        
        if ($result === false) {
            throw new RouterException("Failed to write route cache file");
        }
        
        // Set restrictive permissions on cache file
        chmod($this->cacheFile, 0600);
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
