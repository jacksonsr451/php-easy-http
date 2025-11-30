<?php

declare(strict_types=1);

namespace Amp {
    if (! class_exists(Loop::class)) {
        final class Loop
        {
            public static function run(callable $callback): void
            {
                $callback();
            }

            public static function stop(): void
            {
                // no-op for stub
            }
        }
    }
}

namespace Swoole {
    if (! class_exists(Coroutine::class)) {
        class Coroutine
        {
            public static function run(callable $callback): void
            {
                $callback();
            }
        }
    }
}

namespace Spiral\RoadRunner {
    if (! class_exists(Worker::class)) {
        class Worker
        {
            public function waitPayload(): void
            {
                // no-op stub method
            }
        }
    }
}
