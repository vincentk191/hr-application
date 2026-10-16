<?php

namespace App\Http\Controllers;

use App\Actions\Offboarding\ManageChecklist;
use App\Actions\Offboarding\ProceedToSignOff;
use App\Enums\OffboardingItemKind;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use App\Models\OffboardingItem;
use App\Workflows\Exceptions\TransitionBlocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The checklist stage of an offboarding case (OFF-ITM, OFF-CHK): each department's owner picks the
 * items that apply, confirms the list and ticks the items off; HR sets departments aside and, once
 * everything is done, moves the case on to sign-off.
 */
class OffboardingChecklistController extends Controller
{
    public function __construct(private ManageChecklist $checklist) {}

    /**
     * Tick one master item as applying to this case.
     */
    public function select(Request $request, OffboardingCase $offboardingCase, OffboardingCaseDepartment $group): RedirectResponse
    {
        $this->authorize('workGroup', $group);
        $this->belongs($offboardingCase, $group);

        $validated = $request->validate(['offboarding_item_id' => ['required', 'integer']]);
        $item = OffboardingItem::query()->find($validated['offboarding_item_id']);

        if (! $item instanceof OffboardingItem) {
            throw ValidationException::withMessages(['offboarding_item_id' => 'Item tidak ditemukan.']);
        }

        $this->checklist->select($group, $item, $request->user());

        return back();
    }

    /**
     * An item for this case only, in any department.
     */
    public function adhoc(Request $request, OffboardingCase $offboardingCase, OffboardingCaseDepartment $group): RedirectResponse
    {
        $this->authorize('addItem', $group);
        $this->belongs($offboardingCase, $group);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(OffboardingItemKind::class)],
        ], ['name.required' => 'Tulis nama item.']);

        $this->checklist->addAdhoc($group, $validated['name'], OffboardingItemKind::from($validated['kind']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item ditambahkan.']);

        return back();
    }

    public function remove(Request $request, OffboardingCaseItem $item): RedirectResponse
    {
        $this->authorize('removeItem', $item);

        $this->checklist->remove($item);

        return back();
    }

    /**
     * "Daftar selesai": the list is settled, even when nothing applies (OFF-CHK-008).
     */
    public function confirm(Request $request, OffboardingCase $offboardingCase, OffboardingCaseDepartment $group): RedirectResponse
    {
        $this->authorize('workGroup', $group);
        $this->belongs($offboardingCase, $group);

        $this->checklist->confirm($group, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Daftar {$group->name} dikonfirmasi."]);

        return back();
    }

    /**
     * HR sets a department aside (or takes it back) when nothing there concerns this leaver.
     */
    public function involvement(Request $request, OffboardingCase $offboardingCase, OffboardingCaseDepartment $group): RedirectResponse
    {
        $this->authorize('setAside', $group);
        $this->belongs($offboardingCase, $group);

        $this->checklist->setNotInvolved($group, $request->boolean('not_involved'), $request->user());

        return back();
    }

    /**
     * The owner ticks an item as done, with a note on the condition or what was handed over (OFF-CHK-003).
     */
    public function done(Request $request, OffboardingCaseItem $item): RedirectResponse
    {
        $this->authorize('workItem', $item);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $this->checklist->markDone($item, $validated['note'] ?? null, $request->user());

        return back();
    }

    public function reopen(Request $request, OffboardingCaseItem $item): RedirectResponse
    {
        $this->authorize('workItem', $item);

        $this->checklist->reopen($item);

        return back();
    }

    /**
     * "Lanjut ke sign-off": HR moves the case on, if the guard allows it (OFF-CHK-011).
     */
    public function proceed(Request $request, OffboardingCase $offboardingCase, ProceedToSignOff $signOff): RedirectResponse
    {
        $this->authorize('proceed', $offboardingCase);

        try {
            $signOff->handle($offboardingCase, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['proceed' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$offboardingCase->reference()} lanjut ke sign-off."]);

        return back();
    }

    /**
     * A group named in the address must be a group of the case named in it.
     */
    private function belongs(OffboardingCase $case, OffboardingCaseDepartment $group): void
    {
        abort_unless($group->offboarding_case_id === $case->id, 404);
    }
}
