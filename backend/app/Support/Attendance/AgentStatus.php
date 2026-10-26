<?php

namespace App\Support\Attendance;

use App\Models\AttendanceAgent;
use App\Models\AttendanceIngestRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Whether the sync agents are reporting in (ATT-DEV-001).
 *
 * An agent is stale when it has not completed a request for longer than STALE_AFTER_HOURS during
 * working hours. Outside working hours silence is normal — nobody is scanning — so no alarm is
 * raised then. With no agent configured there is nothing to alarm about: the manual import is the
 * source, and CaptureStatus covers how current that is.
 */
class AgentStatus
{
    public const STALE_AFTER_HOURS = 3;

    public function __construct(private WorkSchedule $schedule) {}

    /**
     * @return array<int, array{
     *     id: int, name: string, lastRunAt: string|null, lastSeenAt: string|null,
     *     lastHeartbeatAt: string|null, lastIp: string|null, stale: bool, syncRequested: bool
     * }>
     */
    public function all(?CarbonInterface $now = null): array
    {
        $timezone = (string) config('attendance.device_timezone');
        $now = CarbonImmutable::instance($now ?? now())->setTimezone($timezone);

        $lastRuns = AttendanceIngestRequest::query()
            ->where('status', AttendanceIngestRequest::OK)
            ->selectRaw('attendance_agent_id, max(created_at) as last_run')
            ->groupBy('attendance_agent_id')
            ->pluck('last_run', 'attendance_agent_id');

        return AttendanceAgent::query()->orderBy('name')->get()->map(function (AttendanceAgent $agent) use ($lastRuns, $now, $timezone): array {
            $lastRun = isset($lastRuns[$agent->id])
                ? CarbonImmutable::parse((string) $lastRuns[$agent->id], 'UTC')->setTimezone($timezone)
                : null;

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'lastRunAt' => $lastRun?->toIso8601String(),
                'lastSeenAt' => $agent->last_seen_at?->setTimezone($timezone)->toIso8601String(),
                'lastHeartbeatAt' => $agent->last_heartbeat_at?->setTimezone($timezone)->toIso8601String(),
                'lastIp' => $agent->last_ip,
                'stale' => $this->isStale($lastRun, $now),
                'syncRequested' => $agent->sync_requested_at !== null,
            ];
        })->all();
    }

    /**
     * Whether any agent is stale right now; what the dashboard asks.
     */
    public function anyStale(?CarbonInterface $now = null): bool
    {
        return collect($this->all($now))->contains('stale', true);
    }

    private function isStale(?CarbonImmutable $lastRun, CarbonImmutable $now): bool
    {
        if (! $this->inWorkingHours($now)) {
            return false;
        }

        return $lastRun === null || $lastRun->lt($now->subHours(self::STALE_AFTER_HOURS));
    }

    private function inWorkingHours(CarbonImmutable $now): bool
    {
        $time = $now->format('H:i');

        return $this->schedule->isWorkday($now) && $time >= $this->schedule->start && $time < $this->schedule->endFor($now);
    }
}
