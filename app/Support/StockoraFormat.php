<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class StockoraFormat
{
    public static function locale(?string $locale = null): string
    {
        return StockoraLocale::normalize($locale) ?? StockoraLocale::current();
    }

    public static function number(float|int|string|null $value, int $decimals = 0, ?string $locale = null): string
    {
        $num = is_numeric((string) $value) ? (float) $value : 0.0;
        $loc = self::locale($locale);

        if ($loc === 'id') {
            return number_format($num, $decimals, ',', '.');
        }

        return number_format($num, $decimals, '.', ',');
    }

    public static function quantity(float|int|string|null $value, int $decimals = 2, ?string $locale = null): string
    {
        return self::number($value, $decimals, $locale);
    }

    public static function currency(float|int|string|null $value, int $decimals = 0, ?string $locale = null): string
    {
        return 'Rp ' . self::number($value, $decimals, $locale);
    }

    public static function date(CarbonInterface|string|null $date, ?string $locale = null, string $fallback = '—'): string
    {
        if ($date === null || $date === '') {
            return $fallback;
        }

        $loc = self::locale($locale);
        $carbon = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);
        $carbon->locale($loc);

        return $loc === 'id'
            ? $carbon->translatedFormat('d F Y')
            : $carbon->translatedFormat('M d, Y');
    }

    public static function shortDate(CarbonInterface|string|null $date, ?string $locale = null, string $fallback = '—'): string
    {
        if ($date === null || $date === '') {
            return $fallback;
        }

        $loc = self::locale($locale);
        $carbon = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);
        $carbon->locale($loc);

        return $loc === 'id'
            ? $carbon->translatedFormat('d M Y')
            : $carbon->translatedFormat('M d, Y');
    }

    public static function dateTime(CarbonInterface|string|null $date, ?string $locale = null, string $fallback = '—'): string
    {
        if ($date === null || $date === '') {
            return $fallback;
        }

        $loc = self::locale($locale);
        $carbon = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);
        $carbon->locale($loc);

        return $loc === 'id'
            ? $carbon->translatedFormat('d M Y, H:i')
            : $carbon->translatedFormat('M d, Y H:i');
    }
}
