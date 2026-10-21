<?php

namespace App\Support;

/**
 * Which modules are switched on for this release (config/features.php).
 */
class Features
{
    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    public static function attendance(): bool
    {
        return self::enabled('attendance');
    }
}
