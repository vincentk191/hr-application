<?php

namespace App\Support\Files;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets a stored file be looked at in the page instead of downloaded, for the pictures people attach to a request or
 * file on a profile. Only the raster types the uploads accept are ever shown inline, with their type fixed from the
 * extension and sniffing switched off, so nothing else (a PDF, a Word file, a renamed script) is opened by the
 * browser. The routes that call this keep their own policy check: asking for the preview is asking for the file.
 */
class InlineImage
{
    /** @var array<string, string> */
    private const TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public static function isImage(string $name): bool
    {
        return isset(self::TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))]);
    }

    /**
     * The link that shows the file in the page, or null for a file that is not a picture.
     */
    public static function previewUrl(string $downloadUrl, string $name): ?string
    {
        return self::isImage($name) ? $downloadUrl.(str_contains($downloadUrl, '?') ? '&' : '?').'inline=1' : null;
    }

    /**
     * The file as an inline picture when the request asks for one and it is a picture, otherwise as a download.
     */
    public static function serve(Request $request, string $disk, string $path, string $name): StreamedResponse
    {
        $storage = Storage::disk($disk);

        if (! $request->boolean('inline') || ! self::isImage($name)) {
            return $storage->download($path, $name);
        }

        return $storage->response($path, $name, [
            'Content-Type' => self::TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))],
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
