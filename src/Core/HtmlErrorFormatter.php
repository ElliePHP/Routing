<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Throwable;

class HtmlErrorFormatter implements ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        $message = $debugMode ? $e->getMessage() : 'An unexpected error occurred';

        $html = "<!DOCTYPE html>
<html>
<head>
    <title>Error {$status}</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin: 0 0 20px 0; }
        .message { color: #333; margin: 20px 0; }
        .debug { background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 20px; }
        pre { overflow-x: auto; }
    </style>
</head>
<body>
    <div class='error'>
        <h1>Error {$status}</h1>
        <div class='message'>{$message}</div>";

        if ($debugMode) {
            $html .= "
        <div class='debug'>
            <strong>Exception:</strong> " . get_class($e) . "<br>
            <strong>File:</strong> {$e->getFile()}<br>
            <strong>Line:</strong> {$e->getLine()}<br>
            <strong>Trace:</strong>
            <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
        </div>";
        }

        $html .= "
    </div>
</body>
</html>";

        return ['html' => $html, 'status' => $status];
    }
}