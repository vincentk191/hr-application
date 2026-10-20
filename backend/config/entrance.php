<?php

/*
|--------------------------------------------------------------------------
| Entrances (CORE-ACC-001)
|--------------------------------------------------------------------------
|
| The app is reached two ways: a private entrance over the VPN, and a public one limited to employee
| self-service. Nginx says which by setting a header on every request it forwards, overwriting whatever
| the client sent, and the app believes the header only from a proxy listed here. A request with no
| trusted header is treated as `default`, which is "public" so that a missing or broken proxy setup fails
| closed. Local development and the tests set ENTRANCE_DEFAULT=private.
|
*/

return [

    'header' => 'X-Entrance',

    'default' => env('ENTRANCE_DEFAULT', 'public'),

    /*
     * With PHP-FPM, Nginx passes the entrance as a plain FastCGI parameter. That cannot be set by a client (request
     * headers only ever arrive as HTTP_*), so it is believed on its own, with no proxy check. The header above is
     * for set-ups that forward with a header instead.
     */
    'server_variable' => 'ENTRANCE',

    /*
     * The proxies whose X-Entrance header is believed (and whose forwarded address and scheme the app
     * trusts). Addresses or CIDR ranges, comma separated in the environment.
     */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))))),

    // A separate session cookie per entrance, so a session from one is never valid on the other.
    'session_cookies' => [
        'public' => env('SESSION_COOKIE_PUBLIC', 'hr_public_session'),
        'private' => env('SESSION_COOKIE_PRIVATE', 'hr_private_session'),
    ],

];
