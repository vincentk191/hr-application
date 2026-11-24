# HR Dashboard

**Eyebrow:** HR operations platform
**Headline:** People, leave and attendance, in one place.
**Subtitle:** For groups of companies that still track HR in Access and Excel. Everyone works from the same record, not a copy.
**Stats:** 150-200 | employees per group ; 3 | companies on one dashboard ; 7 | features available

## Problem solved

**Before:**
1. Employee data is split across an Access database, Excel workbooks and PDFs
2. Leave and HR requests are filed on paper forms
3. Attendance can only be checked at the one PC that holds the files
**Before note:** The same fact lives in several places and is updated by hand
**With HR Dashboard:**
1. One record per employee, kept per company and per branch
2. Requests are filed online and approved along the reporting line
3. Attendance is loaded once and visible to HR from any device
**After note:** Everyone works from the same, up-to-date record

## Built for

- **Type of business:** Group of companies | Several operating companies under one parent
- **Scale:** 150-200 employees | Small to medium groups
- **Region:** Indonesia | Head office and branches (Cabang)

## Features

- [x] **Employee directory** - One profile per employee, with company and branch, documents, Excel import and export.
- [x] **Requests (Pengajuan)** - Leave, Izin and Sakit requests with attachments, approved along the reporting line.
  - Izin: filed for coming days
  - Sakit: filed after the day, with an attachment
  - Approval: the employee's supervisor, or HR for Sakit
- [x] **Offboarding** - An exit checklist per employee, with an owner for each item and a scheduled exit date.
- [x] **Profile change requests** - Employees ask for changes to their own data and HR approves them.
- [x] **Attendance by upload** - Head office loads the fingerprint scanner's export; branches without a scanner send a per-period summary.
  - Head office: scanner export, uploaded on working days
  - Other branches: summary per period (rekapitulasi)
  - Unmatched scanner IDs: held until HR links them, never dropped
- [x] **Roles, companies and security** - Permissions per role, a filter per company, two-factor sign-in, a private network entrance and an audit trail.
- [x] **Admin panel** - Companies, users, roles, departments, leave kinds and branches, with encrypted nightly backups.
- [~] **Recruitment** - From a job requisition through screening, interviews and offer to a new employee profile.
- [ ] **Recruitment funnel figures** - Dashboard numbers for each step of the hiring funnel.
- [ ] **Automatic attendance sync** - Scanner punches arrive on their own, without an upload.
- [ ] **Employee mobile app** - A companion app for staff to file and follow their own requests.
