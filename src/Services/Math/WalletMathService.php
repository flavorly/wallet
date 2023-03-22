<?php

namespace Flavorly\Wallet\Services\Math;

class WalletMathService implements MathServiceInterface
{
    public function __construct(
        protected readonly int $decimalPlaces,
        protected MathService|null $baseMathService = null,
    ) {
        if (null === $this->baseMathService) {
            $this->baseMathService = new MathService(0);
        }
    }

    public function toInteger(float|int|string $value): string
    {
        $decimalPlaces = $this->baseMathService->powTen($this->decimalPlaces);

        return $this->baseMathService->round(
            $this->baseMathService->mul(
                $value,
                $decimalPlaces,
                $this->decimalPlaces
            )
        );
    }

    public function toFloat(float|int|string $value): string
    {
        $decimalPlaces = $this->baseMathService->powTen($this->decimalPlaces);

        return $this->baseMathService->div($value, $decimalPlaces, $this->decimalPlaces);
    }

    public function add(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->baseMathService->add(
            $this->toInteger($first),
            $this->toInteger($second),
        );
    }

    public function sub(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->baseMathService->sub(
            $this->toInteger($first),
            $this->toInteger($second)
        );
    }

    public function div(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->baseMathService->div(
            $this->toInteger($first),
            $this->toInteger($second),
            $scale ?? $this->decimalPlaces
        );
    }

    public function mul(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->baseMathService->mul(
            $this->toInteger($first),
            $this->toInteger($second)
        );
    }

    public function pow(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->baseMathService->pow(
            $this->toInteger($first),
            $this->toInteger($second),
        );
    }

    public function powTen(float|int|string $number): string
    {
        return $this->baseMathService->powTen($this->toInteger($number));
    }

    public function ceil(float|int|string $number): string
    {
        return $this->baseMathService->ceil($this->toInteger($number));
    }

    public function floor(float|int|string $number): string
    {
        return $this->baseMathService->floor($this->toInteger($number));
    }

    public function round(float|int|string $number, int $precision = 0): string
    {
        return $this->baseMathService->round($this->toInteger($number), $precision);
    }

    public function abs(float|int|string $number): string
    {
        return $this->baseMathService->abs($this->toInteger($number));
    }

    public function negative(float|int|string $number): string
    {
        return $this->baseMathService->negative($this->toInteger($number));
    }

    public function compare(float|int|string $first, float|int|string $second): int
    {
        return $this->baseMathService->compare($this->toInteger($first), $this->toInteger($second));
    }
}
