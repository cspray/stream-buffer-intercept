<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

final class InterceptOptions {

    public function __construct(
        public readonly InterceptResponseStrategy $interceptResponseStrategy = InterceptResponseStrategy::Trap
    ) {}

}