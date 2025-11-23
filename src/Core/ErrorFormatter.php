<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use Throwable;

class ErrorFormatter implements ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        // Determine error message based on exception type and debug mode
        $message = $this->getErrorMessage($e, $debugMode);

        $data = [
            'error' => $message,
            'status' => $status,
        ];

        if ($debugMode) {
            $data['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return $data;
    }

    /**
     * Get appropriate error message based on exception type and debug mode
     */
    private function getErrorMessage(Throwable $e, bool $debugMode): string
    {
        if ($debugMode) {
            return $e->getMessage();
        }

        if ($e instanceof RouteNotFoundException) {
            return $e->getMessage();
        }

        if ($e instanceof RouterException) {
            $code = $e->getCode();
            if ($code >= 400 && $code < 500) {
                return $e->getMessage();
            }
        }

        // For all other exceptions in production, hide details
        return 'An unexpected error occurred';
    }
}
