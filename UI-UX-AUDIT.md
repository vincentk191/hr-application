# UI/UX Audit: Fixes and Optimizations

Audit date: 2026-11-25. Status: **UX-01 to UX-07, UX-09 and UX-10 built 2026-11-25; UX-08 half done (waiting on the owner's choice for the subtitle); UX-12 waiting on approval to delete files.**
Decisions made the same day: UX-04 approved becomes green; UX-06 all companies share one timezone; UX-11 stays as is;
UX-13 no undo for now.

**Built, with what was verified:** UX-01 to UX-03 each have Pest tests (new files `EmployeeFieldRevealTest.php` and
`LeaveQueuePagingTest.php`; three existing assertions updated because the profile now returns masks). Related suites,
Pint, PHPStan, `vp fmt`, `vp lint`, `tsc` and a production build all pass. **Not done: looking at any of it in a
browser.** Check the masked fields, the confirm dialogs and the pager at 360px and desktop before committing.

Measured against the shared guidelines in `.claude/skills/ui-ux-guidelines/` (copy of
`UI_UX_FRONTEND_GUIDELINES.md`). Project overrides (Indonesian only, Inertia instead of React Router/TanStack,
oklch tokens, fixed button-icon vocabulary) are honoured. "§" numbers refer to the guidelines.

Scope reviewed: app shell, navigation, company switcher, login, dashboard structure, leave requests list,
employee profile and drawer, shared status/format helpers, and pattern greps across all of `frontend/src`
(about 40k lines). Not reviewed in a browser; findings come from code and the one login screenshot.

## Summary

| ID | Fix | Priority | Guideline | Size |
|---|---|---|---|---|
| UX-01 | Mask sensitive fields, log reveals | High | §10 | M |
| UX-02 | Replace native confirm boxes with a shared dialog | High | §5.3, §11 | M |
| UX-03 | Bound the leave-requests list (and check the other lists) | High | §7 | M |
| UX-04 | One status system: tokens + `StatusPill` | Medium | §6, §15.1 | L |
| UX-05 | Separate "nothing yet" from "nothing matches" | Medium | §4 | S |
| UX-06 | One `lib/format.ts`, fixed business timezone | Medium | §9, §19 | M |
| UX-07 | Raise faint and tiny text to AA | Medium | §15.3, §16 | M |
| UX-08 | Login: remove the explainer subtitle, fix checkbox label | Medium | §1.2, §4 | S |
| UX-09 | Respect `prefers-reduced-motion` | Low | §16 | S |
| UX-10 | Exact time on hover for relative times | Low | §9 | S |
| UX-11 | Put the active company in the URL (decision needed) | Low | §11 | L |
| UX-12 | Remove unused header layout, label its menu button | Low | §16 | S |
| UX-13 | Undo for one-tap phone "Setujui" | Low | §5.3 | S |

Size: S under half a day, M about a day, L multi-day or cross-cutting.

Suggested order: UX-01, 02, 03 first (risk), then UX-06 and UX-04 (shared work that later fixes build on), then the rest.

---

## High

### UX-01 Mask sensitive fields and log reveals (BUILT)

**As built:** the profile page and the directory drawer receive masks (`••••1234`; amounts `••••`) plus a `masked`
list. An eye button on each field posts to `employees.reveal`, which checks the same permissions as the card
(contact details need only access to the record; bank, payroll, KK and BPJS need the sensitive-data permission or
being the owner), writes "Sensitive field revealed" with the field name (never the value) to the activity log, and
returns the value. It hides again after 30 seconds. Masked: phone, NIK, emergency-contact phone, KK number, account
number, NPWP, both BPJS numbers, base salary, allowances, overtime rate. The edit form still gets real values.
Files: `SensitiveFields.php`, `EmployeeFieldRevealController.php`, `masked-value.tsx`; route classed public so an
employee can reveal their own data on the public entrance. **Not masked:** email, address, date of birth.

- **Problem:** NIK, phone, bank account number, NPWP and base salary render in full for every role allowed to see
  the card. Guidelines say mask by default (`••••1234`), reveal on click, log each reveal.
- **Where:** `frontend/src/pages/employees/show.tsx` (contact ~558-570, bank ~670-692, payroll ~721-730) and
  `frontend/src/components/employee-drawer.tsx` (~294-311).
- **Fix:**
  1. Server sends a masked value plus a `revealable` flag, not the raw value.
  2. A small `MaskedField` component with an eye button (tooltip + `aria-label`) fetches the raw value from a
     dedicated endpoint.
  3. That endpoint checks permission and writes an activity-log entry (who, which employee, which field).
  4. Re-mask after navigation or a short timeout.
- **Check:** page source and Inertia props for an employee contain no raw NIK/account/NPWP/salary until a reveal; each
  reveal appears in the activity log. Feature test for permission and log entry.
- **Note:** the profile-change screens already use a "Dirahasiakan" mask (`profile-changes/index.tsx:38`); reuse the idea.

### UX-02 Replace native `window.confirm` with a shared confirm dialog (BUILT)

**As built:** `useConfirm()` in `components/confirm-dialog.tsx` returns `{ confirm, askReason, dialog }`. Every dialog
shows the record in its title and the company (the active one, or the record's own), puts Batal first and focused,
and uses the fixed button icons. One call needs the name typed to proceed: removing a candidate from a process
(`requireText`), the only one marked "tidak bisa diurungkan". **The earlier count was wrong:** the first search was cut
off, and there were 14 `window.confirm` calls plus 1 `window.prompt` (the reopen-issue reason, now `askReason` with a
5-character minimum instead of silently doing nothing when shorter). None remain in `frontend/src`.

- **Problem:** native confirm boxes cannot name the record, the company or the amount, look different from the app,
  and are blocked or suppressed in some installed-PWA/browser settings.
- **Where it was (14 + 1):** `attendance/device.tsx`, `attendance/rekaps/show.tsx`, `attendance/issues.tsx` (prompt),
  `employees/show.tsx`, `offboarding/form.tsx`, `recruitment/form.tsx`, `calendar-events.tsx` (2),
  `candidate-drawer.tsx` (3), `employee-documents.tsx`, `requisition-offer.tsx`, `requisition-posting.tsx`,
  `requisition-screening.tsx`.
- **Fix:** one `ConfirmDialog` built on the existing `components/ui/dialog.tsx`: title naming the object and company,
  destructive button in red with the fixed icon (Trash2 / Ban), cancel focused by default, button disabled while the
  request is pending. For truly irreversible deletes, ask the user to type the name.
- **Check:** `grep -rn "window.confirm" frontend/src` returns nothing.

### UX-03 Bound the leave-requests list (BUILT for leave requests only)

**As built:** `LeaveRequestController@index` pages on the server, 25 at a time, and reports the real total.
"Menunggu Anda" now runs in SQL with the same `waitingOn` rule the nav badge uses; everything waiting on the person
is sent separately as `mine` so a phone still pins it whichever page is showing; `?open=ID` links (notifications,
search, dashboard) are served by an `opened` prop so they still open when the request is not on page 1. The CSV
export is unchanged and still returns every filtered row. **Note:** the existing `Pagination` component only slices
a list the page already holds, so it would not have fixed the cost; this adds `ServerPagination` beside it. The
employee directory and attendance recap still load whole lists and page them on screen (their own follow-up).

- **Problem:** `LeaveRequestController::filtered()` ends in `->get()` and the default filter is "Semua", so every
  request ever made is loaded, serialised and rendered. It grows without limit.
- **Where:** `backend/app/Http/Controllers/LeaveRequestController.php:136`.
- **Fix:** paginate on the server (reuse `components/pagination.tsx`, already used by employees and recap), and/or
  default the first view to "Menunggu Anda" plus the current month. Keep filters in the URL as they are now.
- **Also check:** only this controller was read. Recruitment, offboarding, profile-changes and notifications lists have
  no frontend pagination component either; confirm their query sizes before declaring them fine.
- **Check:** a seeded set of 2,000 requests renders a page quickly and filters still work.

---

## Medium

### UX-04 One status system: tokens + `StatusPill` (BUILT)

**As built:** five status tokens in `css/app.css` (success, pending, danger, info, caution), each with a fill and a
readable `-text` colour for light and dark, used as `bg-success/15 text-success-text`. Light-theme text colours were
darkened until every pill clears 4.5:1 on its own tint (lowest is 4.7, checked by calculation, not by eye).
`components/status-pill.tsx` draws icon + text + colour over seven tones (neutral, info, pending, caution, success,
danger, void); `lib/status.ts` holds the tone for each flow (`decisionTone`, `offboardingStageTone`,
`checklistGroupTone`, `offerStageTone`, `inviteStateTone`, `rekapStatusTone`, `ageTone`). All 28 status badges moved
onto it, the 315 raw amber/emerald/red/sky/orange classes became tokens (dark-mode companions removed), approved is
green everywhere including the approval chain and the leave dialog marker, and the approval chain's open step no
longer uses the brand red. Leave requests that are waiting show how long (`Atasan langsung · 4 hari`), warming from
amber to orange at 3 days and red at 7.
- **Decisions I made that you may want to change:** an active employee is a green "success" pill (it is on every
  directory row, so that is a lot of green; neutral would be the quiet alternative); recruitment requisitions that
  are not drafts are "info"; a rekapitulasi draft is "pending" and a replaced one "void"; document "expiring soon" is
  orange and "expired" red.
- **Left as they are:** violet, rose and slate, which are categorical colours (calendar event kinds, issue types),
  not statuses, and neutral greys for chrome. The age counts from the day a request was first filed, so a request sent
  back and filed again shows its whole age.
- **Verified:** typecheck, lint, production build and the compiled CSS contains every new utility. **Not verified:** how
  it looks. Check each pill in light and dark, especially the attendance tiles and calendar legend.

- **Problem:** two unrelated systems.
  - Attendance: green/amber/orange/red/sky tiles (`lib/attendance-status.ts`), with dark variants written per call.
  - Leave, recruitment, offboarding: `Badge` variants `default` (black), `secondary` (grey), `destructive` (red),
    with no icon (`lib/leave-stage.ts`).
  - 315 raw palette classes (`bg-amber-*`, `text-emerald-*`, ...) across 39 files; hot spots:
    `candidate-evaluations.tsx` (15), `admin-overview.tsx` (12), `attendance-status.ts` (10),
    `attendance-summary-views.tsx` (8).
- **Fix:**
  1. Add semantic tokens to `css/app.css`: `--status-neutral/info/pending/success/danger` with light and dark values.
  2. Build `StatusPill` (icon + text + colour, never colour alone) mapping states to those tokens.
  3. Migrate leave, recruitment, offboarding and attendance onto it; add age to waiting items (`Menunggu · 6h`).
  4. Keep `Badge` for non-status labels.
- **Decided 2026-11-25:** "approved" becomes green. The accent is red and red also means "problem" in the status
  palette; keep the two apart (accent only for primary buttons and active nav, never for status).
- **Check:** `grep -rE "(bg|text|border)-(emerald|amber|red|sky|orange|green|rose|violet)-[0-9]" frontend/src`
  returns only the token definitions.

### UX-05 Separate "nothing yet" from "nothing matches" (BUILT for four lists)

**As built:** `components/empty-list.tsx` shows "Belum ada …" when there is nothing at all, and "Tidak ada yang cocok" with a
"Hapus filter" button when filters are on. Applied to the leave queue (table and phone), the employee directory (phone,
grid, table), recruitment and offboarding. Recruitment and offboarding default to "open only", so for them any status
but "Semua" counts as a filter and the button sets it to "Semua". **Not done:** the three attendance calendar views and
the recap's pickers still say "cocok" without a button; the attendance issues list keeps its positive "Tidak ada masalah
pada rentang ini", which is good news rather than an empty list. There is no "no access" variant yet, because none of
these lists can be reached without access.

- **Problem:** `leave-requests/index.tsx:260` and `:355` say "Belum ada pengajuan." even when filters are on.
  Only the employee directory offers chip removal; most lists have no "clear filters".
- **Fix:** extend `EmptyNote` with three variants: nothing yet (with the relevant action), nothing matches (with a
  "Hapus filter" button), no access. Apply to leave requests, employees, recap, issues, recruitment, offboarding.
- **Check:** with a filter that matches nothing, each list shows the "nothing matches" variant and the button restores
  the full list.

### UX-06 One `lib/format.ts` with a fixed business timezone (BUILT)

**As built:** `lib/format.ts` has `formatRupiah`, `formatNumber`, `formatDay` ("13 Nov"), `formatDate` ("1 Okt 2026"),
`formatInstant` (with optional time, in Asia/Jakarta), `businessToday`, `businessNow` and `daysSince`. The eight
rupiah formatters, two clashing `shortDate` functions (they had different output), two local "28 Okt" helpers and the
`relativeTime` fallback now use it, and `lib/short-date.ts` is gone. "Today" and "now" (dashboard calendars, the leave
dialog's year, interview-in-the-future checks, the interview calendar) are read in the business timezone, not the
browser's. Verified by running the helpers under three process timezones (Jakarta, Makassar, New York): identical
output.
- **Correction to this audit's own claim:** it said interview times shifted on phones set to WITA/WIT. They do not: a
  time from the server is read back as the same wall-clock digits wherever the phone is. The real bug was in the
  comparisons with "now" (is this interview still ahead, which day is today), which used the phone's clock. Those are
  fixed. The per-component month and weekday name formatters were left, since they format naive dates and are correct.
- **Not done:** the interview calendar and similar screens still build naive local `Date`s; that is fine while every
  company shares one timezone, which is the decision made today.

- **Problem:**
  - The rupiah `Intl.NumberFormat` is re-defined in at least 7 files (`recruitment/show`, `employees/show`,
    `rekaps/show`, `recap`, `requisition-offer`, `requisition-filled`, `candidate-drawer`).
  - Date helpers are re-defined in several more (`approval-chain`, `candidate-drawer`, `requisition-summary`,
    `leave-decision-dialog`, `requisition-interview`, `dashboard-overview`, `admin-overview`).
  - Times use `getHours()`/`getMinutes()` (e.g. `requisition-interview.tsx:614`), so they follow the viewer's device
    timezone. A phone on WITA/WIT or abroad shows a different interview time than the server stored.
- **Fix:** create `frontend/src/lib/format.ts` with `formatRupiah`, `formatDate`, `formatDateTime`, `formatTime`,
  `formatRange`, all using `timeZone: 'Asia/Jakarta'` (or the company timezone from shared props if companies differ).
  Replace the copies. Keep money as the integers the API already sends.
- **Check:** change the OS timezone to `Asia/Makassar`; interview and scan times do not move. `grep -rn
  "new Intl.NumberFormat" frontend/src` returns only `format.ts`.

### UX-07 Raise faint and tiny text to AA (BUILT)

**As built:** every `text-[9px]`, `[10px]` and `[11px]` became 12px, and the shared `.label-mono` captions and table
headings went from 10px to 12px (this is most of what people read at that size: "NIK", "ADMINISTRASI", column
headings). The fade on muted text (`text-muted-foreground/50` to `/70`) is gone. Count badges (notifications, tab bar,
filters, unread comments) grew from 16px to 20px so the larger digits still fit. **Check by eye:** the compact month
grid cells on a 360px phone (their labels went from 9px to 12px and rely on truncation), and any caption that now wraps.
Left alone on purpose: the 40-70% opacity on disabled or dimmed states (offboarding "not involved", unselected filter
chips), which are not meant to be read at full contrast.

- **Problem:** 52 uses of `text-[9px]`/`[10px]`/`[11px]`; `text-muted-foreground/50` to `/70` in nine places
  (`mobile-tab-bar.tsx`, `dashboard-overview.tsx`, `candidate-evaluations.tsx`, `candidate-drawer.tsx`,
  `attendance-week-grid.tsx`, `attendance-day-view.tsx`, `app-sidebar.tsx`). Opacity on muted text lowers it below
  4.5:1.
- **Fix:** minimum 12px for any text that carries information; drop the opacity modifiers on text (use
  `text-muted-foreground` as is, or a stronger token); check `--muted-foreground` itself in both themes with a
  contrast tool.
- **Check:** contrast pass on the tab bar, dashboard and week grid in light and dark.

### UX-08 Login page (HALF DONE: the owner chooses for the subtitle)

**Done:** the "remember NIK" checkbox label is now written as a sentence (normal case, sans, foreground colour, 14px)
and the uppercase field labels are 12px.
**Waiting on the owner:** the subtitle "Masuk dengan NIK dan ID karyawan Anda." Per the standing rule below, options are:
(a) delete it, since the field labels already say it; (b) an ⓘ tooltip beside the "Masuk" heading holding it; (c) no
pop-up warning fits, as nothing here is a condition that needs warning. Recommendation: (a) or (b). The "Lupa kata sandi?
Hubungi HR" line stays, because it tells people what to do.

- **Problem:** the subtitle "Masuk dengan NIK dan ID karyawan Anda." (`pages/auth/login.tsx:250`) restates the field
  labels, against the no-explainer rule. The checkbox label "INGAT NIK DAN ID KARYAWAN DI PERANGKAT INI" is
  uppercase with wide tracking, hard to scan; the "Lupa kata sandi?" help line is acceptable because it is
  actionable.
- **Fix:** remove the subtitle (keep the heading "Masuk"); set the checkbox label in normal case ("Ingat NIK dan ID di
  perangkat ini"). Keep the monospace small-caps for field labels only if contrast stays AA.
- **Standing rule for this clean-up (owner, 2026-11-25):** when redundant or explainer text is taken off a screen,
  do not only delete it. For each piece, also suggest where it could live instead: an `InfoHint` tooltip on the
  nearest label or icon, or a pop-up warning (dialog or inline alert shown only when the condition actually
  applies, such as a stale-data or limit warning). The owner then picks delete, tooltip or pop-up per item. Applies to
  the login subtitle here and to any later sweep for redundant text.
- **Alternatives for the items already removed on 2026-11-25 (not applied, for the owner to pick):**
  working hours and late threshold on the attendance calendar -> tooltip on the calendar header; the 62-day range
  limit -> pop-up warning only when a longer range is picked; "3 masalah" count -> nothing needed (the filter sheet
  button still says it); dashboard date and clock -> nothing needed (device clock).

---

## Low

### UX-09 Respect `prefers-reduced-motion` (BUILT)

**As built:** one global rule at the end of `app.css`: with reduced motion requested, animations and transitions finish at once and loops run once. Needs a browser with "reduce motion" switched on to see.

- **Problem:** zero `motion-reduce` / `prefers-reduced-motion` rules; `tw-animate-css` animations run on drawers,
  dialogs, sheets and dropdowns regardless.
- **Fix:** one global rule in `css/app.css` that shortens animations/transitions to near zero under
  `@media (prefers-reduced-motion: reduce)`.

### UX-10 Exact time on hover for relative times (BUILT for notifications)

**As built:** `components/relative-time.tsx` renders a `<time>` with the exact moment ("25 Nov 2026, 14:05", business timezone) as its tooltip. Used for the notification bell and the notifications page, the only two places that show "5 menit lalu". Other "oleh Rina · 2 jam lalu" audit lines on records do not exist in the app yet.

- **Problem:** `relativeTime()` outputs "5 menit lalu" with no absolute time (`notification-bell.tsx:97`, workflow
  discussions).
- **Fix:** a `<RelativeTime>` component that renders a `<time dateTime>` with the absolute time in `title` (after
  UX-06, using `formatDateTime`). Audit microcopy ("Diubah oleh Rina · 2 jam lalu") on records if it is missing.

### UX-11 Active company in the URL (decided: leave as is, 2026-11-25)

- **Problem:** the company is stored server-side (`CompanySwitcherController`), so a link shared between people in
  different companies opens in the reader's selected company, and a refresh never shows which one a link belongs to.
- **Options:** (a) leave as is, since the switcher is always visible and the server scopes data; (b) add a
  `?company=` or `/c/{company}` prefix. (b) touches every route and every Wayfinder import, so it is a large change
  for a modest safety gain. Recommendation: keep (a), but make the switcher visually stronger when "Semua perusahaan"
  is selected and show the company name on every cross-company row.

### UX-12 Remove unused header layout and label its menu button

- **Problem:** `layouts/app/app-header-layout.tsx` and `components/app-header.tsx` appear unused (only the sidebar layout
  is wired in `layouts/app-layout.tsx`). `app-header.tsx:80` has an icon-only menu button without a label.
- **Fix:** confirm nothing imports them (`grep -rn "app-header" frontend/src`), then delete both. Deleting needs your
  approval per the project rules. If kept, add `aria-label="Buka menu"`.

### UX-13 Undo for the one-tap phone "Setujui" (decided: no undo for now, 2026-11-25)

- **Problem:** on a phone, "Setujui" approves a leave request in one tap with no confirmation or undo
  (`leave-requests/index.tsx`, `approve()`).
- **Fix:** keep it one tap (medium stakes), but show a `sonner` toast with "Batalkan" for about 6 seconds, only if the
  backend can reverse an approval at that stage; otherwise add a lightweight confirm naming the employee and dates.
  Confirm with the workflow rules before choosing.

---

## Optimizations that fall out of the fixes

- UX-04 and UX-06 delete a lot of duplicated colour and format code, shrinking the large files
  (`requisition-interview.tsx` 1623 lines, `recruitment/form.tsx` 1517, `requisition-offer.tsx` 1331,
  `attendance/device.tsx` 1220).
- UX-03 reduces payload and render time on the busiest approval screen.
- UX-02 and UX-05 give shared components that every new screen can reuse, matching §19's building-blocks list
  (`ConfirmDialog`, `EmptyState`, `StatusPill`).
- Skeleton loaders (§14): only the sidebar uses `Skeleton`, and no page uses Inertia deferred props. Revisit after the
  fixes above; the heaviest candidates are the dashboard and the attendance recap.

## Verification for every fix

- `npx vp fmt <files>`, `npx vp lint <files>`, `npx tsc --noEmit` from `frontend/`.
- `npm run build` from `frontend/`.
- Pest tests for any backend change (UX-01, UX-03); run the narrowest set, then the full suite with
  `php -d memory_limit=2G vendor/bin/pest`.
- `vendor/bin/pint --dirty --format agent` from `backend/` after PHP changes.
- Look at each changed screen in a browser at 360px and desktop, light and dark. This audit did not do that.
