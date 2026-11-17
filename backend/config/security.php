<?php

/*
|--------------------------------------------------------------------------
| Browser security policy
|--------------------------------------------------------------------------
|
| `report_only` sends a Content-Security-Policy-Report-Only header on the app's pages (not the admin panel) and
| asks browsers to report what the policy would have blocked to `csp-report`, which writes it to the `csp` log. Nothing
| is blocked while it is on. The server template turns it on; local development leaves it off so the Vite dev server
| is not reported on.
|
*/

return [

    'csp' => [

        'report_only' => (bool) env('CSP_REPORT_ONLY', false),

    ],

];
