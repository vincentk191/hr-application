export type NotificationRow = {
    id: string;
    /** leave, attendance or document: what the notification is about. */
    kind: string;
    title: string;
    body: string;
    read: boolean;
    /** ISO time it was sent. */
    at: string | null;
    /** Opens it: marks it read, then goes where it points. */
    openUrl: string;
};

/** The header bell, shared on every page. */
export type NotificationSummary = {
    unread: number;
    recent: NotificationRow[];
};
