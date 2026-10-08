<?php

namespace App\Http\Controllers\Api;

use App\Actions\Attendance\IngestPunches;
use App\Http\Controllers\Controller;
use App\Http\Requests\IngestPunchesRequest;
use App\Models\AttendanceAgent;
use App\Models\AttendanceIngestRequest;
use App\Models\AttendancePunch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receives punches from the on-site sync agent (ATT-CAL-003).
 *
 * The filing itself is IngestPunches, the same code the manual file import uses, so the two routes
 * into the system de-duplicate identically: pushing the same punch twice, or a punch the
 * file import already loaded, changes nothing.
 */
class AttendanceIngestController extends Controller
{
    public function store(IngestPunchesRequest $request, IngestPunches $ingest): JsonResponse
    {
        $agent = $request->agent() ?? abort(403);
        $timezone = (string) config('attendance.device_timezone');

        $bySource = [];

        foreach ((array) $request->validated('punches') as $punch) {
            $bySource[$punch['source'] ?? 'push'][] = [
                'sn' => (string) $punch['sn'],
                'pin' => (string) $punch['pin'],
                // Scanner time carries no zone; it is wall-clock time at the site.
                'scan_at' => CarbonImmutable::parse($punch['scan_at'], $timezone)->utc(),
                'verify_mode' => isset($punch['verify_mode']) ? (int) $punch['verify_mode'] : null,
                'inout_mode' => isset($punch['inout_mode']) ? (int) $punch['inout_mode'] : null,
            ];
        }

        $counts = ['accepted' => 0, 'duplicates' => 0, 'unmapped' => 0];

        foreach ($bySource as $source => $rows) {
            foreach ($ingest->handle($rows, (string) $source) as $key => $value) {
                $counts[$key] += $value;
            }
        }

        $agent->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'last_heartbeat_at' => $request->filled('heartbeat.listener_alive_at')
                ? CarbonImmutable::parse((string) $request->input('heartbeat.listener_alive_at'))->utc()
                : $agent->last_heartbeat_at,
            // Whatever was asked for has now been answered.
            'sync_requested_at' => null,
        ])->save();

        AttendanceIngestRequest::create([
            'attendance_agent_id' => $agent->id,
            'status' => AttendanceIngestRequest::OK,
            'rows' => array_sum(array_map('count', $bySource)),
            ...$counts,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $latest = AttendancePunch::acrossCompanies()->max('scan_at');

        return response()->json([
            ...$counts,
            'last_accepted_scan_at' => $latest === null
                ? null
                : CarbonImmutable::parse($latest, 'UTC')->setTimezone($timezone)->format('Y-m-d\TH:i:s'),
        ]);
    }

    /**
     * What the agent should do next. For now only "sync now" (ATT-DEV-003): an administrator asks
     * for it on the Data absensi page, and the agent finds out the next time it polls.
     */
    public function instructions(Request $request): JsonResponse
    {
        $agent = Auth::guard('sanctum')->user();

        abort_unless($agent instanceof AttendanceAgent, 403);

        $agent->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();

        return response()->json(['sync_now' => $agent->sync_requested_at !== null]);
    }
}
