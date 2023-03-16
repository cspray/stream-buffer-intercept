<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

enum InterceptResponseStrategy {
    case PassThru;
    case Trap;
    case FatalError;
}