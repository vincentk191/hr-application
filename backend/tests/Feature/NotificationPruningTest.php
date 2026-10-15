<?php

use App\Models\User;
use App\Notifications\InAppNotice;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Seen notifications clear themselves a day after they were read (CORE-NOT-010).
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->user = User::factory()->create();
});

function noticeFor(User $user, string $title, ?string $key = null): DatabaseNotification
{
    $user->notify(new InAppNotice('leave', $title, 'isi', '/dashboard', $key));

    return $user->notifications()->where('data->title', $title)->firstOrFail();
}

it('deletes a notification once it has been read for more than a day', function () {
    $old = noticeFor($this->user, 'lama');
    $old->forceFill(['read_at' => now()->subHours(25)])->save();

    $this->artisan('notifications:prune-read')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(0);
});

it('keeps one read less than a day ago, and unread ones however old', function () {
    $recent = noticeFor($this->user, 'baru dibaca');
    $recent->forceFill(['read_at' => now()->subHours(23)])->save();

    $unread = noticeFor($this->user, 'belum dibaca');
    $unread->forceFill(['created_at' => now()->subMonths(3)])->save();

    $this->artisan('notifications:prune-read')->assertSuccessful();

    expect(DatabaseNotification::pluck('id')->all())->toEqualCanonicalizing([$recent->id, $unread->id]);
});

it('counts the day from when it was read, not from when it arrived', function () {
    $notice = noticeFor($this->user, 'lama masuk');
    $notice->forceFill(['created_at' => now()->subDays(10), 'read_at' => now()->subHour()])->save();

    $this->artisan('notifications:prune-read')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(1);
});

it('can be told a different number of hours', function () {
    noticeFor($this->user, 'satu')->forceFill(['read_at' => now()->subHours(3)])->save();

    $this->artisan('notifications:prune-read', ['--hours' => 2])->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(0);
});

it('does not send something again after its notification was cleared', function () {
    $notifier = app(Notifier::class);
    $notice = fn (): InAppNotice => new InAppNotice('document', 'Dokumen akan habis', 'isi', '/dashboard', 'doc:7:soon');

    $notifier->once($this->user, $notice());
    $notifier->once($this->user, $notice());

    expect($this->user->notifications()->count())->toBe(1);

    $this->user->notifications()->firstOrFail()->markAsRead();
    $this->travel(2)->days();
    $this->artisan('notifications:prune-read')->assertSuccessful();

    expect($this->user->notifications()->count())->toBe(0);

    $notifier->once($this->user, $notice());

    expect($this->user->notifications()->count())->toBe(0);
});

it('still sends the same key to a different person, and a different key to the same one', function () {
    $other = User::factory()->create();
    $notifier = app(Notifier::class);

    $notifier->once($this->user, new InAppNotice('document', 't', 'b', '/x', 'doc:7:soon'));
    $notifier->once($other, new InAppNotice('document', 't', 'b', '/x', 'doc:7:soon'));
    $notifier->once($this->user, new InAppNotice('document', 't', 'b', '/x', 'doc:7:expired'));

    expect($this->user->notifications()->count())->toBe(2)->and($other->notifications()->count())->toBe(1);
});

it('runs every hour', function () {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'notifications:prune-read'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
});
