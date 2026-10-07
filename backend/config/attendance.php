<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scanner timezone
    |--------------------------------------------------------------------------
    |
    | The Fingerspot scanner and Personnel record punches in local wall-clock time with no
    | timezone attached. The application stores everything in UTC, so scan times read from a
    | file (or sent by the sync agent) are interpreted in this zone and converted. Getting this
    | wrong shifts every punch by the difference — a 07:55 arrival would land at 00:55.
    |
    */

    'device_timezone' => env('ATTENDANCE_DEVICE_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Longest scan log accepted in one upload, in kilobytes
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => 20480,

    /*
    |--------------------------------------------------------------------------
    | Working schedule
    |--------------------------------------------------------------------------
    |
    | The defaults the attendance calendar judges punches against, used until an administrator
    | saves their own under Jam kerja in the admin panel (which then take precedence). The
    | requirements do not define working hours, so these are assumptions.
    |
    | A scan after start + late_grace_minutes is late; a last scan before end, on a day with more
    | than one scan, is an early departure. Saturday and Sunday are always non-working and are not
    | configurable.
    |
    */

    'schedule' => [
        'start' => env('ATTENDANCE_WORK_START', '08:00'),
        'end' => env('ATTENDANCE_WORK_END', '17:00'),
        'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 10),
    ],

];
