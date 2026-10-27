<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\PasswordConfirmedResponse as FortifyPasswordConfirmedResponse;

/**
 * Where the password confirmation sends you next.
 *
 * The confirmation form is an Inertia visit, and an Inertia visit can only follow a redirect to another Inertia page.
 * The admin panel's pages are plain HTML (the backup settings ask for a confirmed password), and Inertia answers that by
 * showing the page inside a sandboxed frame, where its menus and links do nothing. A location response has the browser
 * load the address itself, which works for both kinds of page.
 */
class PasswordConfirmedResponse extends FortifyPasswordConfirmedResponse
{
    public function toResponse($request)
    {
        $response = parent::toResponse($request);

        if ($response instanceof RedirectResponse && $request->header('X-Inertia')) {
            return Inertia::location($response->getTargetUrl());
        }

        return $response;
    }
}
