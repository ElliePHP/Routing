<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class CallableRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private mixed $callable
    ){}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callable)($request);
    }
}