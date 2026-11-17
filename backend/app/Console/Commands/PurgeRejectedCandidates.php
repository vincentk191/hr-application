<?php

namespace App\Console\Commands;

use App\Actions\Recruitment\PurgeRejectedCandidate;
use App\Models\Candidate;
use App\Support\Recruitment\CandidateRetention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes the records of candidates rejected longer ago than the retention period set in the admin panel (default twelve
 * months). Safe to run again: what is gone stays gone.
 */
#[Signature('recruitment:purge-rejected-candidates {--dry-run : Only say how many would be deleted}')]
#[Description('Delete the records of rejected candidates after the retention period')]
class PurgeRejectedCandidates extends Command
{
    public function handle(CandidateRetention $retention, PurgeRejectedCandidate $purge): int
    {
        $cutoff = $retention->cutoff();

        $expired = Candidate::acrossCompanies()
            ->whereNotNull('rejected_at')
            ->where('rejected_at', '<', $cutoff)
            ->get();

        if ($this->option('dry-run')) {
            $this->info("{$expired->count()} candidate(s) rejected before {$cutoff->toDateString()} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $expired->filter(fn (Candidate $candidate): bool => $purge->handle($candidate))->count();

        if ($deleted > 0) {
            activity('recruitment')
                ->withProperties(['deleted' => $deleted, 'rejected_before' => $cutoff->toDateString(), 'months' => $retention->months()])
                ->log('Rejected candidates deleted after the retention period');
        }

        $this->info("{$deleted} rejected candidate(s) older than {$retention->months()} month(s) deleted.");

        return self::SUCCESS;
    }
}
