<?php

use App\Support\Security\ContentSecurityPolicy;

function cspLogFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'csp');
    config(['logging.channels.csp' => ['driver' => 'single', 'path' => $path, 'level' => 'warning']]);

    return $path;
}

it('sends the report-only policy on the app pages, with the nonce the page itself carries', function () {
    config(['security.csp.report_only' => true]);

    $response = $this->get(route('login'))->assertOk();

    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("script-src 'self' 'nonce-")->toContain('report-uri '.ContentSecurityPolicy::REPORT_PATH);

    preg_match("/'nonce-([^']+)'/", $policy, $found);

    expect($found[1])->not->toBe('')
        ->and($response->getContent())->toContain('<script nonce="'.$found[1].'">')
        ->toContain('<style nonce="'.$found[1].'">');
});

it('sends no policy while the setting is off', function () {
    config(['security.csp.report_only' => false]);

    $this->get(route('login'))->assertOk()->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('leaves the admin panel out of the script and style policy', function () {
    config(['security.csp.report_only' => true]);

    $this->get('/admin/login')->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('writes a policy report to the csp log without the query string or the candidate form link', function () {
    $log = cspLogFile();

    $this->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
        'document-uri' => 'https://hr.example.test/lamaran/SECRETTOKEN123?utm=private',
        'effective-directive' => 'style-src-elem',
        'blocked-uri' => 'inline',
        'source-file' => 'https://hr.example.test/build/assets/app-abc.js?v=1',
        'line-number' => 12,
        'column-number' => 3,
    ]]))->assertNoContent();

    $written = file_get_contents($log);

    expect($written)->toContain('style-src-elem')
        ->toContain('https://hr.example.test/lamaran/*')
        ->toContain('https://hr.example.test/build/assets/app-abc.js')
        ->not->toContain('SECRETTOKEN123')
        ->not->toContain('utm=private')
        ->not->toContain('v=1');

    unlink($log);
});

it('accepts a report from the public entrance with no sign-in and no CSRF token', function () {
    $log = cspLogFile();

    $this->withServerVariables(['ENTRANCE' => 'public'])
        ->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => ['blocked-uri' => 'eval', 'effective-directive' => 'script-src-elem']]))
        ->assertNoContent();

    expect(file_get_contents($log))->toContain('script-src-elem');

    unlink($log);
});

it('ignores a report that is malformed or too large, and still answers', function () {
    $log = cspLogFile();

    $this->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')->assertNoContent();
    $this->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => ['blocked-uri' => str_repeat('a', 9000)]]))->assertNoContent();

    expect(file_get_contents($log))->toBe('');

    unlink($log);
});

it('throttles the report endpoint per address', function () {
    cspLogFile();

    foreach (range(1, 30) as $ignored) {
        $this->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], '{}')->assertNoContent();
    }

    $this->call('POST', route('csp-report'), [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], '{}')->assertTooManyRequests();
});
