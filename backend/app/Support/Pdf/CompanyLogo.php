<?php

namespace App\Support\Pdf;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

/**
 * A company's logo as data inside a PDF page, so the PDF library never has to fetch anything. A logo it cannot draw (an SVG
 * or a WebP) is left out rather than breaking the document.
 */
class CompanyLogo
{
    /** The logo formats the PDF library can draw. */
    private const TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    public static function dataUri(?Company $company): ?string
    {
        $path = $company?->logo_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $type = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        return $type === null ? null : 'data:'.$type.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
