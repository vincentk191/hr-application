import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/candidate-form';

type Question = { key: string; group: string; text: string };

type Props = {
    state: 'open' | 'early' | 'submitted' | 'expired' | 'closed' | 'invalid';
    /** For a link opened before the interview day: the day it opens. */
    opensOn?: string;
    positionName?: string;
    companyName?: string | null;
    candidateName?: string;
    token?: string;
    interview?: { when: string; mode: string; place: string | null } | null;
    questions?: Question[];
    today?: string;
    expiresOn?: string | null;
};

/** A short message page for a link that cannot be used (or has been, already). */
function Notice({ title, children }: { title: string; children: string }) {
    return (
        <div className="space-y-2" data-test="candidate-form-notice">
            <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
            <p className="text-muted-foreground">{children}</p>
        </div>
    );
}

/** The questions, one block per heading of the checklist, each answered Ya or Tidak. */
function Questions({
    questions,
    error,
}: {
    questions: Question[];
    error?: string;
}) {
    const groups = questions.reduce<Record<string, Question[]>>(
        (carry, question) => ({
            ...carry,
            [question.group]: [...(carry[question.group] ?? []), question],
        }),
        {},
    );

    return (
        <section className="space-y-4" data-test="questions">
            <div>
                <h2 className="text-lg font-extrabold tracking-tight">
                    Kesesuaian dengan persyaratan
                </h2>
                <p className="text-sm text-muted-foreground">
                    Jawab Ya atau Tidak dengan jujur. Jawaban Tidak tidak
                    otomatis menggugurkan Anda.
                </p>
            </div>

            {Object.entries(groups).map(([group, items]) => (
                <fieldset key={group} className="surface space-y-3 p-4">
                    <legend className="label-mono px-1">{group}</legend>
                    {items.map((question) => (
                        <div
                            key={question.key}
                            className="flex flex-wrap items-center justify-between gap-3"
                        >
                            <span className="min-w-0 flex-1 text-sm">
                                {question.text}
                            </span>
                            <span className="flex gap-4 text-sm">
                                {[
                                    ['yes', 'Ya'],
                                    ['no', 'Tidak'],
                                ].map(([value, label]) => (
                                    <label
                                        key={value}
                                        className="flex items-center gap-1.5"
                                    >
                                        <input
                                            type="radio"
                                            name={`answers[${question.key}]`}
                                            value={value}
                                            required
                                        />
                                        {label}
                                    </label>
                                ))}
                            </span>
                        </div>
                    ))}
                </fieldset>
            ))}
            <InputError message={error} />
        </section>
    );
}

/**
 * The candidate form (REC-INT), opened from the personal link in an interview invitation. It shows the interview, asks a
 * yes or no on each line of the requisition's checklist and a few details, and can be sent once.
 */
export default function CandidateForm(props: Props) {
    const { state } = props;

    if (state === 'invalid') {
        return (
            <>
                <Head title="Tautan tidak berlaku" />
                <Notice title="Tautan tidak berlaku">
                    Tautan ini tidak dikenali atau sudah diganti dengan yang
                    baru. Gunakan tautan terbaru dari pesan undangan, atau
                    hubungi HR.
                </Notice>
            </>
        );
    }

    if (state === 'submitted') {
        return (
            <>
                <Head title="Formulir terkirim" />
                <Notice title="Terima kasih">
                    {`Formulir Anda untuk posisi ${props.positionName} sudah kami terima. Tidak ada yang perlu dilakukan lagi; sampai jumpa di interview.`}
                </Notice>
            </>
        );
    }

    if (state === 'expired') {
        return (
            <>
                <Head title="Tautan kedaluwarsa" />
                <Notice title="Tautan sudah kedaluwarsa">
                    Masa berlaku tautan ini sudah habis. Hubungi HR untuk
                    meminta tautan baru.
                </Notice>
            </>
        );
    }

    if (state === 'early') {
        return (
            <>
                <Head title="Formulir belum dibuka" />
                <Notice title="Formulir belum dibuka">
                    {`Formulir ini hanya bisa dibuka dan diisi pada hari interview: ${props.opensOn}. Silakan buka kembali tautan ini pada hari itu.`}
                </Notice>
            </>
        );
    }

    if (state === 'closed') {
        return (
            <>
                <Head title="Lowongan ditutup" />
                <Notice title="Lowongan sudah ditutup">
                    {`Lowongan ${props.positionName} sudah tidak membuka formulir. Terima kasih atas minat Anda.`}
                </Notice>
            </>
        );
    }

    return (
        <>
            <Head title={`Formulir — ${props.positionName}`} />

            <div className="space-y-6" data-test="candidate-form">
                <header className="space-y-1">
                    <p className="label-mono">
                        {props.companyName ?? 'Formulir kandidat'}
                    </p>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        {props.positionName}
                    </h1>
                    <p className="text-muted-foreground">
                        Halo {props.candidateName}, lengkapi formulir singkat
                        ini hari ini. Formulir hanya bisa dibuka pada hari
                        interview dan berakhir pada akhir hari ini.
                    </p>
                </header>

                {props.interview && (
                    <section
                        className="surface space-y-1 p-4"
                        data-test="interview-details"
                    >
                        <h2 className="label-mono">Jadwal interview</h2>
                        <p className="font-semibold">{props.interview.when}</p>
                        <p className="text-sm text-muted-foreground">
                            {props.interview.mode}
                            {props.interview.place
                                ? ` · ${props.interview.place}`
                                : ''}
                        </p>
                    </section>
                )}

                <Form
                    {...store.form(props.token ?? '')}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Questions
                                questions={props.questions ?? []}
                                error={errors.answers}
                            />

                            <section className="space-y-4">
                                <h2 className="text-lg font-extrabold tracking-tight">
                                    Informasi tambahan
                                </h2>
                                <div className="grid gap-1">
                                    <Label htmlFor="expected-salary">
                                        Gaji yang diharapkan per bulan (Rp,
                                        opsional)
                                    </Label>
                                    <Input
                                        id="expected-salary"
                                        name="expected_salary"
                                        type="number"
                                        min={0}
                                        step={50000}
                                        inputMode="numeric"
                                    />
                                    <InputError
                                        message={errors.expected_salary}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="available-from">
                                        Bisa mulai bekerja (opsional)
                                    </Label>
                                    <Input
                                        id="available-from"
                                        name="available_from"
                                        type="date"
                                        min={props.today}
                                    />
                                    <InputError
                                        message={errors.available_from}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="message">
                                        Pesan untuk HR (opsional)
                                    </Label>
                                    <textarea
                                        id="message"
                                        name="message"
                                        rows={3}
                                        maxLength={1000}
                                        className="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.message} />
                                </div>
                            </section>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="submit-candidate-form"
                            >
                                Kirim formulir
                            </Button>
                            <p className="text-center text-xs text-muted-foreground">
                                Formulir hanya bisa dikirim sekali.
                            </p>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
