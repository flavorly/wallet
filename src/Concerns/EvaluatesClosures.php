<?php

namespace Flavorly\Wallet\Concerns;

use Closure;

trait EvaluatesClosures
{
    /**
     * Stolen from Filament, evaluate the closure with given params, and exclude some.
     */
    protected function evaluate($value, array $parameters = []): mixed
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
