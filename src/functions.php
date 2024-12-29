<?php

use FreerkMinnema\Synchronized\HashFromBacktrace;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

if (! function_exists('synchronized')) {
    /**
     * Ensures a closure is treated as a "critical section"
     * and is executed one-at-a-time, using an atomic cache lock.
     *
     * Optionally, the result will be cached and shared between simultaneous calls.
     *
     * @template  TReturnType
     *
     * @param  callback(): TReturnType  $callback
     * @param  int  $ttl
     * @param  bool $shareReplay
     * @return TReturnType
     * @throws LockTimeoutException
     */
    function synchronized(callable $callback, string|callable|Model|null $key = null, int $ttl = 60, bool|int $shareResult = false): mixed
    {
        if (is_null($key)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
            $key = (new HashFromBacktrace)($trace, $callback);
        } elseif (is_callable($key)) {
            $key = $key();
        } elseif ($key instanceof Model) {
            $key = get_class($key).':'.json_encode($key->getKey());
        }

        if (boolval($shareResult)) {
            $callback = fn () => Cache::remember($key.':result', is_bool($shareResult) ? $ttl : abs($shareResult), $callback);
        } else {
            Cache::forget($key.':result');
        }

        return Cache::lock($key.':lock', $ttl)->block($ttl, $callback);
    }
}