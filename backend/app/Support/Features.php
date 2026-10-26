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

    /**
     * The automatic sync agents. Nothing they do makes sense with attendance off, so that wins.
     */
    public static function attendanceAgents(): bool
    {
        return self::attendance() && self::enabled('attendance_agents');
    }
}
