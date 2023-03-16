<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

use Cspray\StreamBufferIntercept\Exception\BufferNotFound;
use Cspray\StreamBufferIntercept\Exception\InvalidResourceType;
use Cspray\StreamBufferIntercept\Exception\StreamBufferNotRegistered;
use php_user_filter;

final class StreamBuffer extends php_user_filter {

    /**
     * @var array<string, string>
     */
    private static array $buffers = [];

    /**
     * @var array<string, resource>
     */
    private static array $filters = [];

    /**
     * @var array<string, InterceptOptions>
     */
    private static array $options = [];

    public static function register() : void {
        if (!self::isRegistered()) {
            stream_filter_register('cspray-stream-buffer.*', self::class);
        }
    }

    private static function isRegistered() : bool {
        return in_array('cspray-stream-buffer.*', stream_get_filters(), true);
    }

    /**
     * @param resource $stream
     * @throws InvalidResourceType
     * @throws StreamBufferNotRegistered
     */
    public static function intercept($stream, InterceptOptions $options = new InterceptOptions()) : BufferIdentifier {
        if (!is_resource($stream)) {
            throw InvalidResourceType::fromStreamNotResource($stream);
        }

        if (!self::isRegistered()) {
            throw StreamBufferNotRegistered::fromNotRegistered();
        }

        $id = bin2hex(random_bytes(8));
        $filter = stream_filter_append($stream, 'cspray-stream-buffer.' . $id);
        $bufferId = new class('cspray-stream-buffer.' . $id) implements BufferIdentifier {

            public function __construct(
                private readonly string $id,
            ) {}

            public function toString() : string {
                return $this->id;
            }
        };

        self::$filters[$bufferId->toString()] = $filter;
        self::$options[$bufferId->toString()] = $options;
        return $bufferId;
    }

    public static function stopIntercepting(BufferIdentifier $bufferIdentifier) : void {
        if (!isset(self::$filters[$bufferIdentifier->toString()])) {
            throw BufferNotFound::fromBufferIdentifierNotFound($bufferIdentifier);
        }
        stream_filter_remove(self::$filters[$bufferIdentifier->toString()]);
        unset(self::$buffers[$bufferIdentifier->toString()]);
        unset(self::$filters[$bufferIdentifier->toString()]);
        unset(self::$options[$bufferIdentifier->toString()]);
    }

    public static function output(BufferIdentifier $bufferIdentifier) : string {
        if (!isset(self::$buffers[$bufferIdentifier->toString()])) {
            throw BufferNotFound::fromBufferIdentifierNotFound($bufferIdentifier);
        }
        return self::$buffers[$bufferIdentifier->toString()];
    }

    public static function reset(BufferIdentifier $bufferIdentifier) : void {
        if (!isset(self::$buffers[$bufferIdentifier->toString()])) {
            throw BufferNotFound::fromBufferIdentifierNotFound($bufferIdentifier);
        }
        self::$buffers[$bufferIdentifier->toString()] = '';
    }

    public static function resetAll() : void {
        foreach (array_keys(self::$buffers) as $bufferIdentifier) {
            self::$buffers[$bufferIdentifier] = '';
        }
    }

    /**
     * @return array<string, string>
     */
    public static function buffers() : array {
        return self::$buffers;
    }

    private function __construct() {}

    public function onCreate() : bool {
        self::$buffers[$this->filtername] = '';
        return true;
    }

    public function filter($in, $out, &$consumed, bool $closing) : int {
        while ($bucket = stream_bucket_make_writeable($in)) {
            self::$buffers[$this->filtername] .= $bucket->data;
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return match(self::$options[$this->filtername]->interceptResponseStrategy) {
            InterceptResponseStrategy::Trap => PSFS_FEED_ME,
            InterceptResponseStrategy::PassThru => PSFS_PASS_ON,
            InterceptResponseStrategy::FatalError => PSFS_ERR_FATAL
        };
    }

}
