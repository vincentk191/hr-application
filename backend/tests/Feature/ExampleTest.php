<?php

use App\Models\User;

/**
 * The root is not a landing page: this app is internal and private-only (context.md §5.1), so
 * it just routes people to where they can actually do something.
 */
test('the root sends a guest to sign in', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the root sends a signed-in user to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
