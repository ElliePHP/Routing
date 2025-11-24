<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

abstract class AbstractErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected bool $debugMode = false
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->format($e, $request);
        }
    }

    abstract protected function format(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
