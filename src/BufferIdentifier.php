<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

interface BufferIdentifier {

    /**
     * @return non-empty-string
     */
    public function toString() : string;

}