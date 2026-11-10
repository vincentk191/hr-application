<?php

namespace App\Http\Controllers;

use App\Actions\Recruitment\SubmitCandidateForm;
use App\Http\Requests\SaveRequisitionRequest;
use App\Http\Requests\SubmitCandidateFormRequest;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Support\Recruitment\InvitationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The candidate form (REC-INT): reached only from the personal link in an interview invitation, by someone who is not
 * signed in. The link is a long random token; what is kept is its hash. It opens only on the day of the interview, works
 * once, lapses at the end of that day, and shows nothing about anyone but the person it was made for.
 */
class CandidateFormController extends Controller
{
    public function show(string $token): Response
    {
        [$candidate, $requisition] = $this->find($token);

        if (! $candidate instanceof Candidate || ! $requisition instanceof Requisition) {
            return Inertia::render('candidate-form/show', ['state' => 'invalid']);
        }

        $state = match (true) {
            $candidate->form_submitted_at !== null => 'submitted',
            $requisition->stage()->isFinal(), $candidate->isRejected() => 'closed',
            $candidate->inviteState() === 'expired' => 'expired',
            $this->isEarly($candidate) => 'early',
            default => 'open',
        };

        $base = [
            'state' => $state,
            'positionName' => $requisition->position_name,
            'companyName' => $requisition->company?->name,
            'candidateName' => $candidate->name,
        ];

        if ($state === 'early') {
            return Inertia::render('candidate-form/show', [
                ...$base,
                'opensOn' => InvitationMessage::dayLabel($candidate->interview_at ?? now()),
            ]);
        }

        if ($state !== 'open') {
            return Inertia::render('candidate-form/show', $base);
        }

        $when = $candidate->interview_at;

        return Inertia::render('candidate-form/show', [
            ...$base,
            'interview' => $when === null ? null : [
                'when' => InvitationMessage::whenLabel($when),
                'mode' => $candidate->interview_mode === 'online' ? 'Daring' : 'Tatap muka',
                'place' => $candidate->interview_place,
            ],
            'token' => $token,
            'questions' => SubmitCandidateForm::questions($requisition),
            'today' => SaveRequisitionRequest::today()->toDateString(),
            'expiresOn' => $candidate->invite_expires_at === null ? null : InvitationMessage::dayLabel($candidate->invite_expires_at, false),
        ]);
    }

    public function store(SubmitCandidateFormRequest $request, string $token, SubmitCandidateForm $submit): RedirectResponse
    {
        [$candidate, $requisition] = $this->find($token);

        if (! $candidate instanceof Candidate
            || ! $requisition instanceof Requisition
            || $candidate->inviteState() !== 'invited'
            || $this->isEarly($candidate)
            || $candidate->isRejected()
            || $requisition->stage()->isFinal()) {
            return to_route('candidate-form.show', $token);
        }

        $answers = $request->answers();
        $missing = array_filter(
            SubmitCandidateForm::questions($requisition),
            fn (array $question): bool => ! isset($answers[$question['key']]),
        );

        if ($missing !== []) {
            throw ValidationException::withMessages(['answers' => 'Jawab semua pertanyaan dengan Ya atau Tidak.']);
        }

        $submit->handle($candidate, $requisition, $answers, $request->details());

        return to_route('candidate-form.show', $token);
    }

    /**
     * The link is not open yet: it opens on the morning of the interview day.
     */
    private function isEarly(Candidate $candidate): bool
    {
        $opens = $candidate->formOpensAt();

        return $opens !== null && now()->lt($opens);
    }

    /**
     * The candidate and requisition a link belongs to, across companies (nobody is signed in), or nulls when the link
     * is not one we issued, or it was replaced by a newer one.
     *
     * @return array{0: Candidate|null, 1: Requisition|null}
     */
    private function find(string $token): array
    {
        if (strlen($token) !== 48) {
            return [null, null];
        }

        $candidate = Candidate::acrossCompanies()->where('invite_token_hash', Candidate::hashToken($token))->first();
        $requisition = $candidate === null ? null : Requisition::acrossCompanies()->with('company:id,name')->find($candidate->requisition_id);

        return $requisition === null ? [null, null] : [$candidate, $requisition];
    }
}
