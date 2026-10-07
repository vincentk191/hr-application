import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/active-company';

/**
 * The company switcher in the dashboard header, present on every page (CORE-NAV-001).
 *
 * A parent-wide role can pick one of the group's companies or view them combined
 * (CORE-NAV-003). An account pinned to one company gets a static label instead of a control
 * (CORE-NAV-004) — that is a presentation choice only; the scoping itself is enforced
 * server-side, so rendering a control here would not have granted any extra access.
 */
const COMBINED = 'all';

export function CompanySwitcher() {
    const { companySwitcher } = usePage().props;

    if (!companySwitcher || companySwitcher.companies.length === 0) {
        return null;
    }

    const { companies, selected, canCombine, locked } = companySwitcher;

    if (locked) {
        const company = companies.find(
            (option) => String(option.id) === selected,
        );

        return (
            <div
                className="flex items-center gap-2 text-sm font-medium"
                data-testid="company-switcher-locked"
            >
                <Building2 className="size-4 text-muted-foreground" />
                <span>{company?.name ?? companies[0].name}</span>
            </div>
        );
    }

    const handleChange = (value: string) => {
        if (value === selected) {
            return;
        }

        // Only the selection is sent; the server resolves the active company from it on every
        // later request, so the switcher cannot widen what this account may see.
        router.put(
            update().url,
            { company: value },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <Select value={selected} onValueChange={handleChange}>
            <SelectTrigger
                className="w-[200px]"
                aria-label="Pilih perusahaan"
                data-testid="company-switcher"
            >
                <Building2 className="size-4 text-muted-foreground" />
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {canCombine && (
                    <SelectItem value={COMBINED}>Semua perusahaan</SelectItem>
                )}
                {companies.map((company) => (
                    <SelectItem key={company.id} value={String(company.id)}>
                        {company.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
