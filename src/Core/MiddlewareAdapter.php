<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class MiddlewareAdapter
{
    public function __construct(
        private ?ContainerInterface $container = null
    ) {}

    /**
     * Convert various middleware types to PSR-15 compatible format
     */
    public function adapt(mixed $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof Closure) {
            return new readonly class($middleware) implements MiddlewareInterface {
                public function __construct(private Closure $closure) {}

                public function process(
                    ServerRequestInterface $request,
                    RequestHandlerInterface $handler
                ): ResponseInterface {
                    return ($this->closure)($request, static fn($req): ResponseInterface => $handler->handle($req));
                }
            };
        }

        if (is_string($middleware) && class_exists($middleware)) {
            // Try to resolve from container first
            if ($this->container !== null && $this->container->has($middleware)) {
                $instance = $this->container->get($middleware);
            } else {
                $instance = new $middleware();
            }
            
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
        }

        throw new InvalidArgumentException(
            'Middleware must be a PSR-15 MiddlewareInterface, Closure, or class name'
        );
    }
}