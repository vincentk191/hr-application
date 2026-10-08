import { Link } from '@inertiajs/react';
import { show } from '@/routes/employees';
import type { EmployeeTeam, TeamPerson } from '@/types/employee';

type Props = {
    team: EmployeeTeam;
    name: string;
    /** In the drawer a person opens in the drawer; on the profile page they link to their profile. */
    onOpen?: (id: number) => void;
};

function Person({
    person,
    highlight = false,
    onOpen,
}: {
    person: TeamPerson;
    highlight?: boolean;
    onOpen?: (id: number) => void;
}) {
    const className = `flex min-w-32 flex-col rounded-md border px-3 py-2 text-left text-sm hover:bg-muted/60 ${
        highlight ? 'border-primary bg-primary/5' : 'bg-card'
    }`;
    const body = (
        <>
            <span className="font-medium">{person.name}</span>
            <span className="text-xs text-muted-foreground">
                {person.position ?? '—'}
            </span>
        </>
    );

    return onOpen ? (
        <button
            type="button"
            className={className}
            onClick={() => onOpen(person.id)}
        >
            {body}
        </button>
    ) : (
        <Link href={show(person.id).url} className={className}>
            {body}
        </Link>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-t py-2 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

/**
 * The "Tim" tab (a slice of the reporting line): supervisor above, the people who share that
 * supervisor beside, and this person's own direct reports below, followed by who is in today.
 */
export function EmployeeTeamTab({ team, name, onOpen }: Props) {
    const teamNoun =
        team.basis === 'reports' ? 'bawahan langsung' : 'rekan tim';

    return (
        <div className="flex flex-col gap-4">
            <h3 className="text-sm font-medium tracking-wide uppercase">
                Posisi {name.split(' ')[0]}
            </h3>

            <div className="flex flex-col items-center gap-2">
                {team.grandSupervisor && (
                    <>
                        <Person person={team.grandSupervisor} onOpen={onOpen} />
                        <span className="h-3 w-px bg-border" />
                    </>
                )}
                {team.supervisor && (
                    <>
                        <Person person={team.supervisor} onOpen={onOpen} />
                        <span className="h-3 w-px bg-border" />
                    </>
                )}
                <div className="flex flex-wrap justify-center gap-2">
                    {team.peers.map((peer) => (
                        <Person
                            key={peer.id}
                            person={peer}
                            highlight={peer.isSelf}
                            onOpen={onOpen}
                        />
                    ))}
                </div>
                {team.reports.length > 0 && (
                    <>
                        <span className="h-3 w-px bg-border" />
                        <p className="text-xs text-muted-foreground">
                            Bawahan langsung
                        </p>
                        <div className="flex flex-wrap justify-center gap-2">
                            {team.reports.map((report) => (
                                <Person
                                    key={report.id}
                                    person={report}
                                    onOpen={onOpen}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>

            <dl>
                <Row label="Supervisor" value={team.supervisor?.name ?? '—'} />
                <Row label="Jumlah tim" value={`${team.size} orang`} />
                {team.onLeaveToday !== null && (
                    <>
                        <Row
                            label="Hadir hari ini"
                            value={
                                team.presentToday === null
                                    ? 'Data hari ini belum ada'
                                    : `${team.presentToday} dari ${team.size}`
                            }
                        />
                        <Row
                            label="Cuti hari ini"
                            value={
                                team.onLeaveToday.length === 0
                                    ? '—'
                                    : `${team.onLeaveToday.length} · ${team.onLeaveToday.join(', ')}`
                            }
                        />
                    </>
                )}
            </dl>

            <p className="text-xs text-muted-foreground">
                Hanya potongan: satu level ke atas dan rekan di bawah supervisor
                yang sama. Angka di atas menghitung {teamNoun}. Struktur lengkap
                ada di tampilan Chart.
            </p>
        </div>
    );
}
