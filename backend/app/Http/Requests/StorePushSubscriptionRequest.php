<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\PushSubscription;

/**
 * A browser's push subscription, as `PushSubscription.toJSON()` gives it: the endpoint and the two keys.
 */
class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH, $this->isKnownPushService(...)],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.*' => 'Langganan notifikasi dari peramban ini tidak dapat diterima.',
            'keys.*' => 'Langganan notifikasi dari peramban ini tidak lengkap.',
        ];
    }

    /**
     * The server later posts to the stored endpoint, so it must belong to a known push service, never an address
     * the signed-in person picked.
     *
     * @param  Closure(string): void  $fail
     */
    private function isKnownPushService(string $attribute, mixed $value, Closure $fail): void
    {
        $host = Str::lower((string) parse_url((string) $value, PHP_URL_HOST));

        foreach ((array) config('webpush.allowed_endpoint_hosts') as $service) {
            if ($host === $service || str_ends_with($host, ".{$service}")) {
                return;
            }
        }

        $fail('Layanan notifikasi ini tidak dikenali.');
    }

    public function endpoint(): string
    {
        return (string) $this->validated('endpoint');
    }

    public function publicKey(): string
    {
        return (string) $this->validated('keys.p256dh');
    }

    public function authToken(): string
    {
        return (string) $this->validated('keys.auth');
    }
}
