<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Clears notifications that were seen (CORE-NOT-010): a read notification is deleted once it has been
 * read for a day. Unread ones are never touched, however old.
 *
 * What the app has already told someone once lives in `notification_keys`, not here, so deleting a
 * notification never makes a job send it again.
 */
#[Signature('notifications:prune-read {--hours=24 : How long a notification stays after it was read}')]
#[Description('Delete notifications that were read more than a day ago')]
class PruneReadNotifications extends Command
{
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("{$deleted} read notification(s) older than {$hours} hour(s) deleted.");

        return self::SUCCESS;
    }
}
