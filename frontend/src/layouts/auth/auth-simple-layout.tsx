import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * The signed-out screens: a black panel with the product's name and what it is for, and the form on
 * the right. On a phone the panel is just the name above the form.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-2">
            <aside className="hidden flex-col justify-between bg-black p-12 text-white lg:flex">
                <Link
                    href={home()}
                    className="text-lg font-extrabold tracking-tight uppercase"
                >
                    HR Dashboard
                </Link>
                <h2 className="max-w-lg text-6xl leading-[1.02] font-extrabold tracking-tight xl:text-7xl">
                    Absensi, cuti, dan data karyawan di satu tempat.
                </h2>
                <p className="font-mono text-[11px] tracking-[0.14em] text-white/60 uppercase">
                    V1 · Internal
                </p>
            </aside>

            <main className="auth-form flex items-center px-6 py-12 sm:px-16">
                <div className="mx-auto w-full max-w-md">
                    <Link
                        href={home()}
                        className="mb-10 block text-lg font-extrabold tracking-tight uppercase lg:hidden"
                    >
                        HR Dashboard
                    </Link>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                    <div className="mt-8">{children}</div>
                </div>
            </main>
        </div>
    );
}
