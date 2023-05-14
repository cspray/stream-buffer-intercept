<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Exception;

final class BufferNotFound extends Exception {

    public static function fromStopInterceptingMissingBuffer() : self {
        return new self(sprintf(
            'Attempted to stop intercepting a buffer that is not currently intercepting any stream. Please only call Buffer::stopIntercepting once per Buffer.',
        ));
    }

    public static function fromOutputMissingBuffer() : self {
        return new self('Attempted to get output for a buffer that is not currently intercepting any stream. Please ensure Buffer::output is not called after Buffer::stopIntercepting.');
    }

    public static function fromResetMissingBuffer() : self {
        return new self('Attempted to reset a buffer that is not currently intercepting any stream. Please ensure Buffer::reset is not called after Buffer::stopIntercepting.');
    }

}
