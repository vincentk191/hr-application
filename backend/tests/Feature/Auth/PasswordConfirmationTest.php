<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/confirm-password'),
    );
});

test('an Inertia visit is sent to the address it was heading for with a location response, not a redirect', function () {
    // The admin panel's pages are plain HTML: a redirect would open them inside a sandboxed frame.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/admin/manage-backups')])
        ->post(route('password.confirm.store'), ['password' => 'password'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/admin/manage-backups'));
});

test('an ordinary request is redirected to the address it was heading for', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/admin/manage-backups')])
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(url('/admin/manage-backups'));
});

test('a wrong password confirms nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/admin/manage-backups')])
        ->post(route('password.confirm.store'), ['password' => 'wrong'], ['X-Inertia' => 'true'])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});
