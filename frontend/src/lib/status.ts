/**
 * What a record is up to, as one of seven tones that every screen shares (UI/UX audit UX-04). The colours come from the
 * status tokens in css/app.css; StatusPill draws them with an icon so colour is never the only signal.
 *
 * - neutral: not started, a draft
 * - info: active, in progress
 * - pending: waiting on someone
 * - caution: waiting longer than it should
 * - success: done, approved, good
 * - danger: rejected, overdue, a problem
 * - void: cancelled, replaced, no longer counts
 */
export type StatusTone =
    | 'neutral'
    | 'info'
    | 'pending'
    | 'caution'
    | 'success'
    | 'danger'
    | 'void';

/** A decision on its way: a leave request, one approver's step, a profile change. Approved is done, rejected a problem, the rest waits. */
export function decisionTone(stage: string | null | undefined): StatusTone {
    if (stage === 'approved') {
        return 'success';
    }

    if (stage === 'rejected') {
        return 'danger';
    }

    return 'pending';
}

/** An offboarding case along its pipeline. */
export function offboardingStageTone(stage: string): StatusTone {
    switch (stage) {
        case 'draft':
            return 'neutral';
        case 'submitted':
        case 'sign_off':
            return 'pending';
        case 'checklist':
            return 'info';
        case 'closed':
            return 'success';
        default:
            return 'void';
    }
}

/** One department's part of an offboarding checklist. */
export function checklistGroupTone(status: string): StatusTone {
    switch (status) {
        case 'choosing':
            return 'neutral';
        case 'review':
            return 'pending';
        case 'running':
            return 'info';
        case 'done':
            return 'success';
        default:
            return 'void';
    }
}

/** A recruitment offer along its way. */
export function offerStageTone(stage: string): StatusTone {
    switch (stage) {
        case 'proposed':
            return 'pending';
        case 'sent':
            return 'info';
        case 'approved':
        case 'accepted':
            return 'success';
        case 'rejected':
        case 'declined':
            return 'danger';
        default:
            return 'void';
    }
}

/** A recruitment candidate's interview invitation. */
export function inviteStateTone(state: string): StatusTone {
    switch (state) {
        case 'invited':
            return 'pending';
        case 'expired':
            return 'danger';
        case 'submitted':
            return 'success';
        default:
            return 'neutral';
    }
}

/** A rekapitulasi file: a draft is not in force yet, a replaced one no longer counts. */
export function rekapStatusTone(status: string): StatusTone {
    switch (status) {
        case 'draft':
            return 'pending';
        case 'confirmed':
            return 'success';
        default:
            return 'void';
    }
}

/** How long something has waited: it warms up so the oldest stand out. */
export function ageTone(days: number): StatusTone {
    if (days >= 7) {
        return 'danger';
    }

    if (days >= 3) {
        return 'caution';
    }

    return 'pending';
}
