<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

it('tells the header which role the signed-in person has, in its Indonesian name', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::HrAdmin->value);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.roleLabel', 'Admin HR'));
});
