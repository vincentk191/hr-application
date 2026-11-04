<?php

namespace App\Actions\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\RequisitionTemplate;

/**
 * The candidate fills in the form from their link (REC-INT): a yes or no on every line of the requisition's
 * checklist, and a few details. The checklist is stored as it read at that moment, and the form can be sent only once.
 */
class SubmitCandidateForm
{
    /**
     * The checklist as the form asks it: only the lines that are filled, each with a stable key.
     *
     * @return list<array{key: string, group: string, text: string}>
     */
    public static function questions(Requisition $requisition): array
    {
        $saved = $requisition->checklist ?? [];
        $questions = [];

        foreach (RequisitionTemplate::CHECKLIST as $key => $group) {
            foreach (RequisitionTemplate::clean($saved[$key] ?? []) as $index => $text) {
                $questions[] = ['key' => "{$key}_{$index}", 'group' => $group, 'text' => $text];
            }
        }

        return $questions;
    }

    /**
     * @param  array<string, string>  $answers  question key => "yes" or "no"
     * @param  array{expected_salary: int|null, available_from: string|null, message: string|null}  $details
     */
    public function handle(Candidate $candidate, Requisition $requisition, array $answers, array $details): void
    {
        $candidate->forceFill([
            'form_submitted_at' => now(),
            'form_data' => [
                'answers' => array_map(fn (array $question): array => [
                    'group' => $question['group'],
                    'text' => $question['text'],
                    'answer' => ($answers[$question['key']] ?? 'no') === 'yes',
                ], self::questions($requisition)),
                'expected_salary' => $details['expected_salary'],
                'available_from' => $details['available_from'],
                'message' => $details['message'],
            ],
        ])->save();

        app(Notifier::class)->candidateFormSubmitted($requisition, $candidate);
    }
}
