import { Fragment } from 'react';

/** A web address: with http(s), or a bare Google Meet one, which is how people often paste it. */
const ADDRESS =
    /(https?:\/\/[^\s<>"']+|(?:www\.)?meet\.google\.com\/[^\s<>"']+)/gi;

/** Sentence punctuation that follows an address but is not part of it. */
const TRAILING = /[.,;:!?)\]]+$/;

/** The first web address in a text, ready to open (a bare Meet address gets https), or null when there is none. */
export function firstAddress(text: string | null): string | null {
    const found = text?.split(ADDRESS)[1];

    if (!found) {
        return null;
    }

    const address = found.replace(TRAILING, '');

    return /^https?:\/\//i.test(address) ? address : `https://${address}`;
}

/**
 * Text in which every web address is a link that opens in a new tab (an interview's place, which for an online interview
 * is the Meet link). Only http and https are linked, so nothing like a `javascript:` address becomes clickable.
 */
export function LinkifiedText({
    text,
    className = 'font-medium text-primary underline underline-offset-2 hover:no-underline',
}: {
    text: string;
    className?: string;
}) {
    return (
        <>
            {text.split(ADDRESS).map((part, index) => {
                // split() with a capture group puts the addresses at the odd positions.
                if (index % 2 === 0) {
                    return <Fragment key={index}>{part}</Fragment>;
                }

                const trailing = part.match(TRAILING)?.[0] ?? '';
                const address = trailing
                    ? part.slice(0, -trailing.length)
                    : part;
                const href = /^https?:\/\//i.test(address)
                    ? address
                    : `https://${address}`;

                return (
                    <Fragment key={index}>
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={className}
                            data-test="place-link"
                        >
                            {address}
                        </a>
                        {trailing}
                    </Fragment>
                );
            })}
        </>
    );
}
