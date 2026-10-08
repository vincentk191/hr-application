import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb className="min-w-0">
                    <BreadcrumbList className="flex-nowrap">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={index}>
                                    {/* On a phone only the current page is named; the trail is for wider screens. */}
                                    <BreadcrumbItem
                                        className={
                                            isLast
                                                ? 'min-w-0'
                                                : 'hidden sm:inline-flex'
                                        }
                                    >
                                        {isLast ? (
                                            <BreadcrumbPage className="block truncate">
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && (
                                        <BreadcrumbSeparator className="hidden sm:block" />
                                    )}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
