<?php

namespace App\Support\Files;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Stores an uploaded file under a random name on a private disk, shrinking a PDF on the way in (config/uploads.php).
 *
 * The compressed copy is only kept when it is readable and really smaller. Any other outcome stores the upload
 * untouched, so compression can make a file smaller but never stops one being filed.
 */
class UploadFiler
{
    /**
     * @param  bool  $compress  false for a file that must be kept byte for byte, such as a signed document
     * @return array{path: string, size: int} where the file was stored, and how many bytes it came to
     */
    public function store(UploadedFile $file, string $directory, string $disk, bool $compress = true): array
    {
        $compressed = $compress ? $this->compressedPdf($file) : null;

        if ($compressed === null) {
            return [
                'path' => $file->store($directory, $disk) ?: throw new RuntimeException('The uploaded file could not be stored.'),
                'size' => (int) $file->getSize(),
            ];
        }

        try {
            return [
                'path' => Storage::disk($disk)->putFile($directory, new File($compressed)) ?: throw new RuntimeException('The uploaded file could not be stored.'),
                'size' => (int) filesize($compressed),
            ];
        } finally {
            @unlink($compressed);
        }
    }

    /**
     * The path of a smaller copy of the upload in a temporary file, or null when it should be stored as it came.
     */
    private function compressedPdf(UploadedFile $file): ?string
    {
        if (! config('uploads.pdf.compress')
            || $file->getMimeType() !== 'application/pdf'
            || (int) $file->getSize() < (int) config('uploads.pdf.minimum_kb') * 1024) {
            return null;
        }

        $output = (string) tempnam(sys_get_temp_dir(), 'pdf');

        try {
            $result = Process::timeout((int) config('uploads.pdf.timeout'))->run([
                (string) config('uploads.pdf.ghostscript'),
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.5',
                '-dPDFSETTINGS='.config('uploads.pdf.preset'),
                '-dNOPAUSE',
                '-dBATCH',
                '-dQUIET',
                '-dSAFER',
                '-sOutputFile='.$output,
                (string) $file->getRealPath(),
            ]);
        } catch (Throwable $exception) {
            // Not installed, or it ran out of time: the upload is filed as it is.
            Log::warning('PDF compression skipped: '.$exception->getMessage());
            @unlink($output);

            return null;
        }

        $size = is_file($output) ? (int) filesize($output) : 0;

        if (! $result->successful() || $size === 0 || $size >= (int) $file->getSize()) {
            @unlink($output);

            return null;
        }

        return $output;
    }
}
