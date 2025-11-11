<?php

namespace ElliePHP\Components\Routing\Core;

use Throwable;

/**
 * Handles error response formatting
 */
interface ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array;
}