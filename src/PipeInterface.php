<?php

declare(strict_types=1);

namespace Furious\Psr15;

use Psr\Http\Server\MiddlewareInterface;

interface PipeInterface
{
    public function pipe(MiddlewareInterface $middleware): void;
}