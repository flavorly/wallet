<?php

declare(strict_types=1);

namespace Flavorly\Wallet;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;

/**
 * A simple wrapper around Brick\Math that ensures a better interface
 * But also providers a correct conversion from float to integer
 * in order to be saved in the database without any issues
 * Please see Brick\Money for more information about the concept
 * on why we use Integers instead of floats
 */
final readonly class Math
{
    public function __construct(
        protected int $floatScale,
        protected int $integerScale = 20,
    ) {
    }

    /**
     * Converts a float into a integer based on the given scale
     */
    public function floatToInt(float|int|string $value): string
    {
        $decimalPlaces = $this->powTen($this->floatScale);

        return $this->round(
            $this->mul(
                $value,
                $decimalPlaces,
                $this->floatScale
            )
        );
    }

    /**
     * Converts a big integer into a float based on the given scale
     */
    public function intToFloat(float|int|string $value): string
    {
        $decimalPlaces = $this->powTen($this->floatScale);

        return $this->div($value, $decimalPlaces, $this->floatScale);
    }

    public function addInteger(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->add(
            $this->floatToInt($first),
            $this->floatToInt($second),
        );
    }

    public function subInteger(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->sub(
            $this->floatToInt($first),
            $this->floatToInt($second)
        );
    }

    public function divInteger(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->div(
            $this->floatToInt($first),
            $this->floatToInt($second),
            $scale ?? $this->floatScale
        );
    }

    public function mulInteger(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->mul(
            $this->floatToInt($first),
            $this->floatToInt($second)
        );
    }

    public function powInteger(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return $this->pow(
            $this->floatToInt($first),
            $this->floatToInt($second),
        );
    }

    public function powTenInteger(float|int|string $number): string
    {
        return $this->powTen($this->floatToInt($number));
    }

    public function ceilInteger(float|int|string $number): string
    {
        return $this->ceil($this->floatToInt($number));
    }

    public function floorInteger(float|int|string $number): string
    {
        return $this->floor($this->floatToInt($number));
    }

    public function roundInteger(float|int|string $number, int $precision = 0): string
    {
        return $this->round($this->floatToInt($number), $precision);
    }

    public function absInteger(float|int|string $number): string
    {
        return $this->abs($this->floatToInt($number));
    }

    public function negativeInteger(float|int|string $number): string
    {
        return $this->negative($this->floatToInt($number));
    }

    public function compareInteger(float|int|string $first, float|int|string $second): int
    {
        return $this->compare($this->floatToInt($first), $this->floatToInt($second));
    }

    public function ensureScale(float|int|string $number): string
    {
        return $this->mul($number, 1);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function add(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return (string) BigDecimal::of($first)
            ->plus(BigDecimal::of($second))
            ->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function sub(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return (string) BigDecimal::of($first)
            ->minus(BigDecimal::of($second))
            ->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     */
    public function div(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return (string) BigDecimal::of($first)
            ->dividedBy(BigDecimal::of($second), $scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function mul(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return (string) BigDecimal::of($first)
            ->multipliedBy(BigDecimal::of($second))
            ->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function pow(float|int|string $first, float|int|string $second, ?int $scale = null): string
    {
        return (string) BigDecimal::of($first)
            ->power((int) $second)
            ->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function powTen(float|int|string $number): string
    {
        return $this->pow(10, $number);
    }

    /**
     * @throws MathException
     */
    public function ceil(float|int|string $number): string
    {
        return (string) BigDecimal::of($number)
            ->dividedBy(BigDecimal::one(), 0, RoundingMode::CEILING);
    }

    /**
     * @throws MathException
     */
    public function floor(float|int|string $number): string
    {
        return (string) BigDecimal::of($number)
            ->dividedBy(BigDecimal::one(), 0, RoundingMode::FLOOR);
    }

    /**
     * @throws MathException
     */
    public function round(float|int|string $number, int $precision = 0): string
    {
        return (string) BigDecimal::of($number)
            ->dividedBy(BigDecimal::one(), $precision, RoundingMode::HALF_UP);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function abs(float|int|string $number, ?int $scale = null): string
    {
        return (string) BigDecimal::of($number)->abs()->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
    }

    /**
     * @throws MathException
     * @throws RoundingNecessaryException
     */
    public function negative(float|int|string $number, ?int $scale = null): string
    {
        $number = BigDecimal::of($number);
        if ($number->isNegative()) {
            return (string) $number->toScale($scale ?? $this->floatScale, RoundingMode::DOWN);
        }

        return (string) BigDecimal::of($number)
            ->toScale($scale ?? $this->floatScale, RoundingMode::DOWN)
            ->negated();
    }

    /**
     * @throws MathException
     */
    public function compare(float|int|string $first, float|int|string $second): int
    {
        return BigDecimal::of($first)->compareTo(BigDecimal::of($second));
    }
}
