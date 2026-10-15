/** One comment in a flow's thread (CORE-WFL-007). */
export type WorkflowDiscussionComment = {
    id: number;
    author: string | null;
    /** The author's role in the app, e.g. "Admin HR". */
    role: string | null;
    /** What they are to this case, e.g. "PEMOHON", "ATASAN", "MANAJEMEN". */
    badge: string | null;
    body: string;
    at: string;
    /** The stage the comment was written in, which it keeps as the case moves on. */
    stage: string;
    stageLabel: string;
    mine: boolean;
    /** Written by someone else since you last opened the discussion. */
    isNew: boolean;
};

export type WorkflowDiscussionData = {
    items: WorkflowDiscussionComment[];
    /** How many are new to you: the count on the button. */
    unread: number;
    /** The same count by stage: the "+n" on a stage tab. */
    unreadByStage: Record<string, number>;
};
