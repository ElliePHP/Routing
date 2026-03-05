<?php

declare(strict_types=1);

use ElliePHP\Components\Routing\Router;

if (!function_exists('route')) {
    /**
     * Generate a URL for a given route name.
     *
     * @param string $name
     * @param array $parameters
     * @param bool $absolute
     * @return string
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return Router::route($name, $parameters, $absolute);
    }
}
