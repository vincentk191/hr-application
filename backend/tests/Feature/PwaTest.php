<?php

/**
 * The installable app (the PWA's feasibility build): a manifest the browser accepts, icons that exist, a service
 * worker that keeps no personal data, and an offline page. What a phone does with them is tried by hand.
 */
it('serves the page shell with the manifest and colours that make it installable', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('apple-mobile-web-app-capable', false);
});

it('has a valid manifest with icons that exist', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and($manifest['scope'])->toBe('/')
        ->and($manifest['lang'])->toBe('id');

    $sizes = collect($manifest['icons'])->pluck('sizes')->all();
    expect($sizes)->toContain('192x192')->toContain('512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }
});

it('has a service worker that never keeps a page or a response with data in it', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    // Pages are network-only (with the offline page as the fallback), and only the built assets are cached.
    expect($worker)->toContain("request.mode === 'navigate'")
        ->and($worker)->toContain('/build/')
        ->and($worker)->toContain("request.method !== 'GET'")
        ->and($worker)->not->toContain('cache.put(request.url, response')
        ->and(file_exists(public_path('offline.html')))->toBeTrue()
        ->and((string) file_get_contents(public_path('offline.html')))->toContain('Tidak ada koneksi');
});
