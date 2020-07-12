<?php

declare(strict_types=1);

namespace Furious\Psr15\Exception;

use OutOfBoundsException;
use Throwable;

class EmptyPipelineException extends OutOfBoundsException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        if ('' === $message) {
            $this->message = 'No middleware available to process the request';
        }
    }
}