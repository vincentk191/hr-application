<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Notifications\InAppNotice;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Turning Web Push on and off from a browser: the person's own subscriptions only, and only to a real push service.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole(Role::Employee->value);
});

function pushPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyOfTheDevice', 'auth' => 'AuthSecret']];
}

it('stores the browser subscription against the signed-in person', function () {
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), pushPayload())->assertNoContent();

    expect($this->user->pushSubscriptions()->count())->toBe(1)
        ->and($this->user->pushSubscriptions()->first())
        ->endpoint->toBe('https://fcm.googleapis.com/fcm/send/abc123')
        ->public_key->toBe('BPublicKeyOfTheDevice')
        ->auth_token->toBe('AuthSecret');
});

it('updates rather than duplicates when the same browser subscribes again', function () {
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), pushPayload())->assertNoContent();
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), [...pushPayload(), 'keys' => ['p256dh' => 'NewKey', 'auth' => 'NewAuth']])->assertNoContent();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and($this->user->pushSubscriptions()->first()->public_key)->toBe('NewKey');
});

it('moves a shared phone to whoever subscribes on it next', function () {
    $previous = User::factory()->create(['company_id' => $this->company->id]);
    $previous->updatePushSubscription('https://fcm.googleapis.com/fcm/send/shared', 'key', 'auth');

    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), pushPayload('https://fcm.googleapis.com/fcm/send/shared'))->assertNoContent();

    expect($previous->pushSubscriptions()->count())->toBe(0)
        ->and($this->user->pushSubscriptions()->count())->toBe(1);
});

it('refuses an endpoint that is not a known push service', function (string $endpoint) {
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), pushPayload($endpoint))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');

    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'an internal address' => 'https://169.254.169.254/latest/meta-data',
    'a lookalike of a real service' => 'https://fcm.googleapis.com.evil.test/send/abc',
    'a plain http endpoint' => 'http://fcm.googleapis.com/fcm/send/abc',
    'an unrelated site' => 'https://example.com/push',
]);

it('accepts the endpoints of the services browsers use', function (string $endpoint) {
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), pushPayload($endpoint))->assertNoContent();
})->with([
    'Chrome and Edge' => 'https://fcm.googleapis.com/fcm/send/abc',
    'Firefox' => 'https://updates.push.services.mozilla.com/wpush/v2/abc',
    'Safari and iPhone' => 'https://web.push.apple.com/abc',
    'Windows' => 'https://wns2-par02p.notify.windows.com/w/?token=abc',
]);

it('needs both keys', function () {
    $this->actingAs($this->user)->postJson(route('push-subscriptions.store'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'x']])
        ->assertJsonValidationErrors('keys.auth');
});

it('turns push off for this browser only, and never touches another account', function () {
    $other = User::factory()->create(['company_id' => $this->company->id]);
    $other->updatePushSubscription('https://fcm.googleapis.com/fcm/send/theirs', 'key', 'auth');
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/laptop', 'key', 'auth');

    $this->actingAs($this->user)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/theirs'])->assertNoContent();
    $this->actingAs($this->user)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/mine'])->assertNoContent();

    expect($other->pushSubscriptions()->count())->toBe(1)
        ->and($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/laptop']);
});

it('turns the routes away for someone not signed in', function () {
    $this->postJson(route('push-subscriptions.store'), pushPayload())->assertUnauthorized();
    $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertUnauthorized();
    $this->postJson(route('push-subscriptions.test'))->assertUnauthorized();
});

it('sends a test notice only to someone who has subscribed', function () {
    Notification::fake();

    $this->actingAs($this->user)->postJson(route('push-subscriptions.test'))->assertUnprocessable();
    Notification::assertNothingSent();

    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');
    $this->actingAs($this->user)->postJson(route('push-subscriptions.test'))->assertOk();

    Notification::assertSentTo($this->user, InAppNotice::class, fn (InAppNotice $notice): bool => $notice->kind === 'system');
});

it('drops the subscriptions of an account that is deactivated', function () {
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');

    $this->user->forceFill(['is_active' => false])->save();

    expect(PushSubscription::query()->count())->toBe(0);
});

it('tells the notification settings page which devices are subscribed, as the signed-in person only', function () {
    $other = User::factory()->create(['company_id' => $this->company->id]);
    $other->updatePushSubscription('https://fcm.googleapis.com/fcm/send/theirs', 'key', 'auth');
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');

    $this->actingAs($this->user)->get(route('notification-settings.edit'))
        ->assertInertia(fn ($page) => $page->component('settings/notifications')->where('pushEndpoints', ['https://fcm.googleapis.com/fcm/send/mine']));
});

it('hands the public key to the page only when push is set up', function () {
    config(['webpush.vapid.public_key' => 'PUBLICKEY', 'webpush.vapid.private_key' => 'PRIVATEKEY']);
    $this->actingAs($this->user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('push.publicKey', 'PUBLICKEY'));

    config(['webpush.vapid.private_key' => null]);
    $this->actingAs($this->user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('push.publicKey', null));
});
