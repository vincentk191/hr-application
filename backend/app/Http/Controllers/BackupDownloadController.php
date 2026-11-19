<?php

namespace App\Http\Controllers;

use App\Support\Backup\BackupFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends one finished backup to a System Admin as a single file (README, "Download from the admin page").
 *
 * The route asks for the password again, answers only a parent-wide account that may manage backups (the same gate as the
 * page that lists them), and is limited per account. The file is encrypted, but it holds every company's data, so each
 * download is written to the audit trail.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, string $run, BackupFiles $backups): StreamedResponse
    {
        $this->authorize('manage-backup-settings');

        $response = $backups->download($run) ?? abort(404);

        activity('settings')
            ->causedBy($request->user())
            ->withProperties(['run' => $run])
            ->log('Backup download started');

        return $response;
    }
}
