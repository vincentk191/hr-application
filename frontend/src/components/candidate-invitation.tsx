import { useState } from "react";
import { CopyButton } from "@/components/requisition-posting";
import { shortDate } from "@/components/requisition-summary";
import type { Candidate } from "@/types/recruitment";

/** Where a candidate's invitation stands, in words and in the badge's colour. */
export const INVITE_STATE: Record<
    Candidate["inviteState"],
    {
        label: string;
        /** The same in a few words, for a badge with room for little. */
        shortLabel: string;
        variant: "secondary" | "default" | "outline" | "destructive";
    }
> = {
    none: {
        label: "Belum diundang",
        shortLabel: "Belum diundang",
        variant: "secondary",
    },
    invited: {
        label: "Diundang · menunggu formulir",
        shortLabel: "Menunggu formulir",
        variant: "outline",
    },
    expired: {
        label: "Tautan kedaluwarsa",
        shortLabel: "Tautan kedaluwarsa",
        variant: "destructive",
    },
    submitted: {
        label: "Formulir terkirim",
        shortLabel: "Formulir terkirim",
        variant: "default",
    },
};

export function modeLabel(mode: Candidate["interviewMode"]): string {
    return mode === "online" ? "Daring" : "Tatap muka";
}

/** "2026-11-06T09:02" (the company's time) as "6 Nov 2026, 09:02 WIB". */
function sentLabel(sentAt: string | null, sentOn: string | null): string {
    const found = sentAt?.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2})$/);

    return found
        ? `${shortDate(`${found[1]}-${found[2]}-${found[3]}`)}, ${found[4]} WIB`
        : shortDate(sentOn);
}

/**
 * The invitation as it was sent, laid out like the email: who it went to, when and how, the subject, and the text folded
 * to its first lines until HR asks for all of it. The personal link is left out, since it is shown only when the
 * invitation is made.
 */
export function InvitationEmail({ candidate }: { candidate: Candidate }) {
    const sent = candidate.invitation;
    const [expanded, setExpanded] = useState(false);

    if (sent === null) {
        return null;
    }

    const rows: { label: string; value: string; strong?: boolean }[] = [
        ...(sent.emailed && candidate.email
            ? [{ label: "Kepada", value: candidate.email }]
            : []),
        {
            label: "Dikirim",
            value: `${sentLabel(sent.sentAt, sent.sentOn)} · ${
                sent.emailed ? "lewat email" : "disalin untuk WhatsApp"
            }`,
        },
        ...(sent.subject
            ? [{ label: "Subjek", value: sent.subject, strong: true }]
            : []),
    ];

    return (
        <section className="space-y-2" data-test="invitation-preview">
            <header className="flex items-center justify-between gap-3">
                <h3 className="label-mono">
                    {sent.emailed ? "Email undangan" : "Pesan undangan"}
                </h3>
                <CopyButton
                    variant="ghost"
                    label="Salin teks"
                    text={sent.message}
                    testId="copy-sent-invitation"
                />
            </header>

            <div className="overflow-hidden rounded-2xl border">
                <dl className="grid grid-cols-[5rem_1fr] gap-x-3 gap-y-2 p-4 text-sm">
                    {rows.map((row) => (
                        <div key={row.label} className="contents">
                            <dt className="text-muted-foreground">
                                {row.label}
                            </dt>
                            <dd
                                className={`break-words ${row.strong ? "font-semibold" : "font-medium"}`}
                            >
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div
                    className={`relative border-t px-4 py-4 ${expanded ? "" : "max-h-44 overflow-hidden"}`}
                >
                    <pre className="font-sans text-sm leading-relaxed whitespace-pre-wrap">
                        {sent.message}
                    </pre>
                    {!expanded && (
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-background to-transparent"
                        />
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => setExpanded((open) => !open)}
                    aria-expanded={expanded}
                    className="w-full border-t px-4 py-3 text-left text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:bg-muted/40 focus-visible:outline-none"
                    data-test="toggle-invitation-email"
                >
                    {expanded ? "Sembunyikan email" : "Tampilkan email lengkap"}
                </button>
            </div>
        </section>
    );
}
