<?php

/*
|--------------------------------------------------------------------------
| Modules that can be switched off
|--------------------------------------------------------------------------
|
| A release can ship without a module that is built but not ready (attendance: the scanner is not
| connected yet). Switching one off removes it from the menus, the profile and the dashboard, answers
| 404 on its routes and its ingest API, and stops its scheduled jobs. Nothing is deleted: switching it
| back on restores it with its data. On by default, so development and the tests see everything.
|
| Attendance off keeps the calendar page and its holidays, because leave day counts need them.
|
*/

return [

    'attendance' => filter_var(env('FEATURE_ATTENDANCE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | The on-site sync agents that pushed scanner punches automatically: their ingest API, the agent
    | screens in the admin panel, "Sinkronkan sekarang" and the agent warnings. Off by default since
    | October 2026, when capture became a manual import of the scanner export at head office and a
    | per-period rekap from the other Cabang. Kept rather than deleted so it can come back; it only
    | ever works with attendance itself on.
    */

    'attendance_agents' => filter_var(env('FEATURE_ATTENDANCE_AGENTS', false), FILTER_VALIDATE_BOOLEAN),

];
