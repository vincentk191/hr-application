import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import type {
    ChecklistKey,
    RequisitionContent,
    ResponsibilityGroup,
} from '@/types/recruitment';

/** The lines in one list, blanks not counted: what the review calls a "field". */
export function lineCount(lines: string[]): number {
    return lines.filter((line) => line.trim() !== '').length;
}

/** The subtitles that hold something: a title or a line. A blank row the requester left open is not a section. */
export function filledGroups(
    groups: ResponsibilityGroup[],
): ResponsibilityGroup[] {
    return groups.filter(
        (group) => group.title.trim() !== '' || lineCount(group.items) > 0,
    );
}

/** The posting's lines and subtitles, as the review counts them. */
export function postingCounts(content: RequisitionContent) {
    const groups = filledGroups(content.responsibilities);
    const responsibilityLines = groups.reduce(
        (sum, group) => sum + lineCount(group.items),
        0,
    );

    return {
        subtitles: groups.length,
        responsibilityLines,
        benefitLines: lineCount(content.benefits),
        fields:
            (content.about.trim() === '' ? 0 : 1) +
            responsibilityLines +
            lineCount(content.benefits),
    };
}

/** Lines in the checklist's required sections and in all of it. */
export function checklistCounts(checklist: RequisitionContent['checklist']) {
    const sum = (keys: ChecklistKey[]) =>
        keys.reduce((total, key) => total + lineCount(checklist[key]), 0);

    return {
        qualification: sum(['education', 'experience']),
        skills: sum(['hard', 'soft']),
        bonus: sum(['bonus']),
        all: sum(['education', 'experience', 'hard', 'soft', 'bonus']),
    };
}

/** "1 Okt 2026", the way the design writes a date. */
export function shortDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

/** The required fields of the request that have a value: what the review counts as "N FIELD" on Permintaan. */
function requestFields(data: RequisitionSummaryData): number {
    return [
        data.positionName,
        data.department,
        data.quota,
        data.contractLabel,
        data.targetStartOn,
        data.manager,
        data.reason,
    ].filter((value) => value !== null && `${value}`.trim() !== '').length;
}

function Block({
    number,
    title,
    meta,
    onEdit,
    children,
}: {
    number: string;
    title: string;
    meta?: string;
    onEdit?: () => void;
    children: ReactNode;
}) {
    return (
        <section className="surface overflow-hidden">
            <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                <h2 className="flex items-baseline gap-3 text-lg font-extrabold tracking-tight">
                    <span className="label-mono">{number}</span>
                    {title}
                </h2>
                <div className="flex items-center gap-3">
                    {meta && <span className="label-mono">{meta}</span>}
                    {onEdit && (
                        <Button
                            type="button"
                            variant="link"
                            className="h-auto p-0 font-bold"
                            onClick={onEdit}
                        >
                            UBAH
                        </Button>
                    )}
                </div>
            </header>
            {children}
        </section>
    );
}

function Cell({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="border-t p-4 first:border-t-0 sm:[&:nth-child(-n+2)]:border-t-0 md:[&:nth-child(-n+3)]:border-t-0">
            <dt className="label-mono">{label}</dt>
            <dd className="mt-1 text-sm font-medium">{children}</dd>
        </div>
    );
}

function Lines({ lines }: { lines: string[] }) {
    return (
        <ul className="space-y-2 text-sm">
            {lines
                .filter((line) => line.trim() !== '')
                .map((line, position) => (
                    <li key={position} className="flex gap-3">
                        <span className="text-muted-foreground">—</span>
                        <span>{line}</span>
                    </li>
                ))}
        </ul>
    );
}

function SubtitleHeading({
    letter,
    title,
    count,
}: {
    letter?: string;
    title: string;
    count: number;
}) {
    return (
        <div className="mb-2 flex items-baseline justify-between gap-3">
            <p className="font-bold">
                {letter && <span className="label-mono mr-2">{letter} ·</span>}
                {title}
            </p>
            <span className="label-mono">{count} FIELD</span>
        </div>
    );
}

/** Everything the requester asked for, in the review's own layout (REC-WZ3-001): Permintaan, Isi lowongan, Candidate checklist. */
export type RequisitionSummaryData = RequisitionContent & {
    positionName: string;
    department: string | null;
    branch: string | null;
    quota: number | null;
    contractLabel: string | null;
    targetStartOn: string | null;
    manager: string | null;
    reason: string | null;
};

const CHECKLIST_GROUPS: {
    title: string;
    required: boolean;
    keys: { key: ChecklistKey; label: string }[];
}[] = [
    {
        title: 'Kualifikasi',
        required: true,
        keys: [
            { key: 'education', label: 'Pendidikan' },
            { key: 'experience', label: 'Pengalaman' },
        ],
    },
    {
        title: 'Skills',
        required: true,
        keys: [
            { key: 'hard', label: 'Hard Skills' },
            { key: 'soft', label: 'Soft Skills' },
        ],
    },
    {
        title: 'Nilai Plus',
        required: false,
        keys: [{ key: 'bonus', label: 'Nilai Plus' }],
    },
];

export function RequisitionSummary({
    data,
    onEdit,
}: {
    data: RequisitionSummaryData;
    /** Given in the wizard, where each block links back to its step; left out on the pipeline page. */
    onEdit?: (step: 1 | 2) => void;
}) {
    const groups = filledGroups(data.responsibilities);
    const posting = postingCounts(data);
    const checklist = checklistCounts(data.checklist);

    return (
        <div className="space-y-5" data-test="requisition-review">
            <Block
                number="01"
                title="Permintaan"
                meta={`${requestFields(data)} FIELD`}
                onEdit={onEdit && (() => onEdit(1))}
            >
                <dl className="grid sm:grid-cols-2 md:grid-cols-3">
                    <Cell label="Posisi">{data.positionName || '—'}</Cell>
                    <Cell label="Departemen">
                        {data.department ?? '—'}
                        {data.branch && (
                            <span className="block text-xs font-normal text-muted-foreground">
                                {data.branch}
                            </span>
                        )}
                    </Cell>
                    <Cell label="Jumlah kuota">
                        {data.quota === null ? '—' : `${data.quota} orang`}
                    </Cell>
                    <Cell label="Tipe kontrak">
                        {data.contractLabel ?? '—'}
                    </Cell>
                    <Cell label="Target mulai kerja">
                        {shortDate(data.targetStartOn)}
                    </Cell>
                    <Cell label="Atasan langsung">{data.manager ?? '—'}</Cell>
                </dl>
                <div className="border-t p-4">
                    <p className="label-mono">Alasan permintaan</p>
                    <p className="mt-1 text-sm whitespace-pre-line">
                        {data.reason || '—'}
                    </p>
                </div>
            </Block>

            <div className="grid items-start gap-5 lg:grid-cols-2">
                <Block
                    number="02"
                    title="Isi lowongan"
                    meta={`3 BAGIAN · ${posting.fields} FIELD`}
                    onEdit={onEdit && (() => onEdit(2))}
                >
                    <div className="divide-y">
                        <div className="p-4">
                            <div className="mb-2 flex items-baseline justify-between gap-3">
                                <p className="font-bold">Tentang Posisi</p>
                                <span className="label-mono">1 PARAGRAF</span>
                            </div>
                            <p className="text-sm whitespace-pre-line">
                                {data.about.trim() || '—'}
                            </p>
                        </div>
                        <div className="space-y-4 p-4">
                            <div className="flex items-baseline justify-between gap-3">
                                <p className="font-bold">
                                    Tanggung Jawab Utama
                                </p>
                                <span className="label-mono">
                                    {posting.subtitles} SUBTITLE ·{' '}
                                    {posting.responsibilityLines} FIELD
                                </span>
                            </div>
                            {groups.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    —
                                </p>
                            ) : (
                                groups.map((group, position) => (
                                    <div key={position}>
                                        <SubtitleHeading
                                            letter={LETTERS[position]}
                                            title={
                                                group.title || '(tanpa judul)'
                                            }
                                            count={lineCount(group.items)}
                                        />
                                        <Lines lines={group.items} />
                                    </div>
                                ))
                            )}
                        </div>
                        <div className="p-4">
                            <SubtitleHeading
                                title="Benefit & Fasilitas"
                                count={posting.benefitLines}
                            />
                            {posting.benefitLines === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    —
                                </p>
                            ) : (
                                <Lines lines={data.benefits} />
                            )}
                        </div>
                    </div>
                </Block>

                <Block
                    number="03"
                    title="Candidate checklist"
                    meta={`${checklist.all} FIELD`}
                    onEdit={onEdit && (() => onEdit(2))}
                >
                    <div className="divide-y">
                        {CHECKLIST_GROUPS.map((group) => {
                            const total = group.keys.reduce(
                                (sum, { key }) =>
                                    sum + lineCount(data.checklist[key]),
                                0,
                            );

                            return (
                                <div
                                    key={group.title}
                                    className="space-y-3 p-4"
                                >
                                    <div className="flex items-baseline justify-between gap-3">
                                        <p className="font-bold">
                                            {group.title}
                                        </p>
                                        <span className="label-mono">
                                            {group.required
                                                ? 'WAJIB'
                                                : 'TIDAK WAJIB'}{' '}
                                            · {total} FIELD
                                        </span>
                                    </div>
                                    {group.keys.length === 1 ? (
                                        total === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                —
                                            </p>
                                        ) : (
                                            <Lines
                                                lines={
                                                    data.checklist[
                                                        group.keys[0].key
                                                    ]
                                                }
                                            />
                                        )
                                    ) : (
                                        group.keys.map(
                                            ({ key, label }, position) => (
                                                <div key={key}>
                                                    <SubtitleHeading
                                                        letter={
                                                            LETTERS[position]
                                                        }
                                                        title={label}
                                                        count={lineCount(
                                                            data.checklist[key],
                                                        )}
                                                    />
                                                    {lineCount(
                                                        data.checklist[key],
                                                    ) === 0 ? (
                                                        <p className="text-sm text-muted-foreground">
                                                            —
                                                        </p>
                                                    ) : (
                                                        <Lines
                                                            lines={
                                                                data.checklist[
                                                                    key
                                                                ]
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            ),
                                        )
                                    )}
                                </div>
                            );
                        })}
                        <p className="label-mono bg-muted/40 p-4">
                            Tidak ada bobot dan tingkat — kandidat menjawab ya /
                            tidak per field
                        </p>
                    </div>
                </Block>
            </div>
        </div>
    );
}
