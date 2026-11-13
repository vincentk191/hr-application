<?php

namespace App\Support\Recruitment;

/**
 * The `{placeholders}` in a text HR wrote in the admin panel (the interview invitation, the offer letter): filling them in
 * for one person, and finding the ones that are not real so a typo is caught when saving instead of reaching a candidate.
 */
final class PlaceholderText
{
    /**
     * Fill the placeholders in a text. A placeholder with no value becomes empty, and the blank lines that leaves are
     * collapsed so an absent note or paragraph does not leave a gap.
     *
     * @param  array<string, string>  $values
     */
    public static function fill(string $template, array $values): string
    {
        $text = preg_replace_callback('/\{([a-z_]+)\}/', fn (array $found): string => $values[$found[1]] ?? $found[0], $template) ?? $template;

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)) ?? $text);
    }

    /**
     * The `{names}` in a text that are not among the known placeholders.
     *
     * @param  list<string>  $known
     * @return list<string>
     */
    public static function unknown(string $text, array $known): array
    {
        preg_match_all('/\{([^{}\s]*)\}/', $text, $found);

        return array_values(array_unique(array_filter(
            $found[1],
            fn (string $name): bool => ! in_array($name, $known, true),
        )));
    }
}
