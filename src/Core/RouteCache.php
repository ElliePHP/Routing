<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouterException;
use ElliePHP\Components\Support\Util\Json;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Handles caching of compiled routes for production environments
 */
class RouteCache
{
    private const int CACHE_FRESHNESS_THRESHOLD = 5; // seconds
    private const int CACHE_FILE_PERMISSIONS = 0600;

    public string $cacheFile {
        get {
            return $this->cacheFile;
        }
    }
    private string $cacheDirectory;
    private ?array $cachedData = null;

    public function __construct(string $cacheDirectory = '/tmp')
    {
        $this->cacheDirectory = rtrim($cacheDirectory, '/');
        $this->validateCacheDirectory();

        $uniqueId = md5(__DIR__);
        $this->cacheFile = "$this->cacheDirectory/ellie_routes_$uniqueId.cache";
    }

    /**
     * Validate cache directory exists and is writable
     *
     * @throws RouterException
     */
    private function validateCacheDirectory(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            throw new RouterException("Cache directory does not exist: $this->cacheDirectory");
        }

        if (!is_writable($this->cacheDirectory)) {
            throw new RouterException("Cache directory is not writable: $this->cacheDirectory");
        }
    }

    /**
     * Check if cached routes exist
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

        $cacheData = $this->loadCacheData();
        if ($cacheData === null) {
            return false;
        }

        // Validate cache structure
        if (!isset($cacheData['routes'])) {
            return false;
        }

        // Validate version if provided
        if ($cacheVersion !== null && $this->isCacheVersionMismatch($cacheData, $cacheVersion)) {
            return false;
        }

        // Validate route files haven't changed
        return $this->areRouteFilesUnchanged($cacheData, $routesDirectory);
    }

    /**
     * Load and cache the cache data structure
     */
    private function loadCacheData(): ?array
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        $content = @file_get_contents($this->cacheFile);
        if ($content === false) {
            return null;
        }

        $data = Json::decode($content);
        if (!is_array($data)) {
            return null;
        }

        $this->cachedData = $data;
        return $data;
    }

    /**
     * Check if cache version matches expected version
     */
    private function isCacheVersionMismatch(array $cacheData, int $expectedVersion): bool
    {
        return !isset($cacheData['version']) || $cacheData['version'] !== $expectedVersion;
    }

    /**
     * Check if route files have been modified since cache creation
     */
    private function areRouteFilesUnchanged(array $cacheData, string $routesDirectory): bool
    {
        // Skip mtime check if not tracked in cache
        if (!isset($cacheData['route_files_mtime'])) {
            return true;
        }

        // Validate routes directory
        if ($routesDirectory === '/' || !is_dir($routesDirectory)) {
            return true;
        }

        // Trust very fresh cache (within threshold)
        $cacheAge = time() - ($cacheData['cached_at'] ?? 0);
        if ($cacheAge < self::CACHE_FRESHNESS_THRESHOLD) {
            return true;
        }

        try {
            $cachedMtime = $cacheData['route_files_mtime'];
            $currentMtime = $this->getRoutesDirectoryMtime($routesDirectory);

            return $currentMtime === $cachedMtime;
        } catch (Throwable) {
            // Invalidate cache if we can't verify mtime
            return false;
        }
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
            new RecursiveDirectoryIterator($routesDirectory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $mtime = $file->getMTime();
                $maxMtime = max($maxMtime, $mtime);
            }
        }

        return $maxMtime;
    }

    /**
     * Load routes from cache
     *
     * @return array
     * @throws RouterException
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

        $data = Json::decode($content);
        if (!is_array($data)) {
            throw new RouterException("Invalid route cache format");
        }

        // Return routes array with backward compatibility
        return $data['routes'] ?? $data;
    }

    /**
     * Save routes to cache with versioning and file modification tracking
     *
     * @param array $routes Routes to cache
     * @param string|null $routesDirectory Optional routes directory for mtime tracking
     * @param int|null $version Optional cache version
     * @throws RouterException
     */
    public function save(array $routes, ?string $routesDirectory = null, ?int $version = null): void
    {
        $data = [
            'routes' => $routes,
            'cached_at' => time(),
        ];

        if ($version !== null) {
            $data['version'] = $version;
        }

        if ($routesDirectory !== null && is_dir($routesDirectory)) {
            $data['route_files_mtime'] = $this->getRoutesDirectoryMtime($routesDirectory);
        }

        try {
            $content = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RouterException("Failed to encode routes to JSON: {$e->getMessage()}", 0, $e);
        }

        $result = file_put_contents($this->cacheFile, $content, LOCK_EX);
        if ($result === false) {
            throw new RouterException("Failed to write route cache file");
        }

        @chmod($this->cacheFile, self::CACHE_FILE_PERMISSIONS);

        // Clear in-memory cache
        $this->cachedData = null;
    }

    /**
     * Clear the route cache
     */
    public function clear(): void
    {
        if ($this->exists()) {
            @unlink($this->cacheFile);
            $this->cachedData = null;
        }
    }

}