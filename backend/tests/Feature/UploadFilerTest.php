<?php

use App\Support\Files\UploadFiler;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Shrinking PDFs on their way into storage (config/uploads.php). Ghostscript itself is not run: the process
 * is faked, so these tests pin what is kept and when, not what Ghostscript does to a file.
 */
beforeEach(function () {
    Storage::fake('local');
    config(['uploads.pdf.minimum_kb' => 200]);
});

function bigPdf(int $kilobytes = 1000): UploadedFile
{
    return UploadedFile::fake()->create('surat.pdf', $kilobytes, 'application/pdf');
}

/**
 * Fakes Ghostscript writing a file of the given size where it was asked to, and returning the given exit code.
 */
function fakeGhostscript(int $outputBytes, int $exitCode = 0): void
{
    Process::fake(function (PendingProcess $process) use ($outputBytes, $exitCode) {
        $output = collect($process->command)->first(fn (string $part) => str_starts_with($part, '-sOutputFile='));

        file_put_contents(substr($output, strlen('-sOutputFile=')), str_repeat('x', $outputBytes));

        return Process::result(exitCode: $exitCode);
    });
}

it('stores the smaller copy of a large PDF and records its size', function () {
    fakeGhostscript(outputBytes: 300 * 1024);

    $stored = app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local');

    expect($stored['size'])->toBe(300 * 1024);
    Storage::disk('local')->assertExists($stored['path']);
    expect(Storage::disk('local')->size($stored['path']))->toBe(300 * 1024);
});

it('keeps the original when the compressed copy is not smaller', function () {
    fakeGhostscript(outputBytes: 2000 * 1024);

    $stored = app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local');

    expect($stored['size'])->toBe(1000 * 1024);
    Storage::disk('local')->assertExists($stored['path']);
});

it('keeps the original when Ghostscript fails on the file', function () {
    fakeGhostscript(outputBytes: 10, exitCode: 1);

    $stored = app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local');

    expect($stored['size'])->toBe(1000 * 1024);
});

it('keeps the original when Ghostscript cannot be run at all', function () {
    Process::fake(fn () => throw new RuntimeException('gs: command not found'));

    $stored = app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local');

    expect($stored['size'])->toBe(1000 * 1024);
    Storage::disk('local')->assertExists($stored['path']);
});

it('leaves no temporary file behind', function () {
    fakeGhostscript(outputBytes: 300 * 1024);
    $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf*') ?: [];

    app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local');

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf*') ?: [])->toBe($before);
});

it('does not run Ghostscript for a small PDF, an image, or when compression is off', function () {
    Process::fake();
    $filer = app(UploadFiler::class);

    $filer->store(bigPdf(50), 'docs', 'local');
    $filer->store(UploadedFile::fake()->image('foto.jpg')->size(1000), 'docs', 'local');

    config(['uploads.pdf.compress' => false]);
    $filer->store(bigPdf(1000), 'docs', 'local');

    Process::assertNothingRan();
});

it('does not compress a PDF when the caller asks for it to be kept as uploaded', function () {
    fakeGhostscript(outputBytes: 300 * 1024);

    $stored = app(UploadFiler::class)->store(bigPdf(1000), 'docs', 'local', compress: false);

    expect($stored['size'])->toBe(1000 * 1024);
    Process::assertNothingRan();
});
