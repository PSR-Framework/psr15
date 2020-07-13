<?php

declare(strict_types=1);

namespace Furious\Psr15\Exception;

use DomainException;
use Throwable;

class MiddlewareAlreadyCalledException extends DomainException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        if ('' === $message) {
            $this->message = 'Cannot invoke pipeline handler $handler->handle() more than once';
        }
    }
}