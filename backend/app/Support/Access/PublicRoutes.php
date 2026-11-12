<?php

namespace App\Support\Access;

use Illuminate\Routing\Route;

/**
 * Which routes the public entrance serves (CORE-ACC-002): employee self-service and signing in, nothing else.
 * Every route has to be classed here as public or private; a route that is neither is refused on the public
 * entrance (it fails closed) and fails the test that lists every route, so a new route cannot slip through.
 *
 * Several public routes return different data per account (the dashboard, leave requests, search, a profile);
 * on the public entrance those policies treat everyone as a plain employee, so the URL being public does not
 * widen what it shows.
 */
class PublicRoutes
{
    /** Route names served on the public entrance. */
    public const PUBLIC_NAMES = [
        'home',
        // The candidate form, opened from the link in an interview invitation (no sign-in; the link is the credential).
        'candidate-form.show', 'candidate-form.store',
        // Signing in and out, two-factor, and confirming a password.
        'login', 'login.store', 'logout', 'register', 'register.store',
        'two-factor.login', 'two-factor.login.store', 'two-factor.enable', 'two-factor.confirm', 'two-factor.disable',
        'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        // The person's own pages.
        'dashboard', 'search', 'active-company.update',
        'notifications.index', 'notifications.open', 'notifications.read-all',
        'push-subscriptions.store', 'push-subscriptions.destroy', 'push-subscriptions.test',
        'leave-requests.index', 'leave-requests.create', 'leave-requests.conflicts', 'leave-requests.store', 'leave-requests.edit', 'leave-requests.update', 'leave-requests.export',
        'leave-attachments.download',
        'employees.show', 'employees.edit', 'employees.update', 'employees.summary', 'employees.changes.create', 'employees.changes.store',
        'documents.download', 'profile-changes.destroy',
        'user-password.update', 'security.edit', 'appearance.edit', 'notification-settings.edit',
    ];

    /** Route URIs (no name) served on the public entrance. */
    public const PUBLIC_URIS = ['up', 'settings'];

    /** Name prefixes that are private: never served on the public entrance. */
    public const PRIVATE_PREFIXES = [
        'filament.', 'livewire.', 'default-livewire.', 'api.', 'attendance.', 'calendar-events.', 'punches.', 'offboarding.', 'recruitment.',
        'storage.', 'sanctum.', 'boost.', 'verification.', 'password.request', 'password.email', 'password.reset', 'password.update',
        'employees.index', 'employees.create', 'employees.store', 'employees.activation', 'employees.reactivate', 'employees.photo', 'employees.import', 'employees.export',
        'documents.store', 'documents.update', 'documents.destroy', 'documents.versions',
        'profile-changes.index', 'profile-changes.decide', 'profile-changes.photo', 'leave-requests.decision',
        'user-password.',
    ];

    /**
     * The kinds of in-app notification (`InAppNotice`) whose pages are private: not shown on the public entrance, where the link
     * would be refused. The others (leave, account, document, system) lead to pages that are served there.
     */
    public const PRIVATE_NOTICE_KINDS = ['recruitment', 'offboarding', 'attendance', 'profile'];

    /** URI prefixes (for routes with no name) that are private. */
    public const PRIVATE_URIS = ['admin', 'livewire', '_boost', 'filament', 'storage', 'sanctum', 'api', 'email', 'forgot-password', 'reset-password'];

    /**
     * @return 'public'|'private'|null null when the route has not been classed
     */
    public static function classify(Route $route): ?string
    {
        $name = $route->getName();

        if ($name !== null && in_array($name, self::PUBLIC_NAMES, true)) {
            return 'public';
        }

        if (in_array(trim($route->uri(), '/'), self::PUBLIC_URIS, true)) {
            return 'public';
        }

        if ($name !== null) {
            foreach (self::PRIVATE_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return 'private';
                }
            }
        }

        foreach (self::PRIVATE_URIS as $prefix) {
            if (str_starts_with(trim($route->uri(), '/'), $prefix)) {
                return 'private';
            }
        }

        return null;
    }

    public static function servedPublicly(Route $route): bool
    {
        return self::classify($route) === 'public';
    }
}
