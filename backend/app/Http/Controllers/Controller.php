<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Lets controllers call $this->authorize(), so policy checks sit at the top of the action
     * rather than being scattered through it.
     */
    use AuthorizesRequests;
}
