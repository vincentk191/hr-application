<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives the browser's report of what the report-only policy would have blocked, and writes the useful parts to the
 * `csp` log. No sign-in (the sign-in page is covered too), so it is throttled, ignores anything oversized or malformed,
 * and logs only a cleaned-up address: no query string, and the candidate form's personal link is cut to `/lamaran/*`.
 */
class CspReportController extends Controller
{
    private const MAX_BYTES = 8192;

    private const MAX_LENGTH = 200;

    /** What the browser sends in place of an address. */
    private const KEYWORDS = ['inline', 'eval', 'data', 'blob', 'self', 'wasm-eval', 'trusted-types-policy'];

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();

        if (strlen($body) <= self::MAX_BYTES) {
            $report = json_decode($body, true)['csp-report'] ?? null;

            if (is_array($report)) {
                Log::channel('csp')->warning('Policy violation (report only)', [
                    'directive' => $this->text($report['effective-directive'] ?? $report['violated-directive'] ?? null),
                    'blocked' => $this->address($report['blocked-uri'] ?? null),
                    'page' => $this->address($report['document-uri'] ?? null),
                    'source' => $this->address($report['source-file'] ?? null),
                    'line' => is_int($report['line-number'] ?? null) ? $report['line-number'] : null,
                    'column' => is_int($report['column-number'] ?? null) ? $report['column-number'] : null,
                ]);
            }
        }

        return response()->noContent();
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) ? mb_substr($value, 0, self::MAX_LENGTH) : null;
    }

    private function address(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (in_array($value, self::KEYWORDS, true)) {
            return $value;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'])) {
            return '[unreadable]';
        }

        $path = preg_replace('#^/lamaran/[^/]*#', '/lamaran/*', $parts['path'] ?? '');

        return mb_substr($parts['scheme'].'://'.($parts['host'] ?? '').$path, 0, self::MAX_LENGTH);
    }
}
