<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Support\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * Tell the people who look after documents about ones that are about to lapse or already have
 * (EMP-DOC). Each document is announced once when it enters the warning window and once when it
 * expires, not every morning.
 */
class NotifyDocumentExpiry extends Command
{
    protected $signature = 'notifications:document-expiry';

    protected $description = 'Notify document managers about expiring and expired documents';

    public function handle(Notifier $notifier): int
    {
        $count = 0;

        EmployeeDocument::acrossCompanies()->needingAttention()->with('employee')->each(function (EmployeeDocument $document) use ($notifier, &$count): void {
            $status = $document->expiryStatus();

            if ($status !== null) {
                $notifier->documentExpiry($document, $status);
                $count++;
            }
        });

        $this->info("{$count} document(s) checked.");

        return self::SUCCESS;
    }
}
