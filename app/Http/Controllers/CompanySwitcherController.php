<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetActiveCompany;
use App\Http\Requests\UpdateActiveCompanyRequest;
use Illuminate\Http\RedirectResponse;

class CompanySwitcherController extends Controller
{
    /**
     * Change which company the signed-in user is looking at (CORE-NAV-001/003).
     *
     * Only the selection is stored; the active company is resolved from it on every
     * subsequent request by SetActiveCompany, so a selection cannot outlive the user's right
     * to it. Authorisation and validation both live in the Form Request.
     */
    public function update(UpdateActiveCompanyRequest $request): RedirectResponse
    {
        $request->session()->put(
            SetActiveCompany::SESSION_KEY,
            $request->selection(),
        );

        return back();
    }
}
