import { shortDate } from '@/components/requisition-summary';
import type { Candidate } from '@/types/recruitment';

/** Where a candidate's invitation stands, in words and in the badge's colour. */
export const INVITE_STATE: Record<
    Candidate['inviteState'],
    {
        label: string;
        variant: 'secondary' | 'default' | 'outline' | 'destructive';
    }
> = {
    none: { label: 'Belum diundang', variant: 'secondary' },
    invited: { label: 'Diundang · menunggu formulir', variant: 'outline' },
    expired: { label: 'Tautan kedaluwarsa', variant: 'destructive' },
    submitted: { label: 'Formulir terkirim', variant: 'default' },
};

export function modeLabel(mode: Candidate['interviewMode']): string {
    return mode === 'online' ? 'Daring' : 'Tatap muka';
}

/**
 * The invitation as it was sent, folded under one line: the subject, whether it went by email, and the text (with the
 * personal link left out, since that is shown only when the invitation is made).
 */
export function InvitationPreview({ candidate }: { candidate: Candidate }) {
    const sent = candidate.invitation;

    if (sent === null) {
        return null;
    }

    return (
        <details
            className="rounded-xl border p-3 text-sm"
            data-test="invitation-preview"
        >
            <summary className="cursor-pointer font-medium">
                Undangan terkirim
                {sent.sentOn ? ` · ${shortDate(sent.sentOn)}` : ''} ·{' '}
                {sent.emailed ? 'lewat email' : 'disalin untuk WhatsApp'}
            </summary>
            {sent.subject && (
                <p className="mt-2 font-semibold">{sent.subject}</p>
            )}
            <pre className="mt-2 font-sans text-sm whitespace-pre-wrap text-muted-foreground">
                {sent.message}
            </pre>
        </details>
    );
}
