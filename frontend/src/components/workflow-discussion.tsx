import { Form, router, usePoll } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { WorkflowDiscussionData } from '@/types/workflow';

function initials(name: string | null): string {
    return (name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

/**
 * The comment thread of a flow, opened from a button at the top of the page and sliding in from the
 * side (CORE-WFL-007). It is the same thread through every stage: each comment keeps the stage it was
 * written in, and the button counts the comments that are new to you. Used by offboarding; any flow on
 * the workflow engine can use it by sending the same `discussion` prop.
 *
 * The page's `discussion` prop is polled while the page is open, so a comment from someone else shows
 * up without a reload. It is modal: the page behind it is dimmed and cannot be clicked, so the drawer is
 * closed (by its X, Escape, or a click outside it) before anything else is used.
 */
export function WorkflowDiscussion({
    discussion,
    reference,
    subject,
    currentStageLabel,
    canComment,
    postUrl,
    readUrl,
}: {
    discussion: WorkflowDiscussionData;
    /** "OFB-0040": the case the thread belongs to. */
    reference: string;
    /** Who or what the case is about, for the drawer's subtitle. */
    subject: string;
    /** The stage a new comment will be tagged with. */
    currentStageLabel: string;
    canComment: boolean;
    postUrl: string;
    readUrl: string;
}) {
    const [open, setOpen] = useState(false);
    const [stage, setStage] = useState<string>('all');
    const bottom = useRef<HTMLDivElement>(null);

    usePoll(30000, { only: ['discussion'] }, { keepAlive: false });

    // Opening the drawer, or a comment arriving while it is open, counts as having read it.
    useEffect(() => {
        if (open && discussion.unread > 0) {
            router.post(
                readUrl,
                {},
                {
                    only: ['discussion'],
                    preserveScroll: true,
                    preserveState: true,
                },
            );
        }
    }, [open, discussion.unread, readUrl]);

    useEffect(() => {
        if (open) {
            bottom.current?.scrollIntoView({ block: 'end' });
        }
    }, [open, discussion.items.length, stage]);

    const stages = Array.from(
        new Map(
            discussion.items.map((item) => [item.stage, item.stageLabel]),
        ).entries(),
    );
    const shown =
        stage === 'all'
            ? discussion.items
            : discussion.items.filter((item) => item.stage === stage);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    className="relative"
                    aria-label={`Komentar${discussion.unread > 0 ? `, ${discussion.unread} baru` : ''}`}
                    data-test="discussion-button"
                >
                    <MessageSquare />
                    {discussion.unread > 0 && (
                        <span
                            className="absolute -top-2 -right-2 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] leading-5 font-semibold text-primary-foreground"
                            data-test="discussion-unread"
                        >
                            {discussion.unread}
                        </span>
                    )}
                </Button>
            </SheetTrigger>

            <SheetContent
                side="right"
                className="w-full gap-0 sm:max-w-md"
                data-test="discussion-drawer"
            >
                <SheetHeader className="border-b p-5 pr-12">
                    <SheetTitle className="text-xl font-extrabold tracking-tight">
                        Komentar & feedback
                    </SheetTitle>
                    <SheetDescription className="label-mono">
                        {reference} · {subject} · {discussion.items.length}{' '}
                        komentar
                    </SheetDescription>
                    {stages.length > 1 && (
                        <div className="pill-tabs pt-2">
                            <button
                                type="button"
                                data-on={stage === 'all'}
                                onClick={() => setStage('all')}
                            >
                                Semua
                            </button>
                            {stages.map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    data-on={stage === value}
                                    onClick={() => setStage(value)}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    )}
                </SheetHeader>

                <div className="flex-1 overflow-y-auto">
                    {shown.length === 0 ? (
                        <p className="p-5 text-sm text-muted-foreground">
                            Belum ada komentar.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {shown.map((item) => (
                                <li
                                    key={item.id}
                                    className={`flex gap-3 p-5 ${item.isNew ? 'bg-primary/5' : ''}`}
                                    data-test="discussion-comment"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center border-2 border-foreground bg-card text-xs font-bold">
                                        {initials(item.author)}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            {item.author}
                                            {item.badge && (
                                                <span className="bg-foreground px-1.5 py-0.5 text-[10px] font-bold tracking-wider text-background">
                                                    {item.badge}
                                                </span>
                                            )}
                                            <span className="rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                {item.stageLabel}
                                            </span>
                                        </p>
                                        <p className="label-mono">
                                            {[item.role, item.at]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <p className="mt-2 text-sm whitespace-pre-line">
                                            {item.body}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div ref={bottom} />
                </div>

                {canComment && (
                    <Form
                        action={postUrl}
                        method="post"
                        resetOnSuccess
                        options={{ preserveScroll: true, preserveState: true }}
                        className="grid gap-2 border-t p-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <textarea
                                    name="body"
                                    rows={3}
                                    placeholder="Tulis komentar…"
                                    className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.body} />
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Ditandai tahap {currentStageLabel}
                                    </span>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                        data-test="discussion-send"
                                    >
                                        Kirim komentar
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </SheetContent>
        </Sheet>
    );
}
