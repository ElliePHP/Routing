<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

/**
 * Provides debugging utilities for routes and middleware
 */
class RouteDebugger
{
    /**
     * Format routes for display
     */
    public function formatRoutes(array $routes): array
    {
        return array_map(function ($route) {
            return [
                'method' => $route['method'],
                'path' => $route['path'],
                'name' => $route['name'],
                'handler' => $this->formatHandler($route),
                'middleware' => $this->formatMiddleware($route['middleware'] ?? []),
                'domain' => $route['domain'] ?? null,
            ];
        }, $routes);
    }

    /**
     * Generate a route table as a string
     */
    public function generateRouteTable(array $routes): string
    {
        if (empty($routes)) {
            return "No routes registered.\n";
        }

        $output = "\n";
        $output .= str_repeat('=', 130) . "\n";
        $output .= sprintf("%-8s %-35s %-25s %-30s %s\n", 'METHOD', 'PATH', 'NAME', 'DOMAIN', 'HANDLER');
        $output .= str_repeat('=', 130) . "\n";

        foreach ($routes as $route) {
            $handler = $this->formatHandler($route);
            $domain = $route['domain'] ?? '*';
            $output .= sprintf(
                "%-8s %-35s %-25s %-30s %s\n",
                $route['method'],
                $route['path'],
                $route['name'],
                $domain,
                $handler
            );

            if (!empty($route['middleware'])) {
                $middlewareList = $this->formatMiddleware($route['middleware']);
                $output .= sprintf("         └─ Middleware: %s\n", implode(', ', $middlewareList));
            }
        }

        $output .= str_repeat('=', 130) . "\n";
        $output .= "Total routes: " . count($routes) . "\n\n";

        return $output;
    }

    /**
     * Format handler for display
     */
    private function formatHandler(array $route): string
    {
        if ($route['class'] !== '' && $route['handler'] !== null) {
            return $route['class'] . '@' . (is_string($route['handler']) ? $route['handler'] : 'closure');
        }

        if (is_callable($route['handler'])) {
            return 'Closure';
        }

        return 'Unknown';
    }

    /**
     * Format middleware for display
     */
    private function formatMiddleware(array $middleware): array
    {
        return array_map(static function ($mw) {
            if (is_string($mw)) {
                $parts = explode('\\', $mw);
                return end($parts);
            }
            if (is_object($mw)) {
                $parts = explode('\\', get_class($mw));
                return end($parts);
            }
            return 'Closure';
        }, $middleware);
    }

    /**
     * Get timing information
     */
    public function getTimingInfo(float $startTime): array
    {
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        return [
            'start' => $startTime,
            'end' => $endTime,
            'duration_ms' => round($duration, 2),
        ];
    }
}
