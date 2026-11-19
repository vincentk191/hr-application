<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The finished backups on the server, as the `app` container sees them through a read-only mount, and a way to send one
 * as a single download (README, "Download from the admin page").
 *
 * Everything here only reads. The files are encrypted to a key the server never holds, so a download is no more
 * revealing than the folder it came from; what protects it is who may ask (the controller) and that the application can
 * neither change nor delete a backup (the mount).
 *
 * A backup is one folder named for the moment it began, holding `*.age` files, `SHA256SUMS` and `MANIFEST.txt`, which
 * `backup.sh` writes last. A folder without the manifest is a run still in progress and is not offered.
 */
class BackupFiles
{
    public const READY = 'ready';

    /** Nothing is mounted at the directory: the server has not been set up for downloads. */
    public const NOT_MOUNTED = 'not-mounted';

    /** Mounted, but the web user may not read it: `BACKUP_READER_GID` is not set on the server. */
    public const NOT_READABLE = 'not-readable';

    /** A backup folder is named `YYYY-MM-DD_HHMMSS`. */
    public const RUN_PATTERN = '/^20\d{2}-\d{2}-\d{2}_\d{6}$/';

    /** The only files that belong to a backup; anything else in the folder is neither listed nor sent. */
    private const FILE_PATTERN = '/^(?:[A-Za-z0-9][A-Za-z0-9._-]*\.age|SHA256SUMS|MANIFEST\.txt)$/';

    private const BLOCK = 512;

    private const CHUNK = 1048576;

    public function directory(): string
    {
        return rtrim((string) config('backup.directory'), '/');
    }

    /**
     * @return 'ready'|'not-mounted'|'not-readable'
     */
    public function state(): string
    {
        $directory = $this->directory();

        if ($directory === '' || ! is_dir($directory)) {
            return self::NOT_MOUNTED;
        }

        return is_readable($directory) && is_executable($directory) ? self::READY : self::NOT_READABLE;
    }

    /**
     * The finished backups, newest first.
     *
     * @return list<array{name: string, startedAt: CarbonImmutable, bytes: int, files: array<string, int>}>
     */
    public function runs(): array
    {
        if ($this->state() !== self::READY) {
            return [];
        }

        $runs = [];

        foreach (scandir($this->directory()) ?: [] as $entry) {
            $files = preg_match(self::RUN_PATTERN, $entry) === 1 ? $this->filesOf($entry) : null;

            if ($files === null) {
                continue;
            }

            $startedAt = CarbonImmutable::createFromFormat('Y-m-d_His', $entry);

            if (! $startedAt instanceof CarbonImmutable) {
                continue;
            }

            $runs[] = ['name' => $entry, 'startedAt' => $startedAt, 'bytes' => array_sum($files), 'files' => $files];
        }

        usort($runs, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $runs;
    }

    /**
     * One backup as a single `.tar` (a plain tar, no compression: the files are already encrypted and compressed), sent
     * while it is read so nothing is copied to disk and the length is known up front. Null when there is no such
     * finished backup.
     */
    public function download(string $run): ?StreamedResponse
    {
        $files = preg_match(self::RUN_PATTERN, $run) === 1 ? $this->filesOf($run) : null;

        if ($files === null) {
            return null;
        }

        // Opened now, before anything is sent: a backup pruned while it is being sent is still read to the end, and one
        // that is already gone is a plain 404 instead of a broken download.
        $handles = [];

        foreach (array_keys($files) as $file) {
            $handle = @fopen($this->directory().'/'.$run.'/'.$file, 'rb');

            if ($handle === false) {
                foreach ($handles as $open) {
                    fclose($open);
                }

                return null;
            }

            $handles[$file] = $handle;
        }

        $length = self::BLOCK; // the folder's own entry
        foreach ($files as $size) {
            $length += self::BLOCK + $this->padded($size);
        }
        $length += 2 * self::BLOCK; // the end of the archive

        $response = new StreamedResponse(function () use ($run, $files, $handles): void {
            // A long download on a slow line outlasts the 60 seconds a page request is allowed.
            set_time_limit(0);

            $this->send($run, $files, $handles);
        }, 200, [
            'Content-Type' => 'application/x-tar',
            'Content-Length' => (string) $length,
            'Cache-Control' => 'no-store, private',
        ]);

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            "hr-cadangan-{$run}.tar",
        ));

        return $response;
    }

    /**
     * The files of a finished backup with their sizes, or null when the folder is missing, is a link, or has no
     * manifest yet.
     *
     * @return array<string, int>|null
     */
    private function filesOf(string $run): ?array
    {
        $path = $this->directory().'/'.$run;

        if (is_link($path) || ! is_dir($path)) {
            return null;
        }

        $files = [];

        foreach (scandir($path) ?: [] as $entry) {
            $file = $path.'/'.$entry;

            if (preg_match(self::FILE_PATTERN, $entry) === 1 && is_file($file) && ! is_link($file)) {
                $files[$entry] = (int) filesize($file);
            }
        }

        if (! isset($files['MANIFEST.txt'], $files['SHA256SUMS']) || count($files) < 3) {
            return null;
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, int>  $files
     * @param  array<string, resource>  $handles
     */
    private function send(string $run, array $files, array $handles): void
    {
        echo $this->header($run.'/', 0, '5', 0700);

        foreach ($files as $file => $size) {
            echo $this->header($run.'/'.$file, $size, '0', 0600);

            $remaining = $size;

            while ($remaining > 0) {
                $chunk = fread($handles[$file], min(self::CHUNK, $remaining));

                if ($chunk === false || $chunk === '') {
                    // A file that is shorter than it was measured can only be a damaged one. Stopping here leaves the
                    // download short of its announced length, so the browser reports it as failed instead of saving it.
                    return;
                }

                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }

            fclose($handles[$file]);
            echo str_repeat("\0", $this->padded($size) - $size);
        }

        echo str_repeat("\0", 2 * self::BLOCK);
    }

    private function padded(int $size): int
    {
        return intdiv($size + self::BLOCK - 1, self::BLOCK) * self::BLOCK;
    }

    /**
     * One 512-byte header in the POSIX ustar layout, which every tar reads (Windows' own `tar`, 7-Zip, GNU tar).
     */
    private function header(string $name, int $size, string $type, int $mode): string
    {
        $block = pack(
            'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
            $name,
            sprintf('%07o', $mode),
            sprintf('%07o', 0),
            sprintf('%07o', 0),
            sprintf('%011o', $size),
            sprintf('%011o', time()),
            '        ', // the checksum is worked out with this field as spaces
            $type,
            '',
            "ustar\0",
            '00',
            '',
            '',
            '',
            '',
            '',
            '',
        );

        return substr_replace($block, sprintf('%06o', array_sum((array) unpack('C*', $block)))."\0 ", 148, 8);
    }
}
