<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Exception;

use Exception as PhpException;
use Throwable;

abstract class Exception extends PhpException {

    protected function __construct(string $message = "", int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

}