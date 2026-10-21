<?php

/*
|--------------------------------------------------------------------------
| The first System Admin (CORE-USR-007)
|--------------------------------------------------------------------------
|
| A new server has no accounts. SystemAdminSeeder creates one parent-wide System Admin from these
| values: no company, so it can create the companies and then the first HR Admin of each from the
| admin panel. The password is only a temporary one; the account must change it at its first sign-in.
| Nothing here has a default, and the seeder creates nothing until the email and a password of at
| least 12 characters are set. Remove INITIAL_ADMIN_PASSWORD from the environment once the account exists.
|
*/

return [

    'name' => env('INITIAL_ADMIN_NAME', 'Admin Sistem'),

    'email' => env('INITIAL_ADMIN_EMAIL'),

    'password' => env('INITIAL_ADMIN_PASSWORD'),

];
