<?php

use App\Models\User;

test('the version comes from the VERSION file', function () {
    expect(config('app.version'))->toBe(trim(file_get_contents(base_path('VERSION'))))
        ->and(config('app.version'))->toMatch('/^\d+\.\d+\.\d+$/');
});

test('the version is shared with the pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('version', config('app.version')));
});
