<?php

use FreerkMinnema\Synchronized\CannotGenerateAtomicLockKeyException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

it('works', function () {
    expect(synchronized(fn () => rand(1, 100)))->toBeBetween(1, 100);
});

it('works with different "uses"', function () {
    $var = 'foo';
    expect(synchronized(fn () => $var))->toBe($var);
    $var = 'bar';
    expect(synchronized(fn () => $var))->toBe($var);
});

it('works concurrently', function () {
    Cache::put('counter', 0);
    for ($i = 0; $i < 100; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) { // child process, increment the counter and exit with that value as the result code
            exit(synchronized(function () {
                return Cache::increment('counter');
            }));
        } elseif ($pid > 0) { // main process
            $pids[] = $pid;
        };
    }
    $results = [];
    foreach ($pids as $pid) {
        $status = null;
        pcntl_waitpid($pid, $status);
        if (pcntl_wifexited($status)) {
            $results[] = pcntl_wexitstatus($status);
        }
    }
    expect($results)->toEqualCanonicalizing(range(1, 100));
});

it('throws the proper exception when used from eval', function () {
    expect(function () {
        $result = eval('synchronized(fn () => true);');
        dd($result);
    })->toThrow(CannotGenerateAtomicLockKeyException::class);
});

it('throws the proper exception when the lock expires before the callback finishes', function () {
    $key = 'long-running-callback';
    $pid = pcntl_fork();
    if ($pid === 0) { // child process
        synchronized(fn () => sleep(3), $key);
        exit(0);
    } elseif ($pid > 0) { // main process
        sleep(1); // wait 1 sec to ensure the child process gets the lock first
        expect(fn () => synchronized(fn () => null, $key, 1))->toThrow(LockTimeoutException::class);
    };
});

it('can use an Eloquent model as a key', function () {
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'users';
        protected $guarded = [];
    };
    $model->id = 1;
    expect(synchronized(fn () => 'foo'), $model)->toBe('foo');
});

it('can use a callable as a key', function () {
    expect(synchronized(fn () => 'foo', 'bar'))->toBe(synchronized(fn () => 'foo', fn () => 'bar'));
});

it('can share the result between parallel calls', function () {
    foreach (range(1, 100) as $fork) {
        $pid = pcntl_fork();
        if ($pid === 0) { // child process
            exit(synchronized(fn () => rand(0, PHP_INT_MAX), shareResult: true));
        } elseif ($pid > 0) { // main process
            $pids[] = $pid;
        };
    }
    $results = [];
    foreach ($pids as $pid) {
        $status = null;
        pcntl_waitpid($pid, $status);
        if (pcntl_wifexited($status)) {
            $results[$pid] = pcntl_wexitstatus($status);
        }
    }
    expect(count($results))->toBe(100);
    foreach ($results as $i => $result) {
        expect($result)->toBe($results[$i+1] ?? $result);
    }
});