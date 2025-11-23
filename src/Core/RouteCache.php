<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouterException;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Handles caching of compiled routes for production environments
 */
class RouteCache
{
    private string $cacheFile;
    private string $cacheDirectory;

    public function __construct(string $cacheDirectory = '/tmp')
    {
        $this->cacheDirectory = rtrim($cacheDirectory, '/');
        
        // Use unique cache filename to prevent predictable attacks
        $uniqueId = md5(__DIR__);
        $this->cacheFile = $this->cacheDirectory . '/ellie_routes_' . $uniqueId . '.cache';
        
        // Ensure cache directory exists and is writable
        if (!is_dir($this->cacheDirectory)) {
            throw new RouterException("Cache directory does not exist: {$this->cacheDirectory}");
        }
        
        if (!is_writable($this->cacheDirectory)) {
            throw new RouterException("Cache directory is not writable: {$this->cacheDirectory}");
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
     * Check if cache is valid based on version and file modification times
     * 
     * @param string $routesDirectory Directory containing route files
     * @param int|null $cacheVersion Optional cache version number
     * @return bool True if cache is valid, false if it should be invalidated
     */
    public function isValid(string $routesDirectory, ?int $cacheVersion = null): bool
    {
        if (!$this->exists()) {
            return false;
        }

        // Load cache metadata (only once, cache the result)
        static $cacheData = null;
        if ($cacheData === null) {
            $content = file_get_contents($this->cacheFile);
            if ($content === false) {
                return false;
            }

            $cacheData = json_decode($content, true);
            if (!is_array($cacheData)) {
                return false;
            }
        }

        $data = $cacheData;

        // Backward compatibility: if cache is old format (just routes array), it's invalid
        if (!isset($data['routes'])) {
            return false;
        }

        // Check cache version if provided
        if ($cacheVersion !== null && (!isset($data['version']) || $data['version'] !== $cacheVersion)) {
            return false;
        }

        // Check if route files have been modified (only if routes directory exists and is valid)
        // Only check mtime if we have it cached, otherwise skip the check for performance
        if (isset($data['route_files_mtime']) && is_dir($routesDirectory) && $routesDirectory !== '/') {
            // Only check mtime if cache was created recently (within last 5 seconds)
            // This avoids expensive directory scanning on every request
            $cacheAge = time() - ($data['cached_at'] ?? 0);
            if ($cacheAge < 5) {
                // Cache is very fresh, trust it
                return true;
            }
            
            try {
                $cachedMtime = $data['route_files_mtime'];
                $currentMtime = $this->getRoutesDirectoryMtime($routesDirectory);
                
                if ($currentMtime !== $cachedMtime) {
                    return false;
                }
            } catch (Throwable) {
                // If we can't check mtime, invalidate cache to be safe
                return false;
            }
        }

        return true;
    }

    /**
     * Get the maximum modification time of all route files in a directory
     */
    private function getRoutesDirectoryMtime(string $routesDirectory): int
    {
        if (!is_dir($routesDirectory)) {
            return 0;
        }

        $maxMtime = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($routesDirectory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $mtime = $file->getMTime();
                if ($mtime > $maxMtime) {
                    $maxMtime = $mtime;
                }
            }
        }

        return $maxMtime;
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

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RouterException("Invalid route cache format");
        }

        // Return routes array (backward compatible) or full data structure
        return $data['routes'] ?? $data;
    }

    /**
     * Save routes to cache with versioning and file modification tracking
     * @throws JsonException
     */
    public function save(array $routes, ?string $routesDirectory = null, ?int $version = null): void
    {
        $data = [
            'routes' => $routes,
            'cached_at' => time(),
        ];

        // Add version if provided
        if ($version !== null) {
            $data['version'] = $version;
        }

        // Add route files modification time if directory provided
        if ($routesDirectory !== null && is_dir($routesDirectory)) {
            $data['route_files_mtime'] = $this->getRoutesDirectoryMtime($routesDirectory);
        }

        $content = json_encode($data, JSON_THROW_ON_ERROR);
        
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
