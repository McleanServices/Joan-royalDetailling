<?php

namespace App\Support;

class Euro
{
    public static function format(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' €';
    }

    public static function fromStored(mixed $minorUnits): string
    {
        return self::format(((int) $minorUnits) / 100);
    }

    public static function toDecimal(mixed $minorUnits): float
    {
        return ((int) $minorUnits) / 100;
    }
}
