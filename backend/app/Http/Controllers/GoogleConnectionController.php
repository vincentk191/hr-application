<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\GoogleConnection;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Google\GoogleOAuth;
use App\Support\Google\GoogleUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connecting the Google account a company's interview invitations are sent from (REC-INT). HR with the recruitment permission
 * does it once, from the Interview tab of a requisition: Google's consent screen, then back here with a code that is
 * exchanged for the tokens the app keeps (GoogleOAuth).
 *
 * All of it is on the private address, like the rest of recruitment: the return address is the VPN hostname, and the consent
 * is approved in the browser of the HR user who started it.
 */
class GoogleConnectionController extends Controller
{
    /** What the session remembers between leaving for Google and coming back. */
    private const SESSION_KEY = 'google_oauth';

    /**
     * "Hubungkan Google": send HR to Google's consent screen. The requisition only says which company, and where to come back to.
     */
    public function connect(Request $request, Requisition $requisition, GoogleOAuth $oauth): Response|RedirectResponse
    {
        $this->authorizeFor($request->user(), $requisition->company_id);

        if (! $oauth->isConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Google belum disiapkan di server: '.implode(', ', $oauth->missingSettings()).' kosong.']);

            return back();
        }

        // The state ties the answer to this browser and this person, so a link someone else crafted cannot connect an account.
        $state = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'user' => $request->user()->id,
            'requisition' => $requisition->id,
        ]);

        return Inertia::location($oauth->authorizationUrl($state));
    }

    /**
     * Where Google sends HR back to. Keeps the connection if everything needed was granted.
     */
    public function callback(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        $asked = (string) $request->query('state');

        if (! is_array($pending) || $asked === '' || ! hash_equals((string) $pending['state'], $asked) || $pending['user'] !== $request->user()->id) {
            return $this->failed(null, 'Permintaan menghubungkan Google tidak dikenali. Mulai lagi dari tombol Hubungkan Google.');
        }

        $requisition = Requisition::acrossCompanies()->find($pending['requisition']);
        abort_unless($requisition instanceof Requisition, 404);
        $this->authorizeFor($request->user(), $requisition->company_id);

        $code = (string) $request->query('code');

        if ($request->query('error') !== null || $code === '') {
            return $this->failed($requisition, 'Google tidak jadi dihubungkan: izin tidak diberikan.');
        }

        try {
            $tokens = $oauth->connect($code);
        } catch (GoogleUnavailable $unavailable) {
            report($unavailable);

            return $this->failed($requisition, 'Google tidak bisa dihubungi untuk menyelesaikan koneksi. Coba lagi sebentar lagi.');
        }

        if (! $tokens['calendarGranted']) {
            return $this->failed($requisition, 'Izin untuk mengelola acara kalender tidak dicentang di layar Google. Ulangi dan biarkan semua izin tercentang.');
        }

        if ($tokens['refreshToken'] === null) {
            return $this->failed($requisition, 'Google tidak memberi izin jangka panjang. Cabut akses aplikasi di akun Google tersebut, lalu hubungkan lagi.');
        }

        GoogleConnection::acrossCompanies()->updateOrCreate(
            ['company_id' => $requisition->company_id],
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

        Inertia::flash('toast', ['type' => 'success', 'message' => "Google terhubung: {$tokens['email']}."]);

        return redirect()->route('recruitment.show', $requisition);
    }

    /**
     * "Putuskan": forget the account. Events already on its calendar stay there; inviting goes back to the app's own email.
     */
    public function disconnect(Request $request, Requisition $requisition, GoogleOAuth $oauth): RedirectResponse
    {
        $this->authorizeFor($request->user(), $requisition->company_id);

        $connection = GoogleConnection::acrossCompanies()->where('company_id', $requisition->company_id)->first();

        if ($connection instanceof GoogleConnection) {
            $oauth->revoke($connection);
            $connection->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Google diputus. Undangan interview kembali dikirim lewat email aplikasi.']);

        return back();
    }

    /**
     * Who runs recruitment for the company: the same permission that raises and approves requisitions.
     */
    private function authorizeFor(User $user, int $companyId): void
    {
        abort_unless($user->can(Permission::ManageRecruitment->value) && $user->canAccessCompany($companyId), 403);
    }

    private function failed(?Requisition $requisition, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return $requisition === null ? redirect()->route('recruitment.index') : redirect()->route('recruitment.show', $requisition);
    }
}
