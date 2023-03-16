<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept\Test;

use Cspray\StreamBufferIntercept\BufferIdentifier;
use Cspray\StreamBufferIntercept\Exception\BufferNotFound;
use Cspray\StreamBufferIntercept\Exception\InvalidResourceType;
use Cspray\StreamBufferIntercept\Exception\StreamBufferNotRegistered;
use Cspray\StreamBufferIntercept\InterceptOptions;
use Cspray\StreamBufferIntercept\InterceptResponseStrategy;
use Cspray\StreamBufferIntercept\StreamBuffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(StreamBuffer::class),
    CoversClass(InterceptOptions::class),
    CoversClass(InterceptResponseStrategy::class)
]
final class StreamBufferTest extends TestCase {

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

        StreamBuffer::intercept('something');
    }

    #[RunInSeparateProcess]
    public function testInterceptBeforeRegisterThrowsException() : void {
        $this->expectException(StreamBufferNotRegistered::class);
        $this->expectExceptionMessage('StreamBuffer::register() MUST be called before intercepting a stream.');

        StreamBuffer::intercept($this->resource);
    }

    public function testRegisterAddsAppropriateFilter() : void {
        StreamBuffer::register();

        self::assertContains(
            'cspray-stream-buffer.*',
            stream_get_filters()
        );
    }

    #[RunInSeparateProcess]
    public function testInterceptAddsBuffers() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        self::assertSame(
            [$id->toString() => ''],
            StreamBuffer::buffers()
        );
    }

    #[RunInSeparateProcess]
    public function testResetAllResetsBuffersToEmptyCollection() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        StreamBuffer::resetAll();

        self::assertSame([$id->toString() => ''], StreamBuffer::buffers());
    }

    #[RunInSeparateProcess]
    public function testWritingToInterceptedStreamAddedToCorrectOutput() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame(
            'Content written to stream',
            StreamBuffer::output($id)
        );
    }

    #[RunInSeparateProcess]
    public function testResetIndividualBufferClearsToEmptyString() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', StreamBuffer::output($id));

        StreamBuffer::reset($id);

        self::assertSame('', StreamBuffer::output($id));
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingRemovesBufferedResourceFromCache() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Need to write something');

        StreamBuffer::stopIntercepting($id);

        $buffers = (new \ReflectionProperty(StreamBuffer::class, 'buffers'))->getValue();

        self::assertSame([], $buffers);
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingBufferIdentifierNotFoundThrowsException() : void {
        StreamBuffer::register();

        $id = $this->getMockBuilder(BufferIdentifier::class)->getMock();
        $id->expects($this->any())->method('toString')->willReturn('something');

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Unable to find a buffer matching identifier "something".');

        StreamBuffer::stopIntercepting($id);
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingRemovesFilteredResourceFromCache() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Need to write something');

        StreamBuffer::stopIntercepting($id);

        $filters = (new \ReflectionProperty(StreamBuffer::class, 'filters'))->getValue();

        self::assertSame([], $filters);
    }

    #[RunInSeparateProcess]
    public function testStopInterceptingRemovesOptionsFromCache() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Need to write something');

        StreamBuffer::stopIntercepting($id);

        $options = (new \ReflectionProperty(StreamBuffer::class, 'options'))->getValue();

        self::assertSame([], $options);
    }

    #[RunInSeparateProcess]
    public function testOutputForBufferNotFoundThrowsException() : void {
        StreamBuffer::register();

        $id = $this->getMockBuilder(BufferIdentifier::class)->getMock();
        $id->expects($this->any())->method('toString')->willReturn('foobar');

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Unable to find a buffer matching identifier "foobar".');

        StreamBuffer::output($id);
    }

    #[RunInSeparateProcess]
    public function testResetForBufferNotFoundThrowsException() : void {
        StreamBuffer::register();

        $id = $this->getMockBuilder(BufferIdentifier::class)->getMock();
        $id->expects($this->any())->method('toString')->willReturn('foobar');

        $this->expectException(BufferNotFound::class);
        $this->expectExceptionMessage('Unable to find a buffer matching identifier "foobar".');

        StreamBuffer::reset($id);
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsDefaultDoesNotHaveContentsInResource() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource);

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', StreamBuffer::output($id));

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsTrapDoesNotHaveContentsInResource() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::Trap));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', StreamBuffer::output($id));

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsPassThruDoesHaveContentsInResource() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::PassThru));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', StreamBuffer::output($id));

        rewind($this->resource);
        self::assertSame('Content written to stream', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testInterceptOptionsFatalErrorThrowsError() : void {
        StreamBuffer::register();

        $id = StreamBuffer::intercept($this->resource, new InterceptOptions(InterceptResponseStrategy::FatalError));

        fwrite($this->resource, 'Content written to stream');

        self::assertSame('Content written to stream', StreamBuffer::output($id));

        rewind($this->resource);
        self::assertSame('', stream_get_contents($this->resource));
    }

    #[RunInSeparateProcess]
    public function testWritingToSeparateStreams() : void {
        StreamBuffer::register();

        $memory = StreamBuffer::intercept($this->resource);
        $stdout = StreamBuffer::intercept(STDOUT);

        fwrite($this->resource, 'Memory output');
        fwrite(STDOUT, 'stdout output');

        self::assertSame('Memory output', StreamBuffer::output($memory));
        self::assertSame('stdout output', StreamBuffer::output($stdout));
    }

}
