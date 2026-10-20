<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\RegisterEmployeeAccount;
use App\Http\Requests\RegisterAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Daftar": the employee claims the account for the profile HR created (CORE-REG).
 */
class AccountRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(RegisterAccountRequest $request, RegisterEmployeeAccount $register): RedirectResponse
    {
        $register->handle($request->validated(), $request->ip());

        // Not signed in automatically: they sign in the normal way, which also proves the password.
        return to_route('login')->with('status', 'Pendaftaran berhasil. Akun bisa dipakai setelah HR mengaktifkannya.');
    }
}
