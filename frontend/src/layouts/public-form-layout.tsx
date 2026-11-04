import type { ReactNode } from 'react';

/**
 * The layout of pages opened by someone who is not signed in and not an employee, from a link (the candidate form).
 * No menu and no sign-in link: just the page, centred, so a link opened on a phone reads as one simple form.
 */
export default function PublicFormLayout({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-background">
            <main className="mx-auto w-full max-w-2xl px-4 py-8 sm:py-12">
                {children}
            </main>
        </div>
    );
}
