<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Exception;

use Cspray\StreamBufferIntercept\BufferIdentifier;

final class BufferNotFound extends Exception {

    public static function fromBufferIdentifierNotFound(BufferIdentifier $bufferIdentifier) : self {
        return new self(sprintf(
            'Unable to find a buffer matching identifier "%s".',
            $bufferIdentifier->toString()
        ));
    }

}