<?php

use FreerkMinnema\Synchronized\HashFromBacktrace;

it('generates identical hashes for the same callable', function () {
    $foo = 'bar';
    $fizz = 'buzz';
    $callable_1 = static fn () => $foo;
    unset($fizz);
    $callable_2 = fn () => $foo;
    expect((new HashFromBacktrace)(
        debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2),
        $callable_1,
    ))->toBe((new HashFromBacktrace)(
        debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2),
        $callable_2,
    ));
});

it('generates different hashes for different callables', function () {
    $var = 'foo';
    $callable_foo = static fn () => $var;
    $var = 'bar';
    $callable_bar = static fn () => $var;
    expect((new HashFromBacktrace)(
        debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2),
        $callable_foo,
    ))->not->toBe((new HashFromBacktrace)(
        debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2),
        $callable_bar,
    ));
});