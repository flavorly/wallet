<?php

namespace Flavorly\Wallet\Concerns;

use Closure;

trait EvaluatesClosures
{
    /**
     * Stolen from Filament, evaluate the closure with given params, and exclude some.
     * @param  mixed  $value
     * @param  array<string,mixed>  $parameters
     * @return mixed
     */
    protected function evaluate(mixed $value, array $parameters = []): mixed
    {
        if ($value instanceof Closure) {
            return app()->call(
                $value,
                $parameters
            );
        }

        return $value;
    }
}
