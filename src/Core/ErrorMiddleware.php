<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use ElliePHP\Components\Routing\Exceptions\RouteNotFoundException;
use ElliePHP\Components\Routing\Exceptions\RouterException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

class ErrorMiddleware extends AbstractErrorMiddleware
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        bool $debugMode = false
    ) {
        parent::__construct($debugMode);
    }

    protected function format(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        if ($e instanceof RouteNotFoundException) {
            $status = 404;
        }

        // Determine error message based on exception type and debug mode
        $message = $this->getErrorMessage($e, $this->debugMode);

        $data = [
            'error' => $message,
            'status' => $status,
        ];

        if ($this->debugMode) {
            $data['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader("Content-Type", "application/json");

        $stream = $this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream);
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
