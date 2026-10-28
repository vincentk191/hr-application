import {
    Form,
    Head,
    Link,
    router,
    setLayoutProps,
    usePage,
} from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { LineListEditor } from '@/components/line-list-editor';
import { PhaseActions } from '@/components/phase-actions';
import type { PhaseAction } from '@/components/phase-actions';
import {
    RequisitionSummary,
    checklistCounts,
    filledGroups,
    lineCount,
} from '@/components/requisition-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { WorkflowDiscussion } from '@/components/workflow-discussion';
import { destroy, index, show, store, update } from '@/routes/recruitment';
import {
    read as readComments,
    store as storeComment,
} from '@/routes/recruitment/comments';
import type { BreadcrumbItem } from '@/types';
import type {
    Checklist,
    ChecklistKey,
    EmploymentTypeOption,
    ManagerOption,
    Requisition,
    RequisitionContent,
    RequisitionDepartmentOption,
    ResponsibilityGroup,
} from '@/types/recruitment';
import type { WorkflowDiscussionData } from '@/types/workflow';

const selectClasses =
    'border-input bg-card h-10 w-full rounded-xl border px-3 text-sm';
const textareaClasses =
    'w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

type Step = 1 | 2 | 3;

const REQUIRED_REQUEST_FIELDS = 7;
const MAX_SUBTITLES = 12;

type State = 'done' | 'progress' | 'idle';

/** One row of a section: the label on the left, the field on the right (the design's table layout). */
function FieldRow({
    label,
    htmlFor,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid border-t first:border-t-0 md:grid-cols-[14rem_minmax(0,1fr)]">
            <div className="border-b p-4 md:border-r md:border-b-0">
                <Label htmlFor={htmlFor} className="text-sm font-semibold">
                    {label}
                </Label>
                {hint && (
                    <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
            <div className="min-w-0 space-y-1 p-4">
                {children}
                <InputError message={error} />
            </div>
        </div>
    );
}

/** A numbered section card: "01 Posisi yang diminta", with a note on the right ("FIXED SECTION"). */
function Section({
    number,
    title,
    note,
    children,
}: {
    number: string;
    title: string;
    note?: string;
    children: ReactNode;
}) {
    return (
        <section className="surface overflow-hidden">
            <header className="flex items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                <h2 className="flex items-baseline gap-3 text-lg font-extrabold tracking-tight">
                    <span className="label-mono">{number}</span>
                    {title}
                </h2>
                {note && <span className="label-mono text-right">{note}</span>}
            </header>
            {children}
        </section>
    );
}

/** One subtitle of a checklist section: "a Pendidikan" with its lines. */
function ChecklistSubtitle({
    letter,
    label,
    lines,
    onChange,
}: {
    letter: string;
    label: string;
    lines: string[];
    onChange: (lines: string[]) => void;
}) {
    return (
        <div className="space-y-2 border-t p-4 first:border-t-0">
            <p className="font-bold">
                <span className="label-mono mr-2">{letter}</span>
                {label}
            </p>
            <LineListEditor lines={lines} onChange={onChange} label={label} />
        </div>
    );
}

function stateLabel(state: State, detail: string): string {
    const name =
        state === 'done'
            ? 'DONE'
            : state === 'progress'
              ? 'IN PROGRESS'
              : 'NOT STARTED';

    return state === 'idle' ? name : `${name} · ${detail}`;
}

/**
 * The requisition wizard (REC-WIZ): 1 The ask › 2 Details & Requirements › 3 Review. One form holds all three steps,
 * so moving between them keeps what was typed (REC-WIZ-002); "Simpan Draft" keeps it for later at the step it was left
 * on (REC-WIZ-003/004), and "Ajukan untuk Persetujuan" saves and sends it (REC-WZ3-002). Step 2 follows the fixed
 * template: the job posting, then the candidate checklist. A requisition already sent but not decided yet is corrected
 * here too, and stays sent (REC-WIZ-005).
 */
export default function RequisitionForm({
    requisition: existing,
    discussion,
    departments,
    branches,
    managers,
    employmentTypes,
    template,
    today,
    maxStart,
    ownEmployeeId,
}: {
    requisition: Requisition | null;
    /** Only on one already made: what was said when it was sent back. */
    discussion?: WorkflowDiscussionData;
    departments: RequisitionDepartmentOption[];
    branches: { id: number; name: string; company_id: number }[];
    managers: ManagerOption[];
    employmentTypes: EmploymentTypeOption[];
    /** What a new requisition starts with: the base template HR set in the admin panel. */
    template: RequisitionContent;
    /** Today in the company's timezone, which the start date is judged against. */
    today: string;
    maxStart: string;
    ownEmployeeId: number | null;
}) {
    const { auth } = usePage().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Rekrutmen', href: index().url },
        {
            title: existing
                ? `${existing.reference} ${existing.positionName}`
                : 'Permintaan baru',
            href: '#',
        },
    ];

    setLayoutProps({ breadcrumbs });

    const sent = existing !== null && !existing.isDraft;
    const [step, setStep] = useState<Step>(
        (existing?.isDraft
            ? Math.min(3, Math.max(1, existing.draftStep))
            : 1) as Step,
    );
    const [positionName, setPositionName] = useState(
        existing?.positionName ?? '',
    );
    const [departmentId, setDepartmentId] = useState(
        existing ? String(existing.departmentId) : '',
    );
    const [branchId, setBranchId] = useState(
        existing?.branchId ? String(existing.branchId) : '',
    );
    const [quota, setQuota] = useState(
        existing?.quota !== null && existing?.quota !== undefined
            ? String(existing.quota)
            : '',
    );
    const [employmentType, setEmploymentType] = useState(
        existing?.employmentType ?? '',
    );
    const [contractMonths, setContractMonths] = useState(
        existing?.contractMonths ? String(existing.contractMonths) : '',
    );
    const [managerId, setManagerId] = useState(
        existing?.managerId ? String(existing.managerId) : '',
    );
    const [targetStart, setTargetStart] = useState(
        existing?.targetStartOn ?? '',
    );
    const [reason, setReason] = useState(existing?.reason ?? '');
    const [about, setAbout] = useState(existing?.about ?? template.about);
    const [responsibilities, setResponsibilities] = useState<
        ResponsibilityGroup[]
    >(existing?.responsibilities ?? template.responsibilities);
    const [benefits, setBenefits] = useState<string[]>(
        existing?.benefits ?? template.benefits,
    );
    const [checklist, setChecklist] = useState<Checklist>(
        existing?.checklist ?? template.checklist,
    );
    const [stepError, setStepError] = useState<string | null>(null);

    const department = departments.find(
        (option) => String(option.id) === departmentId,
    );
    const branchOptions = department
        ? branches.filter(
              (branch) => branch.company_id === department.company_id,
          )
        : [];
    const managerOptions = department
        ? managers.filter(
              (manager) => manager.company_id === department.company_id,
          )
        : [];
    const type = employmentTypes.find((item) => item.value === employmentType);
    const manager = managers.find((item) => String(item.id) === managerId);

    /** Choosing a department moves the list of branches and superiors to its company, and offers the requester as the superior. */
    const chooseDepartment = (value: string) => {
        const next = departments.find((option) => String(option.id) === value);

        setDepartmentId(value);
        setBranchId('');

        const stillValid = managers.some(
            (item) =>
                String(item.id) === managerId &&
                item.company_id === next?.company_id,
        );

        if (!stillValid) {
            const own = managers.find(
                (item) =>
                    item.id === ownEmployeeId &&
                    item.company_id === next?.company_id,
            );

            setManagerId(own ? String(own.id) : '');
        }
    };

    const content: RequisitionContent = {
        about,
        responsibilities,
        benefits,
        checklist,
    };
    const counts = checklistCounts(checklist);
    const groups = filledGroups(responsibilities);

    // A start date saved earlier can have passed since. A sent requisition keeps its own date as long as it is left alone.
    const startUnchanged =
        sent && existing !== null && targetStart === existing.targetStartOn;
    const startInPast =
        targetStart !== '' && targetStart < today && !startUnchanged;
    const startTooFar = targetStart !== '' && targetStart > maxStart;

    /** What the template still asks of step 1, by name. */
    const missingRequest: string[] = [
        ...(positionName.trim() === '' ? ['Posisi'] : []),
        ...(departmentId === '' ? ['Departemen'] : []),
        ...(quota === '' ? ['Jumlah kuota'] : []),
        ...(employmentType === ''
            ? ['Tipe kontrak']
            : type?.needsMonths && contractMonths === ''
              ? ['Lama kontrak']
              : []),
        ...(managerId === '' ? ['Atasan langsung'] : []),
        ...(targetStart === '' || startInPast || startTooFar
            ? ['Target mulai kerja']
            : []),
        ...(reason.trim() === '' ? ['Alasan permintaan'] : []),
    ];

    /** What it still asks of step 2: the posting's two required parts, and the checklist's two required sections. */
    const missingDetails: string[] = [
        ...(about.trim() === '' ? ['Tentang Posisi'] : []),
        ...(groups.length === 0 ||
        groups.some(
            (group) =>
                group.title.trim() === '' || lineCount(group.items) === 0,
        )
            ? ['Tanggung Jawab Utama']
            : []),
        ...(counts.qualification === 0 ? ['Kualifikasi'] : []),
        ...(counts.skills === 0 ? ['Skills'] : []),
    ];

    const complete = missingRequest.length === 0 && missingDetails.length === 0;

    const requestFilled = REQUIRED_REQUEST_FIELDS - missingRequest.length;
    const requestState: State =
        missingRequest.length === 0
            ? 'done'
            : requestFilled > 0 || step === 1
              ? 'progress'
              : 'idle';
    const detailsFilled =
        about.trim() !== '' ||
        groups.length > 0 ||
        counts.all > 0 ||
        lineCount(benefits) > 0;
    const detailsState: State =
        missingDetails.length === 0
            ? 'done'
            : detailsFilled || step === 2
              ? 'progress'
              : 'idle';

    const stepOneProblem = (): string | null => {
        if (positionName.trim() === '') {
            return 'Isi nama posisi dulu.';
        }

        if (departmentId === '') {
            return 'Pilih departemen dulu.';
        }

        if (quota !== '' && (!/^\d+$/.test(quota) || Number(quota) < 1)) {
            return 'Jumlah kuota harus bilangan bulat, minimal 1.';
        }

        if (startInPast) {
            return 'Target mulai kerja tidak boleh di masa lalu.';
        }

        if (startTooFar) {
            return 'Target mulai kerja terlalu jauh ke depan (maksimal 2 tahun).';
        }

        return null;
    };

    const goTo = (next: Step) => {
        if (next > 1 && step === 1) {
            const problem = stepOneProblem();

            if (problem) {
                setStepError(problem);

                return;
            }
        }

        setStepError(null);
        setStep(next);
    };

    const target = existing ? update.form(existing.id) : store.form();

    const moveChecklist = (key: ChecklistKey, lines: string[]) =>
        setChecklist({ ...checklist, [key]: lines });

    const setGroup = (position: number, group: ResponsibilityGroup) =>
        setResponsibilities(
            responsibilities.map((current, at) =>
                at === position ? group : current,
            ),
        );

    const actions: PhaseAction[] = [
        {
            key: 'save',
            label: sent ? 'Simpan perubahan' : 'Simpan Draft',
            short: 'Simpan',
            rank: sent ? 1 : 2,
            render: (label, className) => (
                <Button
                    type="submit"
                    form="requisition-form"
                    variant={sent ? 'default' : 'outline'}
                    data-test="save-requisition"
                    className={className}
                >
                    {label}
                </Button>
            ),
        },
        ...(!sent
            ? [
                  {
                      key: 'send',
                      label: step === 3 ? 'Ajukan untuk Persetujuan' : 'Review',
                      short: step === 3 ? 'Ajukan' : 'Review',
                      rank: 1,
                      render: (label: string, className?: string) =>
                          step === 3 ? (
                              <Button
                                  type="submit"
                                  form="requisition-form"
                                  name="send"
                                  value="1"
                                  disabled={!complete}
                                  title={
                                      complete
                                          ? undefined
                                          : `Belum lengkap: ${[...missingRequest, ...missingDetails].join(', ')}.`
                                  }
                                  data-test="send-requisition"
                                  className={className}
                              >
                                  {label}
                              </Button>
                          ) : (
                              <Button
                                  type="button"
                                  data-test="to-review"
                                  className={className}
                                  onClick={() => goTo(3)}
                              >
                                  {label}
                              </Button>
                          ),
                  },
              ]
            : []),
        ...(existing?.canDelete
            ? [
                  {
                      key: 'delete',
                      label: 'Hapus draft',
                      short: 'Hapus',
                      rank: 3,
                      render: (label: string, className?: string) => (
                          <Button
                              type="button"
                              variant="ghost"
                              className={className}
                              data-test="delete-requisition"
                              onClick={() => {
                                  if (window.confirm('Hapus draft ini?')) {
                                      router.delete(destroy(existing.id).url);
                                  }
                              }}
                          >
                              {label}
                          </Button>
                      ),
                  },
              ]
            : []),
    ];

    const tabs: { step: Step; title: string; detail: string }[] = [
        {
            step: 1,
            title: '1 · THE ASK',
            detail: stateLabel(
                requestState,
                requestState === 'done'
                    ? `${REQUIRED_REQUEST_FIELDS} FIELDS`
                    : `${requestFilled} OF ${REQUIRED_REQUEST_FIELDS} FIELDS`,
            ),
        },
        {
            step: 2,
            title: '2 · DETAILS & REQUIREMENTS',
            detail: stateLabel(
                detailsState,
                `3 POSTING SECTIONS · ${counts.all} CHECKLIST FIELDS`,
            ),
        },
        {
            step: 3,
            title: '3 · REVIEW',
            detail:
                step === 3
                    ? stateLabel(
                          'progress',
                          complete ? 'SIAP DIKIRIM' : 'BELUM LENGKAP',
                      )
                    : 'NOT STARTED',
        },
    ];

    const author = existing?.createdBy ?? auth.user?.name ?? '';

    return (
        <>
            <Head
                title={
                    existing
                        ? `${existing.reference} · ${existing.positionName}`
                        : 'Ajukan lowongan baru'
                }
            />

            <div className="w-full max-w-5xl space-y-5 p-4 max-md:pb-24">
                <header>
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="label-mono">
                                RECRUITMENT ›{' '}
                                {existing ? existing.reference : 'NEW REQUEST'}
                            </p>
                            <h1 className="text-2xl font-extrabold tracking-tight">
                                {sent
                                    ? `Ubah ${existing?.reference} · ${existing?.positionName}`
                                    : 'Request for Opening'}
                            </h1>
                            <p className="label-mono">
                                {existing
                                    ? `${existing.isDraft ? 'DRAFT ' : ''}${existing.reference}`
                                    : 'DRAFT'}
                                {positionName.trim() !== ''
                                    ? ` · ${positionName.toUpperCase()}`
                                    : ''}
                                {author
                                    ? ` · RAISED BY ${author.toUpperCase()}`
                                    : ''}
                                {sent
                                    ? ' · SUDAH DIAJUKAN'
                                    : ' · NOT SUBMITTED'}
                            </p>
                        </div>
                        <PhaseActions
                            actions={actions}
                            extra={
                                existing &&
                                discussion &&
                                discussion.items.length > 0 && (
                                    <WorkflowDiscussion
                                        discussion={discussion}
                                        reference={existing.reference}
                                        subject={existing.positionName}
                                        currentStageLabel={existing.stageLabel}
                                        canComment={existing.canComment}
                                        postUrl={storeComment(existing.id).url}
                                        readUrl={readComments(existing.id).url}
                                    />
                                )
                            }
                        />
                    </div>
                    {sent && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Belum ada yang memutuskan, jadi pengajuan ini masih
                            bisa diperbaiki. Perubahan langsung terlihat oleh
                            penyetuju.
                        </p>
                    )}
                    <ol
                        className="mt-3 grid gap-2 md:grid-cols-3"
                        data-test="wizard-stepper"
                    >
                        {tabs.map((tab) => (
                            <li key={tab.step} className="contents">
                                <button
                                    type="button"
                                    data-on={step === tab.step}
                                    onClick={() => goTo(tab.step)}
                                    className={`rounded-2xl border p-3 text-left shadow-xs transition-colors ${
                                        step === tab.step
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-border/70 bg-card hover:border-foreground/40'
                                    }`}
                                >
                                    <span className="block text-sm font-extrabold tracking-wide">
                                        {tab.title}
                                    </span>
                                    <span
                                        className={`block font-mono text-[10px] font-medium tracking-[0.12em] uppercase ${
                                            step === tab.step
                                                ? 'text-background/80'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {tab.detail}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ol>
                </header>

                {step > 1 && (
                    <div
                        className="surface flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 text-sm"
                        data-test="ask-summary"
                    >
                        <span className="label-mono">THE ASK</span>
                        <span className="font-semibold">
                            {positionName || '—'}
                        </span>
                        <span className="font-semibold">
                            {department?.name ?? '—'}
                            {quota !== '' ? ` · ${quota} seats` : ''}
                        </span>
                        <Button
                            type="button"
                            variant="link"
                            className="ml-auto h-auto p-0 font-bold"
                            onClick={() => goTo(1)}
                        >
                            EDIT
                        </Button>
                    </div>
                )}

                <Form
                    {...target}
                    id="requisition-form"
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        responsibilities,
                        benefits,
                        checklist,
                    })}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="draft_step"
                                value={step}
                            />

                            <div
                                className={step === 1 ? 'space-y-5' : 'hidden'}
                            >
                                <div className="flex flex-wrap items-baseline gap-3">
                                    <h2 className="text-2xl font-extrabold tracking-tight">
                                        The ask
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Siapa yang dibutuhkan, di mana, dan
                                        mengapa. Belum ada yang dipublikasikan:
                                        ini yang pertama dibaca penyetuju.
                                    </p>
                                </div>

                                <Section
                                    number="01"
                                    title="Posisi yang diminta"
                                >
                                    <FieldRow
                                        label="Posisi"
                                        htmlFor="position_name"
                                        error={errors.position_name}
                                    >
                                        <Input
                                            id="position_name"
                                            name="position_name"
                                            value={positionName}
                                            maxLength={120}
                                            onChange={(event) =>
                                                setPositionName(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="mis. Admin Clerk"
                                        />
                                    </FieldRow>
                                    <FieldRow
                                        label="Departemen"
                                        htmlFor="department_id"
                                        error={errors.department_id}
                                    >
                                        <select
                                            id="department_id"
                                            name="department_id"
                                            value={departmentId}
                                            onChange={(event) =>
                                                chooseDepartment(
                                                    event.target.value,
                                                )
                                            }
                                            className={selectClasses}
                                        >
                                            <option value="">
                                                Pilih departemen…
                                            </option>
                                            {departments.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.name}
                                                </option>
                                            ))}
                                        </select>
                                    </FieldRow>
                                    <FieldRow
                                        label="Cabang"
                                        htmlFor="branch_id"
                                        hint="Opsional"
                                        error={errors.branch_id}
                                    >
                                        <select
                                            id="branch_id"
                                            name="branch_id"
                                            value={branchId}
                                            onChange={(event) =>
                                                setBranchId(event.target.value)
                                            }
                                            disabled={!department}
                                            className={selectClasses}
                                        >
                                            <option value="">
                                                Belum ditentukan
                                            </option>
                                            {branchOptions.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={branch.id}
                                                >
                                                    {branch.name}
                                                </option>
                                            ))}
                                        </select>
                                    </FieldRow>
                                    <FieldRow
                                        label="Jumlah kuota"
                                        htmlFor="quota"
                                        error={errors.quota}
                                    >
                                        <Input
                                            id="quota"
                                            name="quota"
                                            type="number"
                                            min={1}
                                            step={1}
                                            inputMode="numeric"
                                            value={quota}
                                            onChange={(event) =>
                                                setQuota(event.target.value)
                                            }
                                            className="w-28"
                                        />
                                    </FieldRow>
                                </Section>

                                <Section number="02" title="Konteks permintaan">
                                    <FieldRow
                                        label="Tipe kontrak"
                                        htmlFor="employment_type"
                                        error={
                                            errors.employment_type ??
                                            errors.contract_months
                                        }
                                    >
                                        <div className="flex flex-wrap items-center gap-3">
                                            <select
                                                id="employment_type"
                                                name="employment_type"
                                                value={employmentType}
                                                onChange={(event) =>
                                                    setEmploymentType(
                                                        event.target.value,
                                                    )
                                                }
                                                className={`${selectClasses} sm:w-56`}
                                            >
                                                <option value="">Pilih…</option>
                                                {employmentTypes.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            {type?.needsMonths && (
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        name="contract_months"
                                                        type="number"
                                                        min={1}
                                                        max={60}
                                                        step={1}
                                                        inputMode="numeric"
                                                        aria-label="Lama kontrak (bulan)"
                                                        value={contractMonths}
                                                        onChange={(event) =>
                                                            setContractMonths(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-24"
                                                    />
                                                    <span className="text-sm text-muted-foreground">
                                                        bulan
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </FieldRow>
                                    <FieldRow
                                        label="Atasan langsung"
                                        htmlFor="manager_employee_id"
                                        hint="Yang akan membawahi karyawan baru"
                                        error={errors.manager_employee_id}
                                    >
                                        <select
                                            id="manager_employee_id"
                                            name="manager_employee_id"
                                            value={managerId}
                                            onChange={(event) =>
                                                setManagerId(event.target.value)
                                            }
                                            disabled={!department}
                                            className={selectClasses}
                                        >
                                            <option value="">
                                                {department
                                                    ? 'Pilih atasan…'
                                                    : 'Pilih departemen dulu'}
                                            </option>
                                            {managerOptions.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.name}
                                                    {option.number
                                                        ? ` (${option.number})`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </FieldRow>
                                    <FieldRow
                                        label="Target mulai kerja"
                                        htmlFor="target_start_on"
                                        hint="Masih bisa berubah saat surat penawaran dibuat"
                                        error={
                                            errors.target_start_on ??
                                            (startInPast
                                                ? 'Target mulai kerja tidak boleh di masa lalu.'
                                                : startTooFar
                                                  ? 'Terlalu jauh ke depan (maksimal 2 tahun).'
                                                  : undefined)
                                        }
                                    >
                                        <Input
                                            id="target_start_on"
                                            name="target_start_on"
                                            type="date"
                                            min={
                                                sent || startInPast
                                                    ? undefined
                                                    : today
                                            }
                                            value={targetStart}
                                            onChange={(event) =>
                                                setTargetStart(
                                                    event.target.value,
                                                )
                                            }
                                            className="sm:w-56"
                                        />
                                    </FieldRow>
                                    <FieldRow
                                        label="Alasan permintaan"
                                        htmlFor="reason"
                                        error={errors.reason}
                                    >
                                        <textarea
                                            id="reason"
                                            name="reason"
                                            rows={3}
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                            className={textareaClasses}
                                        />
                                    </FieldRow>
                                </Section>

                                <div
                                    className="surface flex flex-wrap items-center justify-between gap-3 bg-muted/40 p-4"
                                    data-test="step-one-banner"
                                >
                                    <p className="label-mono uppercase">
                                        {missingRequest.length === 0
                                            ? 'Semua baris wajib sudah diisi'
                                            : `${missingRequest.length} baris wajib belum diisi: ${missingRequest.join(' dan ')}`}
                                        {' — '}isi lowongan dan checklist diisi
                                        di langkah 2
                                    </p>
                                    <Button
                                        type="button"
                                        onClick={() => goTo(2)}
                                        data-test="next-step"
                                    >
                                        Lanjut ke detail →
                                    </Button>
                                </div>
                            </div>

                            <div
                                className={step === 2 ? 'space-y-5' : 'hidden'}
                            >
                                <div className="flex flex-wrap items-baseline gap-3">
                                    <h2 className="text-2xl font-extrabold tracking-tight">
                                        Job posting details
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Tentang Posisi, Tanggung Jawab Utama dan
                                        Benefit &amp; Fasilitas menggambarkan
                                        pekerjaannya, bukan kandidatnya:
                                        tercetak di lowongan dan tidak pernah
                                        dinilai.
                                    </p>
                                </div>

                                <Section
                                    number="01"
                                    title="Tentang Posisi"
                                    note="SATU PARAGRAF · TERCETAK PERTAMA DI LOWONGAN"
                                >
                                    <div className="space-y-1 p-4">
                                        <textarea
                                            id="about"
                                            name="about"
                                            rows={5}
                                            maxLength={3000}
                                            aria-label="Tentang Posisi"
                                            value={about}
                                            onChange={(event) =>
                                                setAbout(event.target.value)
                                            }
                                            className={textareaClasses}
                                        />
                                        <InputError message={errors.about} />
                                    </div>
                                </Section>

                                <Section
                                    number="02"
                                    title="Tanggung Jawab Utama"
                                    note="SUBTITLE BERBEDA TIAP POSISI"
                                >
                                    <div className="divide-y">
                                        {responsibilities.map(
                                            (group, position) => (
                                                <div
                                                    key={position}
                                                    className="space-y-3 p-4"
                                                    data-test="responsibility-group"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <span className="label-mono">
                                                            {
                                                                'abcdefghijkl'[
                                                                    position
                                                                ]
                                                            }
                                                        </span>
                                                        <Input
                                                            value={group.title}
                                                            maxLength={80}
                                                            placeholder="Judul subtitle, mis. Administrasi & dokumentasi"
                                                            aria-label={`Subtitle ${position + 1}`}
                                                            className="font-semibold"
                                                            onChange={(event) =>
                                                                setGroup(
                                                                    position,
                                                                    {
                                                                        ...group,
                                                                        title: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setResponsibilities(
                                                                    responsibilities.filter(
                                                                        (
                                                                            _,
                                                                            at,
                                                                        ) =>
                                                                            at !==
                                                                            position,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            Hapus
                                                        </Button>
                                                    </div>
                                                    <LineListEditor
                                                        lines={group.items}
                                                        label={`Tanggung jawab ${position + 1}`}
                                                        onChange={(items) =>
                                                            setGroup(position, {
                                                                ...group,
                                                                items,
                                                            })
                                                        }
                                                    />
                                                </div>
                                            ),
                                        )}
                                        <div className="flex flex-wrap items-center gap-3 p-4">
                                            <Button
                                                type="button"
                                                disabled={
                                                    responsibilities.length >=
                                                    MAX_SUBTITLES
                                                }
                                                data-test="add-subtitle"
                                                onClick={() =>
                                                    setResponsibilities([
                                                        ...responsibilities,
                                                        {
                                                            title: '',
                                                            items: [''],
                                                        },
                                                    ])
                                                }
                                            >
                                                + Tambah subtitle
                                            </Button>
                                            <span className="label-mono">
                                                Satu posisi bisa punya satu
                                                subtitle atau beberapa
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.responsibilities}
                                            className="px-4 pb-4"
                                        />
                                    </div>
                                </Section>

                                <Section
                                    number="03"
                                    title="Benefit & Fasilitas"
                                    note="BAGIAN TETAP"
                                >
                                    <div className="p-4">
                                        <LineListEditor
                                            lines={benefits}
                                            label="Benefit"
                                            onChange={setBenefits}
                                        />
                                    </div>
                                </Section>

                                <h2 className="pt-2 text-2xl font-extrabold tracking-tight">
                                    Candidate Checklist
                                </h2>

                                <Section
                                    number="01"
                                    title="Kualifikasi"
                                    note="BAGIAN TETAP · WAJIB"
                                >
                                    <ChecklistSubtitle
                                        letter="a"
                                        label="Pendidikan"
                                        lines={checklist.education}
                                        onChange={(lines) =>
                                            moveChecklist('education', lines)
                                        }
                                    />
                                    <ChecklistSubtitle
                                        letter="b"
                                        label="Pengalaman"
                                        lines={checklist.experience}
                                        onChange={(lines) =>
                                            moveChecklist('experience', lines)
                                        }
                                    />
                                    <InputError
                                        message={errors.qualification}
                                        className="px-4 pb-4"
                                    />
                                </Section>

                                <Section
                                    number="02"
                                    title="Skills"
                                    note="BAGIAN TETAP · WAJIB"
                                >
                                    <ChecklistSubtitle
                                        letter="a"
                                        label="Hard Skills"
                                        lines={checklist.hard}
                                        onChange={(lines) =>
                                            moveChecklist('hard', lines)
                                        }
                                    />
                                    <ChecklistSubtitle
                                        letter="b"
                                        label="Soft Skills"
                                        lines={checklist.soft}
                                        onChange={(lines) =>
                                            moveChecklist('soft', lines)
                                        }
                                    />
                                    <InputError
                                        message={errors.skills}
                                        className="px-4 pb-4"
                                    />
                                </Section>

                                <Section
                                    number="03"
                                    title="Nilai Plus"
                                    note="TIDAK WAJIB · MENAMBAH NILAI KANDIDAT"
                                >
                                    <div className="p-4">
                                        <LineListEditor
                                            lines={checklist.bonus}
                                            label="Nilai Plus"
                                            onChange={(lines) =>
                                                moveChecklist('bonus', lines)
                                            }
                                        />
                                    </div>
                                </Section>

                                <div
                                    className="surface flex flex-wrap items-center justify-between gap-3 bg-muted/40 p-4"
                                    data-test="step-two-banner"
                                >
                                    <p className="label-mono uppercase">
                                        {missingDetails.length === 0
                                            ? 'Isi lowongan dan checklist sudah lengkap'
                                            : `Belum lengkap: ${missingDetails.join(', ')}`}
                                    </p>
                                    <Button
                                        type="button"
                                        onClick={() => goTo(3)}
                                        data-test="to-review-bottom"
                                    >
                                        Review →
                                    </Button>
                                </div>
                            </div>

                            <div
                                className={step === 3 ? 'space-y-5' : 'hidden'}
                            >
                                <div className="flex flex-wrap items-baseline gap-3">
                                    <h2 className="text-2xl font-extrabold tracking-tight">
                                        Review sebelum dikirim
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Satu halaman berisi semua yang sudah
                                        diisi. Setiap bagian bisa dibuka kembali
                                        tanpa kehilangan isian.
                                    </p>
                                </div>

                                <RequisitionSummary
                                    data={{
                                        ...content,
                                        positionName,
                                        department: department?.name ?? null,
                                        branch:
                                            branchOptions.find(
                                                (branch) =>
                                                    String(branch.id) ===
                                                    branchId,
                                            )?.name ?? null,
                                        quota:
                                            quota === '' ? null : Number(quota),
                                        contractLabel: contractText(
                                            type,
                                            contractMonths,
                                        ),
                                        targetStartOn: targetStart || null,
                                        manager: manager?.name ?? null,
                                        reason,
                                    }}
                                    onEdit={goTo}
                                />

                                <section className="surface space-y-2 p-4 text-sm">
                                    <h2 className="label-mono">
                                        Alur persetujuan
                                    </h2>
                                    <p>
                                        01 · Manajemen menyetujui dan menetapkan
                                        ekspektasi gaji
                                    </p>
                                    <p>
                                        02 · HR menyetujui, lalu memposting
                                        lowongan
                                    </p>
                                    <p className="text-muted-foreground">
                                        Pengajuan dikirim ke Manajemen di atas
                                        Anda pada garis pelaporan, atau ke
                                        Manajemen mana pun bila tidak ada.
                                    </p>
                                </section>

                                {!complete && (
                                    <div
                                        className="rounded-xl border border-amber-500/50 p-3 text-sm text-amber-700 dark:text-amber-300"
                                        data-test="missing-banner"
                                    >
                                        <p className="font-medium">
                                            Belum bisa diajukan, masih perlu:
                                        </p>
                                        <ul className="list-disc pl-5">
                                            {missingRequest.length > 0 && (
                                                <li>
                                                    Langkah 1:{' '}
                                                    {missingRequest.join(', ')}
                                                </li>
                                            )}
                                            {missingDetails.length > 0 && (
                                                <li>
                                                    Langkah 2:{' '}
                                                    {missingDetails.join(', ')}
                                                </li>
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </div>

                            {stepError && (
                                <p
                                    className="text-sm font-medium text-destructive"
                                    data-test="step-error"
                                >
                                    {stepError}
                                </p>
                            )}

                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-xl border border-destructive/40 p-3 text-sm text-destructive">
                                    <p className="font-medium">
                                        {sent
                                            ? 'Belum bisa disimpan:'
                                            : 'Belum bisa diajukan:'}
                                    </p>
                                    <ul className="list-disc pl-5">
                                        {Object.values(errors).map(
                                            (message) => (
                                                <li key={message}>{message}</li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-3 border-t pt-4">
                                {step > 1 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => goTo((step - 1) as Step)}
                                    >
                                        ‹ Sebelumnya
                                    </Button>
                                )}
                                {processing && <Spinner />}
                                <Button asChild variant="ghost">
                                    <Link
                                        href={
                                            sent && existing
                                                ? show(existing.id).url
                                                : index().url
                                        }
                                    >
                                        Batal
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

/** "Kontrak 1 tahun" the way the server writes it (Requisition::contractLabel), for the review before anything is saved. */
function contractText(
    type: EmploymentTypeOption | undefined,
    months: string,
): string | null {
    if (!type) {
        return null;
    }

    const count = Number(months);

    if (!type.needsMonths || !Number.isInteger(count) || count < 1) {
        return type.label;
    }

    return `${type.label} ${count % 12 === 0 ? `${count / 12} tahun` : `${count} bulan`}`;
}
