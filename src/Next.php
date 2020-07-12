<?php

declare(strict_types=1);

namespace Furious\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

final class Next implements RequestHandlerInterface
{
    private ?SplQueue $queue;
    private RequestHandlerInterface $handler;

    /**
     * Next constructor.
     * @param SplQueue $queue
     * @param RequestHandlerInterface $handler
     */
    public function __construct(?SplQueue $queue, RequestHandlerInterface $handler)
    {
        $this->queue = clone $queue;
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->queue->isEmpty()) {
            return $this->handler->handle($request);
        }

        /** @var MiddlewareInterface $middleware */
        $middleware = $this->queue->dequeue();

        $next = clone $this;
        $this->queue = null;

        return $middleware->process($request, $next);
    }
}