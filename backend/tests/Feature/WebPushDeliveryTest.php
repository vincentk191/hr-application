<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Notifications\InAppNotice;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * What goes out to a phone when a notice is sent: only to someone subscribed, only where the keys are set, and
 * the in-app copy is written regardless.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['webpush.vapid.public_key' => 'PUBLICKEY', 'webpush.vapid.private_key' => 'PRIVATEKEY']);

    $this->user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $this->user->assignRole(Role::Employee->value);
});

function notice(): InAppNotice
{
    return new InAppNotice('leave', 'CUT-0001 disetujui', 'Cuti tahunan · 2 hari kerja', '/leave-requests?open=1');
}

it('always writes the in-app copy, and pushes nothing to someone who has not subscribed', function () {
    $channel = $this->mock(WebPushChannel::class);
    $channel->shouldNotReceive('send');

    $this->user->notify(notice());

    expect($this->user->notifications()->count())->toBe(1);
});

it('pushes to a subscribed person as well as writing the in-app copy', function () {
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');

    $channel = $this->mock(WebPushChannel::class);
    $channel->shouldReceive('send')->once()->withArgs(fn (User $to, InAppNotice $sent): bool => $to->is($this->user));

    $this->user->notify(notice());

    expect($this->user->notifications()->count())->toBe(1);
});

it('does not push when the keys are not set, even for a subscribed person', function () {
    config(['webpush.vapid.private_key' => null]);
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');

    $channel = $this->mock(WebPushChannel::class);
    $channel->shouldNotReceive('send');

    $this->user->notify(notice());

    expect($this->user->notifications()->count())->toBe(1);
});

it('shows the notice and opens it through the same path as the bell', function () {
    $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/mine', 'key', 'auth');
    $this->mock(WebPushChannel::class)->shouldReceive('send')->once();

    $this->user->notify(notice());
    $stored = $this->user->notifications()->first();
    assert($stored instanceof DatabaseNotification);

    $sent = notice();
    $sent->id = $stored->id;
    $message = $sent->toWebPush($this->user, $sent)->toArray();

    expect($message)
        ->title->toBe('CUT-0001 disetujui')
        ->body->toBe('Cuti tahunan · 2 hari kerja')
        ->and($message['data']['url'])->toBe(route('notifications.open', $stored->id, absolute: false));

    // Tapping it opens the notice (marking it read) and lands on the page it is about.
    $this->actingAs($this->user)->get($message['data']['url'])->assertRedirect('/leave-requests?open=1');
    expect($stored->fresh()->read_at)->not->toBeNull();
});
