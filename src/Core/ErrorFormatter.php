<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Throwable;

class ErrorFormatter implements ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        $data = [
            'error' => $debugMode ? $e->getMessage() : 'An unexpected error occurred',
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
}
