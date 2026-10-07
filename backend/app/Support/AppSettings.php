<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Settings stored in the database, with config as the fallback.
 *
 * Read on every call rather than cached: the table is tiny, and a cache would mean a changed
 * setting could keep applying the old value in a long-running worker.
 */
class AppSettings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = DB::table('app_settings')->where('key', $key)->value('value');

        return $row === null ? $default : json_decode((string) $row, true);
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('app_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
    }
}
