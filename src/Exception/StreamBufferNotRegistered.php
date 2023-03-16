<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Exception;

final class StreamBufferNotRegistered extends Exception {

    public static function fromNotRegistered() : self {
        return new self('StreamBuffer::register() MUST be called before intercepting a stream.');
    }

}