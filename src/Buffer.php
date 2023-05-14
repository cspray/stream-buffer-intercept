<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

interface Buffer {

    public function stopIntercepting() : void;

    public function output() : string;

    public function reset() : void;

}