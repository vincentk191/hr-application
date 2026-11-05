/** How a leave request's stage is painted as a badge: approved solid, rejected red, anything still open quiet. */
export function stageVariant(
    stage: string | null | undefined,
): 'default' | 'destructive' | 'secondary' {
    if (stage === 'approved') {
        return 'default';
    }

    if (stage === 'rejected') {
        return 'destructive';
    }

    return 'secondary';
}
