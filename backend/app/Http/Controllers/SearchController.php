<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search (CORE-SRC): one box over employees, leave requests, documents and the app's own
 * pages, for the header.
 *
 * It only ever searches what the signed-in person could already open. Company scoping is applied by
 * the models, and each group asks the same rule the screen it leads to asks: the directory's
 * permission, the leave queue's visibility, the document policy. A restricted document someone may
 * not read does not appear, not even by title.
 */
class SearchController extends Controller
{
    private const MIN_LENGTH = 2;

    private const PER_GROUP = 5;

    public function index(Request $request): JsonResponse
    {
        $query = mb_strtolower(trim($request->string('q')->toString()));

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();

        $groups = array_values(array_filter([
            $this->pages($user, $query),
            $this->employees($user, $query),
            $this->leaveRequests($user, $query),
            $this->documents($user, $query),
        ], fn (?array $group): bool => $group !== null && $group['items'] !== []));

        return response()->json(['groups' => $groups]);
    }

    /**
     * The places in the app this person can go, matched by name.
     *
     * @return array{key: string, label: string, items: array<int, array{id: string, title: string, subtitle: string|null, url: string}>}
     */
    private function pages(User $user, string $query): array
    {
        $pages = [
            ['Dashboard', 'dashboard', true],
            ['Karyawan', 'employees.index', $user->can(Permission::ViewEmployees->value)],
            ['Pengajuan cuti', 'leave-requests.index', true],
            ['Ajukan cuti', 'leave-requests.create', true],
            ['Notifikasi', 'notifications.index', true],
            ['Kalender absensi', 'attendance.calendar', $user->can(Permission::ViewAttendance->value)],
            ['Rekap absensi', 'attendance.recap', $user->can(Permission::ViewAttendance->value)],
            ['Masalah absensi', 'attendance.issues', $user->can(Permission::ViewAttendance->value)],
            ['Data absensi', 'attendance.device', $user->can('manage-attendance-device')],
            ['Impor karyawan', 'employees.import', $user->can('create', Employee::class)],
        ];

        $items = [];

        foreach ($pages as [$title, $route, $allowed]) {
            if ($allowed && str_contains(mb_strtolower($title), $query)) {
                $items[] = ['id' => $route, 'title' => $title, 'subtitle' => null, 'url' => route($route, absolute: false)];
            }
        }

        return ['key' => 'pages', 'label' => 'Halaman', 'items' => array_slice($items, 0, self::PER_GROUP)];
    }

    /**
     * @return array{key: string, label: string, items: array<int, array{id: string, title: string, subtitle: string|null, url: string}>}|null
     */
    private function employees(User $user, string $query): ?array
    {
        if ($user->cannot('viewAny', Employee::class)) {
            return null;
        }

        $like = $this->like($query);

        $found = Employee::query()
            ->with(['department:id,name', 'position:id,name'])
            ->where(fn (Builder $inner) => $inner
                ->whereRaw("lower(full_name) like ? escape '!'", [$like])
                ->orWhereRaw('lower(employee_number) like ? escape "!"', [$like])
                ->orWhereRaw('lower(email) like ? escape "!"', [$like]))
            ->orderBy('full_name')
            ->limit(self::PER_GROUP)
            ->get();

        return [
            'key' => 'employees',
            'label' => 'Karyawan',
            'items' => $found->map(fn (Employee $employee): array => [
                'id' => (string) $employee->id,
                'title' => $employee->full_name,
                'subtitle' => collect([$employee->employee_number, $employee->position?->name, $employee->department?->name])->filter()->implode(' · '),
                'url' => route('employees.show', $employee, absolute: false),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{key: string, label: string, items: array<int, array{id: string, title: string, subtitle: string|null, url: string}>}
     */
    private function leaveRequests(User $user, string $query): array
    {
        $like = $this->like($query);
        $reference = preg_match('/^req-?0*(\d+)$/', $query, $match) === 1 ? (int) $match[1] : null;

        $found = LeaveRequest::query()
            ->visibleTo($user)
            ->with('employee:id,full_name')
            ->where(fn (Builder $inner) => $inner
                ->when($reference !== null, fn (Builder $q) => $q->orWhere('id', $reference))
                ->orWhereHas('employee', fn (Builder $employee) => $employee->whereRaw("lower(full_name) like ? escape '!'", [$like])))
            ->latest('id')
            ->limit(self::PER_GROUP)
            ->get();

        return [
            'key' => 'leave',
            'label' => 'Pengajuan cuti',
            'items' => $found->map(fn (LeaveRequest $leave): array => [
                'id' => (string) $leave->id,
                'title' => "{$leave->reference()} · {$leave->employee->full_name}",
                'subtitle' => "{$leave->leave_type->label()} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()}",
                'url' => route('leave-requests.index', ['open' => $leave->id], absolute: false),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{key: string, label: string, items: array<int, array{id: string, title: string, subtitle: string|null, url: string}>}
     */
    private function documents(User $user, string $query): array
    {
        $like = $this->like($query);

        // Fetch a little more than will be shown, because the policy drops what this person may not read.
        $found = EmployeeDocument::query()
            ->with('employee:id,full_name,user_id')
            ->whereRaw("lower(title) like ? escape '!'", [$like])
            ->latest('id')
            ->limit(self::PER_GROUP * 4)
            ->get()
            ->filter(fn (EmployeeDocument $document): bool => $user->can('view', $document))
            ->take(self::PER_GROUP);

        return [
            'key' => 'documents',
            'label' => 'Dokumen',
            'items' => $found->map(fn (EmployeeDocument $document): array => [
                'id' => (string) $document->id,
                'title' => $document->title,
                'subtitle' => "{$document->employee->full_name} · {$document->category->label()}",
                'url' => route('employees.show', ['employee' => $document->employee_id, 'tab' => 'documents'], absolute: false),
            ])->values()->all(),
        ];
    }

    /**
     * A LIKE pattern for the text, with the wildcards in it escaped so a search for "50%" is a
     * search for 50%.
     */
    private function like(string $query): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
    }
}
