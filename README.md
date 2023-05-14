# Stream Buffer Intercept

A PHP testing utility designed to capture stream output and facilitate writing unit tests for systems that require writing to streams.

## Installation

```shell
composer require --dev cspray/stream-buffer-intercept
```

## Usage Guide

There are scenarios where you may want to unit test some piece of code that writes to a stream. An example might be where you're writing tests to confirm log messages sent to `stdout` or `stderr`. The `Cspray\StreamBufferIntercept\StreamBuffer` class allows you to easily capture output sent to these streams, and others, to easily assert your expectations.

Let's take a look at a quick code example.

```php
<?php declare(strict_types=1);

namespace Cspray\StreamBufferDemo;

use Cspray\StreamBufferIntercept\Buffer;
use Cspray\StreamBufferIntercept\StreamFilter;
use PHPUnit\Framework\TestCase;

class MyLogger {

    private $stdout;
    private $stderr;

    public function __construct($stdout, $stderr) {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
    
    public function log(string $message) : void {
        fwrite($this->stdout, $message);
    }
    
    public function logError(string $message) : void {
        fwrite($this->stderr, $message);
    }

}

class MyLoggerTest extends TestCase {

    private Buffer $stdout;
    
    private Buffer $stderr;
    
    private MyLogger $subject;

    protected function setUp() : void{
        StreamFilter::register();
        $this->stdout = StreamFilter::intercept(STDOUT);
        $this->stderr = StreamFilter::intercept(STDERR);
        $this->subject = new MyLogger(STDOUT, STDERR);
    }
    
    protected function tearDown() : void{
        StreamFilter::stopIntercepting($this->stdout);
        StreamFilter::stopIntercepting($this->stderr);
    }
    
    public function testLogMessageSentToStdOutAndNotStdErr() : void {
        $this->subject->log('My stdout output'); 
        
        self::assertSame('My stdout output', $this->stdout->output());
        self::assertSame('', $this->stderr->output());
    }

    public function testLogErrorMessageSentToStdErrAndNotStdOut() : void {
        $this->subject->logError('My stderr output'); 
        
        self::assertSame('My stderr output', $this->stderr->output());
        self::assertSame('', $this->stdout->output());
    }
}
```