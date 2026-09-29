<?php

namespace App\Support;

use OverflowException;

final class Money
{
    public static function toMinor(string|int $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '0');
        $fraction = substr($fraction, 0, 2);

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function multiplyMinor(int $unitAmount, int $quantity): int
    {
        if ($unitAmount < 0 || $quantity < 0 || ($unitAmount > 0 && $quantity > intdiv(PHP_INT_MAX, $unitAmount))) {
            throw new OverflowException('The monetary amount exceeds the supported range.');
        }

        return $unitAmount * $quantity;
    }

    public static function addMinor(int $total, int $amount): int
    {
        if ($total < 0 || $amount < 0 || $amount > PHP_INT_MAX - $total) {
            throw new OverflowException('The monetary amount exceeds the supported range.');
        }

        return $total + $amount;
    }

    public static function fromMinor(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function formatMinor(int $amount): string
    {
        return number_format(intdiv($amount, 100), 0, '.', ',')
            .'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function formatDisplayMinor(int $amount): string
    {
        $formatted = self::formatMinor($amount);

        return $amount % 100 === 0 ? substr($formatted, 0, -3) : $formatted;
    }
}
