<?php

namespace App\Http\Requests;

use App\Models\AttendanceAgent;
use App\Models\AttendanceIngestRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A batch of punches pushed by the sync agent. The whole batch is refused if any row is malformed
 * (ATTENDANCE-SYNC.md, Part F): the agent only advances its position on a success, so a partly
 * applied batch would leave it unable to tell what was kept.
 */
class IngestPunchesRequest extends FormRequest
{
    public const MAX_BATCH = 1000;

    /**
     * Authentication and the token's ability are enforced by the route middleware.
     */
    public function authorize(): bool
    {
        return $this->agent() instanceof AttendanceAgent;
    }

    /**
     * The agent making the request. Read through the Sanctum guard, because this is a token
     * holder that is not a User.
     */
    public function agent(): ?AttendanceAgent
    {
        $agent = Auth::guard('sanctum')->user();

        return $agent instanceof AttendanceAgent ? $agent : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // An empty list is allowed: a run with nothing new still reports in, which is what the
            // staleness alarm listens for.
            'punches' => ['present', 'array', 'max:'.self::MAX_BATCH],
            'punches.*.sn' => ['required', 'max:100'],
            'punches.*.pin' => ['required', 'max:50'],
            'punches.*.scan_at' => ['required', 'date_format:Y-m-d\TH:i:s,Y-m-d H:i:s'],
            'punches.*.verify_mode' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'punches.*.inout_mode' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'punches.*.source' => ['nullable', Rule::in(['push', 'sweep'])],
            'heartbeat.listener_alive_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Keep a record of a refused batch before the framework answers 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        $agent = $this->agent();

        AttendanceIngestRequest::create([
            'attendance_agent_id' => $agent instanceof AttendanceAgent ? $agent->id : null,
            'status' => AttendanceIngestRequest::REJECTED,
            'rows' => is_array($this->input('punches')) ? count($this->input('punches')) : 0,
            'message' => mb_substr((string) $validator->errors()->first(), 0, 250),
            'ip' => $this->ip(),
            'created_at' => now(),
        ]);

        parent::failedValidation($validator);
    }
}
