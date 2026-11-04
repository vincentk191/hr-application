import type {
    ChecklistKey,
    Requisition,
    ResponsibilityGroup,
} from '@/types/recruitment';

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

function bullets(lines: string[]): string[] {
    return lines
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .map((line) => `- ${line}`);
}

function groups(responsibilities: ResponsibilityGroup[]): string[] {
    return responsibilities
        .filter(
            (group) =>
                group.title.trim() !== '' || bullets(group.items).length > 0,
        )
        .flatMap((group, position) => [
            `${LETTERS[position] ?? '•'}. ${group.title.trim() || '(tanpa judul)'}`,
            ...bullets(group.items),
        ]);
}

const CHECKLIST: {
    title: string;
    keys: { key: ChecklistKey; label: string }[];
}[] = [
    {
        title: 'Kualifikasi',
        keys: [
            { key: 'education', label: 'Pendidikan' },
            { key: 'experience', label: 'Pengalaman' },
        ],
    },
    {
        title: 'Skills',
        keys: [
            { key: 'hard', label: 'Hard Skills' },
            { key: 'soft', label: 'Soft Skills' },
        ],
    },
    { title: 'Nilai Plus', keys: [{ key: 'bonus', label: 'Nilai Plus' }] },
];

/** The job title as it is typed into a platform's "Job title" box. */
export function postingTitle(item: Requisition): string {
    return item.positionName;
}

/** Tentang Posisi, Tanggung Jawab Utama and Benefit & Fasilitas: the body of the listing. */
export function postingDescription(item: Requisition): string {
    const blocks: string[][] = [];

    if ((item.about ?? '').trim() !== '') {
        blocks.push(['Tentang Posisi', (item.about ?? '').trim()]);
    }

    const duties = groups(item.responsibilities);

    if (duties.length > 0) {
        blocks.push(['Tanggung Jawab Utama', ...duties]);
    }

    const benefits = bullets(item.benefits);

    if (benefits.length > 0) {
        blocks.push(['Benefit & Fasilitas', ...benefits]);
    }

    return blocks.map((block) => block.join('\n')).join('\n\n');
}

/** Kualifikasi, Skills and Nilai Plus: what the listing asks of a candidate. */
export function postingRequirements(item: Requisition): string {
    const blocks: string[][] = [];

    for (const section of CHECKLIST) {
        const lines: string[] = [];

        for (const { key, label } of section.keys) {
            const items = bullets(item.checklist[key]);

            if (items.length === 0) {
                continue;
            }

            // A section with a single subtitle (Nilai Plus) needs no second heading.
            lines.push(
                ...(section.keys.length > 1 ? [`${label}:`] : []),
                ...items,
            );
        }

        if (lines.length > 0) {
            blocks.push([section.title, ...lines]);
        }
    }

    return blocks.map((block) => block.join('\n')).join('\n\n');
}

/** The whole listing in one piece: title, description, requirements. */
export function postingAll(item: Requisition): string {
    return [
        postingTitle(item),
        postingDescription(item),
        postingRequirements(item),
    ]
        .filter((part) => part.trim() !== '')
        .join('\n\n');
}
