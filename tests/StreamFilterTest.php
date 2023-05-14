<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Test;

use Cspray\StreamBufferIntercept\Exception\BufferNotFound;
use Cspray\StreamBufferIntercept\Exception\Exception;
use Cspray\StreamBufferIntercept\Exception\InvalidResourceType;
use Cspray\StreamBufferIntercept\Exception\StreamBufferNotRegistered;
use Cspray\StreamBufferIntercept\InterceptOptions;
use Cspray\StreamBufferIntercept\InterceptResponseStrategy;
use Cspray\StreamBufferIntercept\StreamFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(StreamFilter::class),
    CoversClass(InterceptOptions::class),
    CoversClass(InterceptResponseStrategy::class),
    CoversClass(Exception::class),
    CoversClass(BufferNotFound::class),
    CoversClass(InvalidResourceType::class),
    CoversClass(StreamBufferNotRegistered::class),
]
final class StreamFilterTest extends TestCase {

    private $resource;

    protected function setUp() : void {
        $this->resource = fopen('php://memory', 'wb');
    }

    protected function tearDown() : void {
        fclose($this->resource);
    }

    public function testInterceptNonResourceTypeThrowsException() : void {
        $this->expectException(InvalidResourceType::class);
        $this->expectExceptionMessage('The stream to intercept MUST be a "resource" type but provided "string".');

        StreamFilter::intercept('something');
    }

    #[RunInSeparateProcess]
    public function testInterceptBeforeRegisterThrowsException() : void {
        $this->expectException(StreamBufferNotRegistered::class);
        $this->expectExceptionMessage('StreamBuffer::register() MUST be called before intercepting a stream.');

        StreamFilter::intercept($this->resource);
    }

    public function testRegisterAddsAppropriateFilter() : void {
        StreamFilter::register();

        self::assertContains(
            'cspray-stream-buffer.*',
            stream_get_filters()
        );
    }

    #[RunInSeparateProcess]
    public function testInterceptAddsBuffers() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        $buffers = StreamFilter::buffers();

        self::assertCount(1, $buffers);
        self::assertIsList($buffers);
        self::assertContainsEquals($buffer, $buffers);
    }

    #[RunInSeparateProcess]
    public function testResetAllResetsBuffersToEmptyCollection() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        fwrite($this->resource, 'Some content');

        self::assertSame('Some content', $buffer->output());

        StreamFilter::resetAll();

        self::assertEmpty($buffer->output());

    }

    #[RunInSeparateProcess]
    public function testWritingToInterceptedStreamAddedToCorrectOutput() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame(
            'Content written to stream',
            $buffer->output()
        );
    }

    #[RunInSeparateProcess]
    public function testResetIndividualBufferClearsToEmptyString() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', $buffer->output());

        $buffer->reset();

        self::assertSame('', $buffer->output());
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingRemovesBufferedResourceFromCache() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        fwrite($this->resource, 'Need to write something');

        $buffer->stopIntercepting();

        self::assertSame([], StreamFilter::buffers());
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingBufferIdentifierNotFoundThrowsException() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);
        $buffer->stopIntercepting();

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Attempted to stop intercepting a buffer that is not currently intercepting any stream. Please only call Buffer::stopIntercepting once per Buffer.');

        $buffer->stopIntercepting();
    }

    #[RunInSeparateProcess]
    public function testOutputForBufferStoppedInterceptingThrowsException() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        $buffer->stopIntercepting();

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Attempted to get output for a buffer that is not currently intercepting any stream. Please ensure Buffer::output is not called after Buffer::stopIntercepting.');

        $buffer->output();
    }

    #[RunInSeparateProcess]
    public function testResetForBufferNotFoundThrowsException() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        $buffer->stopIntercepting();

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Attempted to reset a buffer that is not currently intercepting any stream. Please ensure Buffer::reset is not called after Buffer::stopIntercepting.');

        $buffer->reset();
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsDefaultDoesNotHaveContentsInResource() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', $buffer->output());

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsTrapDoesNotHaveContentsInResource() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::Trap));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', $buffer->output());

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsPassThruDoesHaveContentsInResource() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::PassThru));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', $buffer->output());

        rewind($this->resource);
        self::assertSame('Content written to stream', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsFatalErrorThrowsError() : void {
        StreamFilter::register();

        $buffer = StreamFilter::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::FatalError));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', $buffer->output());

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testWritingToSeparateStreams() : void {
        StreamFilter::register();

        $memory = StreamFilter::intercept($this->resource);
        $stdout = StreamFilter::intercept(STDOUT);

        fwrite($this->resource, 'Memory output');
        fwrite(STDOUT, 'stdout output');

        self::assertSame('Memory output', $memory->output());
        self::assertSame('stdout output', $stdout->output());
    }

}
