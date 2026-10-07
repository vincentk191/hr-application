<?php

namespace App\Http\Responses;

use App\Concerns\IgnoresAdminPanelRedirect;
use Laravel\Fortify\Http\Responses\TwoFactorLoginResponse as FortifyTwoFactorLoginResponse;

class TwoFactorLoginResponse extends FortifyTwoFactorLoginResponse
{
    use IgnoresAdminPanelRedirect;
}
