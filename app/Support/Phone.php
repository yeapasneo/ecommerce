<?php

namespace App\Support;

/** Bangladeshi mobile numbers. */
class Phone
{
    /** "+880 1712-345678" → "01712345678" (other formats are returned as digits). */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', self::normalize($phone));
    }

    /** International format used by SMS gateways: 8801XXXXXXXXX. */
    public static function international(?string $phone): string
    {
        return '88'.self::normalize($phone);
    }

    /** Last 10 digits, used to match numbers stored in any format. */
    public static function key(?string $phone): string
    {
        return substr(self::normalize($phone), -10);
    }
}
