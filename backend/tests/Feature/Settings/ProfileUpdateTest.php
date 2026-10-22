<?php

use App\Models\User;

/**
 * A person's name and email are not theirs to edit, and they cannot delete their own account: the account's name comes
 * from the employee record HR keeps, an email is never used to sign in, and an account is closed by offboarding.
 * What is left under Pengaturan is the password and the appearance.
 */
test('settings opens on the appearance page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings')->assertRedirect('/settings/appearance');
    $this->actingAs($user)->get(route('appearance.edit'))->assertOk();
});

test('there is no page to edit the name or email, and none to delete the account', function () {
    $user = User::factory()->create(['name' => 'Nama Asli', 'email' => 'asli@example.com']);

    $this->actingAs($user)->get('/settings/profile')->assertNotFound();
    $this->actingAs($user)->patch('/settings/profile', ['name' => 'Nama Baru', 'email' => 'baru@example.com'])->assertStatus(404);
    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(404);

    $user->refresh();

    expect($user->name)->toBe('Nama Asli')
        ->and($user->email)->toBe('asli@example.com');
});

test('the password can still be changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Sandi-Baru-12345',
            'password_confirmation' => 'Sandi-Baru-12345',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('Sandi-Baru-12345', $user->refresh()->password))->toBeTrue();
});
