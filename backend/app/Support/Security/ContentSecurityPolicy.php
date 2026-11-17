<?php

namespace App\Support\Security;

/**
 * The script and style policy for the app's own pages, sent in report-only mode first (nothing is blocked; browsers
 * report what would have been). Scripts and styles may come from this site, or carry the request's nonce.
 *
 * The admin panel (Filament, Livewire, Alpine) is not covered: it needs inline and evaluated scripts, so a policy that
 * fits it would say nothing about scripts. It is reached over the VPN only.
 *
 * `style-src-attr` allows `style="..."` attributes, which React's own markup uses; scripts get no such allowance.
 */
class ContentSecurityPolicy
{
    public const REPORT_PATH = '/csp-report';

    public static function reportOnly(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
            "media-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'report-uri '.self::REPORT_PATH,
        ]);
    }
}
