<?php

declare(strict_types=1);

namespace Furious\Psr15;

use Furious\Psr15\Exception\EmptyPipelineException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class EmptyPipelineHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new EmptyPipelineException();
    }
}