<?php

namespace Flavorly\Wallet\Helpers;

use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Money\Context\AutoContext;
use Brick\Money\Money;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Services\ConfigurationService;

final class MoneyValueFormatter
{
    public function __construct(
        protected int|float|string $value = 0,
        protected int $decimals = 10,
        protected string $currency = 'USD',
    ) {}

    /**
     * Get the raw value
     */
    public function raw(): int|float|string|null
    {
        return $this->value;
    }

    /**
     * Get the value as a Money instance
     */
    public function toMoney(): Money
    {
        return Money::of(
            $this->toNumber(),
            ConfigurationService::getCurrency(),
            new AutoContext,
        );
    }

    /**
     * Get the value as a string
     */
    public function toString(): string
    {
        return $this->toNumber()->toString();
    }

    /**
     * Get the value as a float
     */
    public function toFloat(): float
    {
        return $this->toNumber()->toFloat();
    }

    /**
     * Get the value as a Math instance
     *
     * @throws DivisionByZeroException
     * @throws MathException
     * @throws NumberFormatException
     */
    public function toNumber(): Math
    {
        return Math::of(
            $this->value,
            $this->decimals,
            $this->decimals,
        )->fromStorage();
    }
}
