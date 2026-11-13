<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\GoogleConnection;
use App\Models\User;
use App\Support\Google\GoogleOAuth;
use App\Support\Google\GoogleUnavailable;
use App\Support\Recruitment\InterviewEvent;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connecting the Google account a company's interview invitations are sent from (REC-INT). Only an admin does it, from the
 * company's page in the admin panel, where the one allowed sender address is set. The account that Google's consent screen
 * ends up approving has to be that address, so HR cannot send from another account, whoever signs in.
 *
 * All of it is on the private address, like the rest of recruitment and the admin panel: the return address is the VPN
 * hostname. Disconnecting is the admin panel's own action (InterviewEvent::disconnect).
 */
class GoogleConnectionController extends Controller
{
    /** What the session remembers between leaving for Google and coming back. */
    private const SESSION_KEY = 'google_oauth';

    /**
     * "Hubungkan Google": send the admin to Google's consent screen, with the company's sender address offered as the account.
     */
    public function connect(Request $request, Company $company, GoogleOAuth $oauth): Response|RedirectResponse
    {
        $this->authorizeFor($request->user(), $company->id);

        if (! $oauth->isConfigured()) {
            return $this->failed($company, 'Google belum disiapkan di server: '.implode(', ', $oauth->missingSettings()).' kosong.');
        }

        $sender = app(InterviewEvent::class)->senderOf($company->id);

        if ($sender === null) {
            return $this->failed($company, 'Isi alamat pengirim undangan perusahaan ini dan simpan dulu.');
        }

        // The state ties the answer to this browser and this person, so a link someone else crafted cannot connect an account.
        $state = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'user' => $request->user()->id,
            'company' => $company->id,
        ]);

        return Inertia::location($oauth->authorizationUrl($state, $sender));
    }

    /**
     * Where Google sends the admin back to. Keeps the connection only if the account approved is the company's sender address
     * and everything needed was granted.
     */
    public function callback(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        $asked = (string) $request->query('state');

        if (! is_array($pending) || $asked === '' || ! hash_equals((string) $pending['state'], $asked) || $pending['user'] !== $request->user()->id) {
            Notification::make()->title('Permintaan menghubungkan Google tidak dikenali. Mulai lagi dari tombol Hubungkan Google.')->danger()->send();

            return redirect('/admin/companies');
        }

        $company = Company::query()->find($pending['company']);
        abort_unless($company instanceof Company, 404);
        $this->authorizeFor($request->user(), $company->id);

        $code = (string) $request->query('code');

        if ($request->query('error') !== null || $code === '') {
            return $this->failed($company, 'Google tidak jadi dihubungkan: izin tidak diberikan.');
        }

        try {
            $tokens = $oauth->connect($code);
        } catch (GoogleUnavailable $unavailable) {
            report($unavailable);

            return $this->failed($company, 'Google tidak bisa dihubungi untuk menyelesaikan koneksi. Coba lagi sebentar lagi.');
        }

        $sender = app(InterviewEvent::class)->senderOf($company->id);

        // Whoever signed in, only the company's own sender address may be connected.
        if ($sender === null || strcasecmp($tokens['email'], $sender) !== 0) {
            return $this->failed($company, "Akun yang dipilih ({$tokens['email']}) bukan alamat pengirim perusahaan ini ({$sender}). Tidak ada yang dihubungkan.");
        }

        if (! $tokens['calendarGranted']) {
            return $this->failed($company, 'Izin untuk mengelola acara kalender tidak dicentang di layar Google. Ulangi dan biarkan semua izin tercentang.');
        }

        if ($tokens['refreshToken'] === null) {
            return $this->failed($company, 'Google tidak memberi izin jangka panjang. Cabut akses aplikasi di akun Google tersebut, lalu hubungkan lagi.');
        }

        GoogleConnection::acrossCompanies()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'google_email' => $tokens['email'],
                'refresh_token' => $tokens['refreshToken'],
                'access_token' => $tokens['accessToken'],
                'access_token_expires_at' => $tokens['expiresAt'],
                'connected_by_user_id' => $request->user()->id,
                'connected_at' => now(),
                'needs_reconnect_at' => null,
                'last_synced_at' => null,
            ],
        );

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($company)
            ->withProperties(['account' => $tokens['email']])
            ->log('Google account connected');

        Notification::make()->title("Google terhubung: {$tokens['email']}.")->success()->send();

        return redirect(self::adminPage($company));
    }

    /**
     * Who runs the companies: the same permission as the Perusahaan screen of the admin panel.
     */
    private function authorizeFor(User $user, int $companyId): void
    {
        abort_unless($user->can(Permission::ManageCompanies->value) && $user->canAccessCompany($companyId), 403);
    }

    private function failed(Company $company, string $message): RedirectResponse
    {
        Notification::make()->title($message)->danger()->send();

        return redirect(self::adminPage($company));
    }

    /**
     * The company's page in the admin panel. A path rather than the panel's route name: the panel's routes are only registered
     * on its own requests, and this one is Google's return address.
     */
    public static function adminPage(Company $company): string
    {
        return "/admin/companies/{$company->getKey()}/edit";
    }
}
