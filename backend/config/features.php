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

];
