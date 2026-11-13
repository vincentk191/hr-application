<?php

namespace App\Support\Google;

use App\Models\GoogleConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Signing a company's Google account in to the app and keeping it signed in (REC-INT): the consent screen's address, the
 * exchange of its code for tokens, and a fresh access token whenever one is needed.
 *
 * The app asks for the narrowest calendar permission that can create and change events, plus the account's email to show
 * which account is connected. The refresh token is the credential and is kept encrypted; access tokens last about an hour
 * and are kept only to avoid asking again for each call.
 */
class GoogleOAuth
{
    /** The permission that lets the app create and change events; nothing else of the calendar. */
    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /** A token this close to its end is replaced before it is used, so it cannot lapse mid-request. */
    private const EXPIRY_MARGIN_SECONDS = 120;

    /**
     * Whether the app has what it needs to talk to Google: the client ID, its secret and the return address, set in the
     * server's environment.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * Where to send HR to approve the connection. `offline` and `consent` make Google hand back a refresh token every time,
     * also when the account approved the app before.
     */
    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', ['openid', 'email', self::CALENDAR_SCOPE]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the code Google sent back for tokens, and asks whose account it is.
     *
     * @return array{email: string, refreshToken: string|null, accessToken: string, expiresAt: CarbonImmutable, calendarGranted: bool}
     *
     * @throws GoogleUnavailable
     */
    public function connect(string $code): array
    {
        $token = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.google.redirect'),
        ]);

        if ($token->failed()) {
            throw new GoogleUnavailable('Google token exchange failed: '.$token->status().' '.$this->errorOf($token));
        }

        $accessToken = (string) $token->json('access_token');

        try {
            $profile = $this->client()->withToken($accessToken)->get(self::USERINFO_URL);
        } catch (ConnectionException $exception) {
            throw new GoogleUnavailable('Google userinfo unreachable: '.$exception->getMessage(), previous: $exception);
        }

        if ($profile->failed() || blank($profile->json('email'))) {
            throw new GoogleUnavailable('Google userinfo failed: '.$profile->status());
        }

        return [
            'email' => (string) $profile->json('email'),
            'refreshToken' => $token->json('refresh_token'),
            'accessToken' => $accessToken,
            'expiresAt' => now()->addSeconds((int) $token->json('expires_in', 3600)),
            // The consent screen lets the person untick permissions: without the calendar one nothing here can work.
            'calendarGranted' => in_array(self::CALENDAR_SCOPE, explode(' ', (string) $token->json('scope')), true),
        ];
    }

    /**
     * A working access token for the connection, replaced from the refresh token when it has run out.
     *
     * @throws GoogleAuthLost when Google no longer accepts the refresh token
     * @throws GoogleUnavailable
     */
    public function accessToken(GoogleConnection $connection, bool $fresh = false): string
    {
        $current = $connection->access_token;
        $expires = $connection->access_token_expires_at;

        if (! $fresh && $current !== null && $expires !== null && $expires->isAfter(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS))) {
            return $current;
        }

        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);

        // "invalid_grant" is Google saying this refresh token is dead; any other failure is trouble on the way.
        if ($response->status() === 400 && $response->json('error') === 'invalid_grant') {
            throw new GoogleAuthLost($connection, $connection->markNeedsReconnect());
        }

        if ($response->failed()) {
            throw new GoogleUnavailable('Google token refresh failed: '.$response->status().' '.$this->errorOf($response));
        }

        $connection->forceFill([
            'access_token' => (string) $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();

        return (string) $connection->access_token;
    }

    /**
     * Tells Google to forget the connection, so the app no longer shows under the account's permissions. Best effort: the
     * connection is removed from the app either way.
     */
    public function revoke(GoogleConnection $connection): void
    {
        try {
            $this->client()->asForm()->post(self::REVOKE_URL, ['token' => $connection->refresh_token]);
        } catch (ConnectionException) {
            // Google can be reached later from the account's own permissions page.
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws GoogleUnavailable
     */
    private function tokenRequest(array $fields): Response
    {
        try {
            return $this->client()->asForm()->post(self::TOKEN_URL, [
                ...$fields,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
            ]);
        } catch (ConnectionException $exception) {
            throw new GoogleUnavailable('Google token endpoint unreachable: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function client(): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(15);
    }

    private function errorOf(Response $response): string
    {
        return (string) ($response->json('error_description') ?? $response->json('error') ?? '');
    }
}
