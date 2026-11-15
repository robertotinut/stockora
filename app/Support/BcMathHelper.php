<?php

namespace App\Support;

use DomainException;
use InvalidArgumentException;

class BcMathHelper
{
    public static function normalize(string|int $val, int $scale = 10): string
    {
        $val = trim((string) $val);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $val)) {
            throw new InvalidArgumentException("Non-decimal BCMath input: {$val}");
        }

        return bcadd($val, '0', $scale);
    }

    public static function roundHalfUp(string|int $val, int $scale): string
    {
        $val = trim((string) $val);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $val)) {
            throw new InvalidArgumentException("Non-decimal BCMath input: {$val}");
        }
        $negative = str_starts_with($val, '-');
        $abs = $negative ? substr($val, 1) : $val;
        if (!str_contains($abs, '.')) {
            return ($negative ? '-' : '') . bcadd($abs, '0', $scale);
        }
        [$intPart, $decPart] = explode('.', $abs, 2);
        if (strlen($decPart) <= $scale) {
            return ($negative && bccomp($abs, '0', $scale) !== 0 ? '-' : '') . bcadd($abs, '0', $scale);
        }
        $nextDigit = (int) $decPart[$scale];
        $truncated = $intPart . '.' . substr($decPart, 0, $scale);
        if ($nextDigit >= 5) {
            $inc = $scale === 0 ? '1' : ('0.' . str_repeat('0', $scale - 1) . '1');
            $rounded = bcadd($truncated, $inc, $scale);
        } else {
            $rounded = bcadd($truncated, '0', $scale);
        }
        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-' . $rounded;
        }

        return $rounded;
    }

    public static function assertPositive(string $val, string $label, int $scale = 8): void
    {
        if (bccomp($val, '0', $scale) <= 0) {
            throw new DomainException("INVALID_POSITIVE_VALUE: {$label} must be > 0, got {$val}");
        }
    }

    public static function assertNonNegative(string $val, string $label, int $scale = 8): void
    {
        if (bccomp($val, '0', $scale) < 0) {
            throw new DomainException("INVALID_NONNEG_VALUE: {$label} must be >= 0, got {$val}");
        }
    }

    public static function assertFitsDecimalCapacity(string $val, int $precision, int $scale, string $label): void
    {
        $maxIntDigits = $precision - $scale;
        $abs = ltrim(explode('.', trim($val))[0], '-+');
        if ($abs === '') {
            $abs = '0';
        }
        if (strlen($abs) > $maxIntDigits) {
            throw new DomainException("DECIMAL_CAPACITY_EXCEEDED: {$label}={$val} exceeds DECIMAL({$precision},{$scale})");
        }
    }
}
