<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Exception;

final class InvalidResourceType extends Exception {

    public static function fromStreamNotResource(mixed $stream) : self {
        return new self(sprintf(
            'The stream to intercept MUST be a "resource" type but provided "%s".',
            gettype($stream)
        ));
    }

}