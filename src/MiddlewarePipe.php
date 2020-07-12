<?php

declare(strict_types=1);

namespace Furious\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

final class MiddlewarePipe
    implements MiddlewareInterface,
        RequestHandlerInterface,
        PipeInterface
{
    private ?SplQueue $pipeline;

    public function __construct()
    {
        $this->pipeline = new SplQueue();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new Next($this->pipeline, $handler))->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->process($request, new EmptyPipelineHandler);
    }

    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->pipeline->enqueue($middleware);
    }
}