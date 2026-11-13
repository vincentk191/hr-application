<?php

namespace App\Support\Google;

use App\Models\GoogleConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The few calls to Google Calendar the interviews need (REC-INT): make an event, change it, delete it, and read it back to
 * see whether somebody changed it on Google. Every call is made as the company's connected account, on its main calendar.
 *
 * Making an event carries an ID of our own. That is what lets a request that timed out be sent again without making the
 * event twice: the second attempt is answered with "already exists", and the event is read instead.
 */
class GoogleCalendar
{
    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    /** Who Google emails about a change: `all` everyone invited, `none` nobody. */
    public const NOTIFY_ALL = 'all';

    public const NOTIFY_NONE = 'none';

    public function __construct(private GoogleOAuth $oauth) {}

    /**
     * Makes the event, with a Google Meet room when asked, and returns it as Google holds it (the Meet link, the etag).
     *
     * @param  array<string, mixed>  $event  the event's fields; `id` is required and stays the event's ID
     * @return array<string, mixed>
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function insert(GoogleConnection $connection, array $event, bool $withMeet, string $notify): array
    {
        if ($withMeet) {
            $event['conferenceData'] = $this->meetRequest((string) $event['id']);
        }

        $response = $this->send($connection, 'post', '', ['conferenceDataVersion' => 1, 'sendUpdates' => $notify], $event);

        if ($response->status() === 409) {
            // An earlier attempt got through before the connection dropped: the event is there already.
            return $this->find($connection, (string) $event['id']) ?? throw new GoogleUnavailable('Google Calendar: the event exists but could not be read.');
        }

        $this->ensure($response);

        return (array) $response->json();
    }

    /**
     * Changes the fields given. `$meet` true adds a Meet room, false removes it, null leaves it as it is.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws GoogleEventGone
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function patch(GoogleConnection $connection, string $eventId, array $fields, ?bool $meet, string $notify): array
    {
        if ($meet === true) {
            $fields['conferenceData'] = $this->meetRequest((string) Str::uuid());
        } elseif ($meet === false) {
            $fields['conferenceData'] = null;
        }

        $response = $this->send($connection, 'patch', "/{$eventId}", ['conferenceDataVersion' => 1, 'sendUpdates' => $notify], $fields);

        $this->throwIfGone($response);
        $this->ensure($response);

        return (array) $response->json();
    }

    /**
     * Deletes the event; an event that is already gone is the same outcome.
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function delete(GoogleConnection $connection, string $eventId, string $notify): void
    {
        $response = $this->send($connection, 'delete', "/{$eventId}", ['sendUpdates' => $notify]);

        $this->ensure($response, [404, 410]);
    }

    /**
     * Reads the event. With the etag of the version already known, null means it has not changed.
     *
     * @return array<string, mixed>|null
     *
     * @throws GoogleEventGone
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    public function find(GoogleConnection $connection, string $eventId, ?string $knownEtag = null): ?array
    {
        $response = $this->send($connection, 'get', "/{$eventId}", [], null, $knownEtag === null ? [] : ['If-None-Match' => $knownEtag]);

        if ($response->status() === 304) {
            return null;
        }

        $this->throwIfGone($response);
        $this->ensure($response);

        return (array) $response->json();
    }

    /**
     * @return array{createRequest: array{requestId: string, conferenceSolutionKey: array{type: string}}}
     */
    private function meetRequest(string $requestId): array
    {
        return ['createRequest' => ['requestId' => $requestId, 'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]];
    }

    /**
     * One call, with a second try on a new token if Google says the one used is no good, and a third stop if it still is.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @param  array<string, string>  $headers
     *
     * @throws GoogleAuthLost
     * @throws GoogleUnavailable
     */
    private function send(GoogleConnection $connection, string $method, string $path, array $query, ?array $json = null, array $headers = []): Response
    {
        $response = $this->attempt($connection, $method, $path, $query, $json, $headers, false);

        if ($response->status() === 401) {
            $response = $this->attempt($connection, $method, $path, $query, $json, $headers, true);
        }

        if ($response->status() === 401) {
            throw new GoogleAuthLost($connection, $connection->markNeedsReconnect());
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @param  array<string, string>  $headers
     */
    private function attempt(GoogleConnection $connection, string $method, string $path, array $query, ?array $json, array $headers, bool $freshToken): Response
    {
        $request = Http::connectTimeout(5)
            ->timeout(15)
            ->withToken($this->oauth->accessToken($connection, $freshToken))
            ->withHeaders($headers)
            ->acceptJson()
            // Every call here can be sent again with the same result (the event has an ID of its own), so a dropped
            // connection, a busy Google or a server error is tried again, a few times, before giving up.
            // (A "not modified" answer is neither a success nor an error, so there is no exception to look at: it is final.)
            ->retry([300, 1000, 2000], 0, fn (?Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);

        try {
            return $request
                ->withQueryParameters($query)
                ->send(strtoupper($method), self::EVENTS_URL.$path, $json === null ? [] : ['json' => $json]);
        } catch (ConnectionException $exception) {
            throw new GoogleUnavailable('Google Calendar unreachable: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @throws GoogleEventGone
     */
    private function throwIfGone(Response $response): void
    {
        if (in_array($response->status(), [404, 410], true)) {
            throw new GoogleEventGone('The event is not on Google Calendar any more.');
        }
    }

    /**
     * @param  list<int>  $tolerated  statuses that are an answer rather than a failure
     *
     * @throws GoogleUnavailable
     */
    private function ensure(Response $response, array $tolerated = []): void
    {
        if ($response->successful() || in_array($response->status(), $tolerated, true)) {
            return;
        }

        throw new GoogleUnavailable('Google Calendar '.$response->status().': '.Str::limit((string) ($response->json('error.message') ?? $response->body()), 300));
    }
}
