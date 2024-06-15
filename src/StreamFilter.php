<?php declare(strict_types=1);

namespace Cspray\StreamBufferIntercept;

use Closure;
use Cspray\StreamBufferIntercept\Exception\BufferNotFound;
use Cspray\StreamBufferIntercept\Exception\InvalidResourceType;
use Cspray\StreamBufferIntercept\Exception\StreamBufferNotRegistered;
use php_user_filter;

final class StreamFilter extends php_user_filter {

    private const BUFFER_REGISTER = 'cspray-stream-buffer.*';

    /**
     * @var array<string, array{output: string, buffer: Buffer, filter: resource, options: InterceptOptions}>
     */
    private static array $cache = [];

    public static function register() : void {
        if (!self::isRegistered()) {
            stream_filter_register(self::BUFFER_REGISTER, self::class);
        }
    }

    private static function isRegistered() : bool {
        return in_array(self::BUFFER_REGISTER, stream_get_filters(), true);
    }

    /**
     * @param resource $stream
     * @throws InvalidResourceType
     * @throws StreamBufferNotRegistered
     */
    public static function intercept($stream, InterceptOptions $options = new InterceptOptions()) : Buffer {
        if (!is_resource($stream)) {
            throw InvalidResourceType::fromStreamNotResource($stream);
        }

        if (!self::isRegistered()) {
            throw StreamBufferNotRegistered::fromNotRegistered();
        }

        $id = bin2hex(random_bytes(8));
        $filter = stream_filter_append($stream, 'cspray-stream-buffer.' . $id);
        $bufferId = 'cspray-stream-buffer.' . $id;
        $buffer = new class(
            function() use($bufferId) : string {
                if (!isset(self::$cache[$bufferId])) {
                    throw BufferNotFound::fromOutputMissingBuffer();
                }
                return self::$cache[$bufferId]['output'];
            },
            function() use($bufferId) : void {
                if (!isset(self::$cache[$bufferId])) {
                    throw BufferNotFound::fromResetMissingBuffer();
                }
                self::$cache[$bufferId]['output'] = '';
            },
            function() use($bufferId) : void {
                if (!isset(self::$cache[$bufferId])) {
                    throw BufferNotFound::fromStopInterceptingMissingBuffer();
                }
                stream_filter_remove(self::$cache[$bufferId]['filter']);
                unset(self::$cache[$bufferId]);
            }
        ) implements Buffer {

            public function __construct(
                private readonly Closure $output,
                private readonly Closure $reset,
                private readonly Closure $stopIntercepting
            ) {}

            public function output() : string {
                return ($this->output)();
            }

            public function reset() : void {
                ($this->reset)();
            }

            public function stopIntercepting() : void {
                ($this->stopIntercepting)();
            }
        };

        self::$cache[$bufferId] = [
            'output' => '',
            'buffer' => $buffer,
            'filter' => $filter,
            'options' => $options
        ];

        return $buffer;
    }

    public static function resetAll() : void {
        foreach (array_keys(self::$cache) as $id) {
            self::$cache[$id]['output'] = '';
        }
    }

    /**
     * @return list<Buffer>
     */
    public static function buffers() : array {
        $buffers = [];
        foreach (array_keys(self::$cache) as $key) {
            $buffers[] = self::$cache[$key]['buffer'];
        }
        return $buffers;
    }

    private function __construct() {}

    public function filter($in, $out, &$consumed, bool $closing) : int {
        while ($bucket = stream_bucket_make_writeable($in)) {
            self::$cache[$this->filtername]['output'] .= $bucket->data;
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        if ($closing) {
            unset(self::$cache[$this->filtername]);
            return PSFS_FEED_ME;
        }

        return match(self::$cache[$this->filtername]['options']->interceptResponseStrategy) {
            InterceptResponseStrategy::Trap => PSFS_FEED_ME,
            InterceptResponseStrategy::PassThru => PSFS_PASS_ON,
            InterceptResponseStrategy::FatalError => PSFS_ERR_FATAL
        };
    }

}
