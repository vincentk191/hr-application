<?php

namespace App\Http\Responses;

use App\Concerns\IgnoresAdminPanelRedirect;
use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;

class LoginResponse extends FortifyLoginResponse
{
    use IgnoresAdminPanelRedirect;
}
