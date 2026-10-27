<?php

use App\Http\Middleware\DiscardRememberMe;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * How long one sign-in lasts: a long idle timeout (config only), an absolute cap however busy the session is,
 * and no "remember me", which would sign someone back in without a password or a two-factor code.
 */
it('ends a session that is older than the absolute lifetime even though it is in use', function () {
    config(['session.absolute_lifetime' => 24 * 60]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->travel(25)->hours();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps a session that is still inside the absolute lifetime', function () {
    config(['session.absolute_lifetime' => 24 * 60]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->travel(23)->hours();

    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('does not cap the session when the absolute lifetime is 0', function () {
    config(['session.absolute_lifetime' => 0]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->travel(72)->hours();

    $this->get(route('dashboard'))->assertOk();
});

it('tells the person why they were signed out', function () {
    config(['session.absolute_lifetime' => 60]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    $this->travel(2)->hours();

    $this->followingRedirects()->get(route('dashboard'))->assertSee('Sesi Anda berakhir');
});

it('marks the session cookie Secure on the public entrance but leaves the private one to the request', function (string $entrance, bool $secure) {
    $response = $this->withHeaders(['X-Entrance' => $entrance])->get(route('login'));

    $cookie = collect($response->baseResponse->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('entrance.session_cookies.'.$entrance));

    expect($cookie->isSecure())->toBe($secure);
})->with([
    'public' => ['public', true],
    'private' => ['private', false],
]);

it('ignores remember me when signing in', function () {
    $user = User::factory()->create();
    $recaller = Auth::guard('web')->getRecallerName();
    $credentials = ['email' => $user->email, 'password' => 'password', 'remember' => '1'];

    $this->post(route('login.store'), $credentials)->assertCookieMissing($recaller);
    Auth::guard('web')->logout();

    // The control: without the middleware Fortify does queue the cookie, so the assertion above can fail.
    $this->withoutMiddleware(DiscardRememberMe::class)->post(route('login.store'), $credentials)->assertCookie($recaller);
});
