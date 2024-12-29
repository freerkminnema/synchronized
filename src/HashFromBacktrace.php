<?php

namespace FreerkMinnema\Synchronized;

use Closure;
use Laravel\SerializableClosure\Support\ReflectionClosure;

class HashFromBacktrace
{
    public function __invoke(array $trace, callable $callable): string
    {
        throw_if(
            str_contains($trace[0]['file'] ?? '', 'eval()\'d code'),
            CannotCreateHashWithinEvalException::class,
        );

        $uses = array_map(
            fn (mixed $argument) => is_object($argument) ? spl_object_hash($argument) : $argument,
            $callable instanceof Closure ? (new ReflectionClosure($callable))->getClosureUsedVariables() : [],
        );

        return md5(sprintf(
            '%s@%s%s:%s (%s)',
            $trace[0]['file'],
            isset($trace[1]['class']) ? ($trace[1]['class'].'@') : '',
            $trace[1]['function'],
            $trace[0]['line'],
            serialize($uses),
        ));
    }
}
