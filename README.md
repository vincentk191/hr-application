# HR Dashboard

Internal HR application for a group of three operating companies: one dashboard for HR and management, and a
self-service view for staff. Laravel 13 (PHP 8.4) with an Inertia + React front end, PostgreSQL, and an admin panel
(Filament) at `/admin`. The UI is in Indonesian.

This file covers **how to deploy the application and how it is structured**. The change log, the screen-by-screen
checklist, decisions and known gaps are in [DEVELOPMENT.md](DEVELOPMENT.md). The full spec is outside this repository in
`../docs/` (`context.md`, and the workbook `HR_Dashboard_Features_and_Test_Scenarios.xlsx`, which wins over any file here
on feature detail).

**Current release:** employee directory, Pengajuan (requests), offboarding, profile change requests, documents, the admin
panel, and attendance (which can be switched off, see below). Recruitment and the employee mobile app are not part of it.
The on-site scanner agent lives in the separate `fingerspot-toolkit` project and needs attendance switched on.

---

## Contents

1. [Stack](#stack)
2. [How it is structured](#how-it-is-structured)
3. [Running it locally](#running-it-locally)
4. [Deploying to a server](#deploying-to-a-server)
5. [Configuration reference](#configuration-reference)
6. [Checks and releases](#checks-and-releases)

---

## Stack

| Layer           | Choice                                                                                       |
| --------------- | -------------------------------------------------------------------------------------------- |
| Framework       | Laravel 13, PHP 8.4, a single monolith                                                       |
| Screens         | Inertia v3 + React 19 + TypeScript, Tailwind 4                                               |
| Admin panel     | Filament 5, at `/admin`: companies, users, roles, departments, leave kinds, offboarding items |
| Database        | PostgreSQL 16                                                                                |
| Authentication  | Laravel Fortify: session login, 2FA. No public registration: HR creates the profile first     |
| Roles           | spatie/laravel-permission (`teams` off); audit trail with spatie/laravel-activitylog          |
| Routes to TS    | Laravel Wayfinder (generated, never committed)                                               |
| Background work | database queue and the Laravel scheduler; no Redis                                           |
| Tests           | Pest (SQLite in memory), PHPStan level 7, Pint                                               |
| Runtime         | Docker Compose, locally and on a server                                                      |

---

## How it is structured

The repository holds two projects side by side, joined at runtime: the backend renders pages with Inertia and serves
the front end's build.

```
hr-application/
├── backend/                the Laravel app: PHP, Composer
├── frontend/               the React app: Node, Vite, TypeScript, Tailwind
├── docker/                 php/ (dev and server PHP config) and server/ (entrypoint, Nginx template, env example)
├── Dockerfile              targets: dev, assets, server (PHP-FPM), web (Nginx)
├── docker-compose.yml          local: db, app, queue, vite
├── docker-compose.server.yml   server: web, app, queue, scheduler, db, wg, tunnel
├── README.md               this file
└── DEVELOPMENT.md          change log, checklist, decisions, known gaps
```

The two projects meet in four places; anything else crossing the boundary is a bug.

| Seam         | Where                                                                  | What it does                                                         |
| ------------ | ---------------------------------------------------------------------- | -------------------------------------------------------------------- |
| Pages        | `frontend/src/pages` named in `backend/config/inertia.php`             | Inertia resolves a component name to a file here                     |
| Built assets | `frontend/` to `backend/public/build`                                  | `vite build` writes the bundle where Laravel serves it               |
| Dev hot file | `frontend/` to `backend/public/hot`                                    | tells Laravel where the Vite dev server is                           |
| Typed routes | `backend/routes` and controllers to `frontend/src/{actions,routes,wayfinder}` | Wayfinder generates TypeScript from the routes; **never edit or commit** |

### Backend (`backend/`)

| Path                  | Holds                                                                                                         |
| --------------------- | ------------------------------------------------------------------------------------------------------------- |
| `app/Actions/`        | one class per business operation (leave filing, offboarding, attendance import, profile changes, accounts)    |
| `app/Concerns/`       | `BelongsToCompany`: the one trait every company-tagged model uses                                             |
| `app/Console/`        | scheduled and manual commands (notices, offboarding exits)                                                    |
| `app/Enums/`          | `Role`, `Permission` (and the role to permission defaults), workflow stages, statuses                         |
| `app/Filament/`       | the admin panel's resources; a new resource must be added to the list in `Providers/Filament/AdminPanelProvider` |
| `app/Http/`           | controllers (web and `Api/`), middleware, form requests (validation and authorisation)                        |
| `app/Models/`         | Eloquent models; `Scopes/CompanyScope` is the tenancy filter                                                  |
| `app/Policies/`       | one per model; they re-check the company as well as the scope                                                 |
| `app/Support/`        | domain helpers: `Access/` (entrances, public route list), `Attendance/`, `Leave/`, `Offboarding/`, `Notifications/`, `CompanyContext`, `ReportingLine`, `Features` |
| `app/Workflows/`      | the approval engine shared by leave, offboarding and (later) recruitment                                      |
| `config/`             | besides the framework's: `entrance.php`, `features.php`, `initial_admin.php`, `attendance.php`, `inertia.php` |
| `database/`           | migrations, factories, seeders: `RoleSeeder` and `SystemAdminSeeder` are for every server; `CompanySeeder`, `DevAccountSeeder`, `OrgHierarchySeeder` are local or staging only |
| `routes/`             | `web.php`, `api.php` (the scanner ingest, token only), `settings.php`, `console.php` (the schedule)           |
| `tests/`              | Pest feature and unit tests                                                                                   |

### Frontend (`frontend/src/`)

`app.tsx` (Inertia entry and layout) · `pages/` (one component per screen) · `layouts/` · `components/` (`ui/` holds
shadcn primitives: do not edit) · `hooks/`, `lib/`, `types/` (hand-written view models) · `actions/`, `routes/`,
`wayfinder/` (generated). `@/` means `frontend/src/` and is defined in `vite.config.ts`. Pages never wrap themselves in the
app layout: `app.tsx` already does.

### The foundations

- **Company scoping.** One database, three companies. Every company-tagged model uses `BelongsToCompany`; `CompanyScope`
  filters its queries by the company `SetActiveCompany` resolved from the signed-in user's record, never from request
  input. `users.company_id` null means a parent-wide account (may switch companies or view them combined). Code that runs
  without a user (console, queue) must scope its own queries.
- **Roles and permissions** are enums. `Role::permissions()` is only the starting point: `RoleSeeder` creates a role once,
  and after that its permissions are managed in the admin panel. A permission added later reaches new installs only.
  Visibility follows the reporting line (`employees.manager_id`): a person sees themselves and their downline, and
  `view-all-employees` lifts that.
- **The approval workflow engine** (`app/Workflows`, four `workflow_*` tables). A module supplies a stage enum and a
  definition; the engine is the only thing that changes a stage. A rejection needs a reason and stops the chain.
- **Two entrances.** `ResolveEntrance` reads which door a request used. *Private* (WireGuard peers, the server itself, the
  office network) gets the full app for roles that hold the permissions. *Public* serves only the employee self-service
  routes listed in `Support/Access/PublicRoutes`, and on it every account is treated as a plain employee. Every route must
  be classed public or private, and a test fails if a new route is not. Separate session cookies per entrance.
- **Feature switch.** `FEATURE_ATTENDANCE=false` ships without attendance (details in the configuration reference).

The data model diagrams and the reasoning behind these are in [DEVELOPMENT.md](DEVELOPMENT.md).

---

## Running it locally

Docker is the supported way: nothing is needed on the host except Docker Desktop (no PHP, Composer or Node).

```bash
docker compose up -d                                           # db + app + queue + vite
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=DevAccountSeeder
```

Open **http://localhost:8001**. The first start is slow (it installs dependencies into named volumes).
`vite` (5173) is the asset server; opening it directly shows Vite's own page, which is normal. The database is published on
5434. Restart `queue` after changing job code.

Seeded logins, all with the password `password`: `group@hr.test` (HR Admin, parent-wide), `hr.a@hr.test` (HR Admin,
Company A), `admin@hr.test` (System Admin, parent-wide), `supervisor.a@hr.test`, `employee.a@hr.test`.

---

## Deploying to a server

This runs the same code the way a server would, from `docker-compose.server.yml`. It needs a **Linux server with Docker and
Docker Compose**; allow about 4 GB of RAM and 10 GB of disk (the first build takes several minutes).

| Service     | What it is                                                                                                   |
| ----------- | ------------------------------------------------------------------------------------------------------------ |
| `web`       | Nginx. Decides which entrance a request used, serves the built files, hands PHP to `app`                     |
| `app`       | PHP-FPM with the code baked into the image. Applies migrations when it starts                                |
| `queue`     | the queue worker                                                                                             |
| `scheduler` | the daily jobs (notices, offboarding exits)                                                                  |
| `db`        | PostgreSQL, not published                                                                                    |
| `wg`        | WireGuard (wg-easy): the private entrance. UDP 51820; its admin page is on `127.0.0.1:51821` only            |
| `tunnel`    | optional (`--profile tunnel`): a temporary https address for trying the PWA; arrives as the **public** entrance |

**How a request is classed.** Nginx decides from the source address and tells PHP, and a client cannot set it. *Private*:
the server itself, `OFFICE_CIDR`, and WireGuard peers (they arrive from `172.30.0.0/24`). *Public*: everything else.
Behind another Nginx on the same host, the visitor's real address is read from `X-Forwarded-For`, and only from the
three Docker gateway addresses. Keep the ranges in `docker-compose.server.yml` and `docker/server/nginx.conf.template` in
step (internal `172.29.0.0/24`, vpn `172.30.0.0/24`, edge `172.31.0.0/24`).

### 1. Prepare the server

- Take a snapshot of the server. If other sites run on it, also back up `/etc/nginx` and list the certificates
  (`certbot certificates`).
- Check the three Docker ranges above are free (`docker network inspect $(docker network ls -q) | grep Subnet`), and that
  TCP 8090 and UDP 51820 are not used.
- Add **one DNS record** for the hostname (type `A`, name `hr` or similar, the server's public IP, TTL 300) and check it
  with `dig +short <hr-host>`. Change nothing else. If the domain has CAA records, they must allow `letsencrypt.org`.
- Commit and push the code, then clone it on the server, for example into `/opt/hr-application`.

### 2. Configure

```bash
cp docker/server/server.env.example docker/server/.env
```

Fill in `docker/server/.env` (every setting is in the [reference](#configuration-reference)). The ones that must be set:

- `APP_KEY`: generate with `docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'`
- `APP_ENV=production`, `COMPOSER_DEV=false`, `APP_URL=https://<hr-host>`
- `DB_PASSWORD`, `WG_ADMIN_PASSWORD`, `WG_HOST` (the server's public address, for WireGuard)
- `WEB_BIND=127.0.0.1` and `WEB_PORT=8090` when another Nginx on the same server does the https (step 5)
- `FEATURE_ATTENDANCE=false` to ship without attendance
- `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD` (12 or more characters) for the first account

### 3. Build and start

```bash
docker compose -f docker-compose.server.yml --env-file docker/server/.env up -d --build
```

### 4. Create the first account, then the companies

```bash
docker compose -f docker-compose.server.yml --env-file docker/server/.env exec app php artisan db:seed --force
```

On a real server (`APP_ENV=production`) this creates the roles and one **System Admin that belongs to no company**, and no
companies. Then:

1. Sign in on the private entrance with that email. The temporary password must be changed at once.
2. In the admin panel (`/admin`) create the companies under **Perusahaan**.
3. Create each company's first HR Admin under **Pengguna**: pick the company and the role *Admin HR*.
4. Remove `INITIAL_ADMIN_PASSWORD` from the env file and run the `up -d` command again. Running the seeder again never
   resets an existing account.
5. HR then adds the employees (one by one or the CSV import in the directory). Each needs a unique 16-digit NIK, because
   staff register with NIK + employee ID and then HR activates the account.
6. HR sets up how absence is counted (admin panel, all of it saved in the database, so a later `up -d` never resets it):
   - **Jenis pengajuan:** each kind has **Dihitung sebagai** (Izin, Sakit, or neither). *Cuti sakit* is Sakit after the
     update migration. A Sakit kind **must** have "Boleh mundur (hari)" set: an employee is marked Alpa first, then files
     the sick form for the past day within that many days, with an attachment (required). It goes to **HR Admin**, not
     the Atasan, and approving it turns that Alpa into Sakit. Izin kinds are filed for coming days.
   - **Cabang:** each has **Sumber absensi**, *Pemindai* (head office: HR uploads the Laporan Absensi from the machine,
     the default) or *Rekapitulasi* (a branch without a machine: its PIC sends a per-period rekap). HR can change this;
     only the System Admin adds, renames or removes a Cabang.
   - **Rekapitulasi cabang:** on **Data absensi**, open the **Rekapitulasi cabang** tab (the other tab is the scanner's
     Laporan Absensi), pick the Cabang and upload its "Rekapitulasi absen karyawan" Excel
     file. It opens as a draft: names are matched to that Cabang's employees, unmatched ones are linked by hand or
     skipped, and differences in Zona or in the deduction against the profile are marked in red (the app's figure is
     used). Nothing counts until **Konfirmasi rekapitulasi**; confirming a corrected file for the same Cabang and dates
     replaces the earlier one. Deduction rates are in `config/attendance.php` (`potongan`).
   - **Zona** (Hijau, Merah, Hitam) is on each employee's profile and sets their deduction rate. Everyone is Hijau until
     HR changes it.

Until the https step is done, reach the app privately with an SSH tunnel: `ssh -L 8090:127.0.0.1:8090 <user>@<server>`, then
open `http://localhost:8090`. That arrives as the private entrance.

*Testing server only:* set `APP_ENV=staging` and `COMPOSER_DEV=true` before step 3, then run `db:seed --force`, then
`db:seed --class=DevAccountSeeder --force` and `--class=OrgHierarchySeeder --force`. These create example companies and
accounts with the password `password`: never on a real server.

### 5. HTTPS in front of the stack

The stack serves plain HTTP on `WEB_PORT`. Put https in front of it with one of these:

**a) The server already runs Nginx for other sites (recommended when it does).** Add a new site file for the HR hostname
only, and never edit the existing ones. It must always replace `X-Forwarded-For` with the visitor's address:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name <hr-host>;
    client_max_body_size 30m;
    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}
```

Then `nginx -t`, `nginx -s reload` (never a restart), and `certbot --nginx -d <hr-host>`. Certbot edits only this site's block.

**b) A temporary https address, no domain:** `docker compose -f docker-compose.server.yml --env-file docker/server/.env --profile tunnel up -d tunnel`,
then `docker logs hr-server-tunnel-1`. Good for trying the PWA on phones.

### 6. Check the entrances from outside (before telling anyone the address)

From a phone on mobile data, **not** the VPN:

- `https://<hr-host>/login` loads.
- `https://<hr-host>/admin`, `/employees` and `/offboarding` are refused (403, or a redirect to the login).
- An elevated account that signs in shows the notice that the rest needs the VPN.

From the server: `curl -s -o /dev/null -w '%{http_code}\n' -H 'X-Forwarded-For: 203.0.113.9' http://127.0.0.1:8090/employees` must
not be 200. If any private page opens from the internet, stop and use the rollback below. Never publish port 8090 on a
public interface: a request that reaches the stack without the header counts as the server itself.

### 7. WireGuard (the private entrance)

Open UDP 51820 in the firewall. Reach the wg-easy page only through an SSH tunnel
(`ssh -L 51821:127.0.0.1:51821 <user>@<server>`, then `http://localhost:51821`), sign in with `WG_ADMIN_USER` /
`WG_ADMIN_PASSWORD`, add a client for each person who needs the private entrance (supervisors, department heads, anyone who
approves, checklist owners, Management, HR, admins), and have them scan the QR code in the WireGuard app. With the VPN on, they
browse to `https://<vpn-host>` once step 7.5 is done (plain `http://172.30.0.10` until then). Delete a person's client when
they leave.

**Limit what a VPN peer can reach (do this before adding the first client).** wg-easy lets a connected device send traffic
anywhere the `wg` container can route, and `INIT_ALLOWED_IPS` only shapes what the *client* sends, which its owner can edit.
Left alone, a peer could open the wg-easy admin page (it listens on every interface inside the container), reach every
other peer including the scanner PC, and reach the host itself (SSH, other sites). In the wg-easy page open **Admin >
Hooks** and replace the four fields with the rules below, which let a peer reach the app's Nginx on 80 and 443 and nothing
else (the `{{...}}` words are wg-easy's own placeholders; leave them as they are):

- **PostUp**
  ```
  iptables -t nat -A POSTROUTING -s {{ipv4Cidr}} -o {{device}} -j MASQUERADE; iptables -A INPUT -p udp -m udp --dport {{port}} -j ACCEPT; iptables -A INPUT -i wg0 -j DROP; iptables -A FORWARD -i wg0 -d 172.30.0.10 -p tcp -m multiport --dports 80,443 -j ACCEPT; iptables -A FORWARD -o wg0 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT; iptables -A FORWARD -i wg0 -j DROP;
  ```
- **PostDown**
  ```
  iptables -t nat -D POSTROUTING -s {{ipv4Cidr}} -o {{device}} -j MASQUERADE; iptables -D INPUT -p udp -m udp --dport {{port}} -j ACCEPT; iptables -D INPUT -i wg0 -j DROP; iptables -D FORWARD -i wg0 -d 172.30.0.10 -p tcp -m multiport --dports 80,443 -j ACCEPT; iptables -D FORWARD -o wg0 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT; iptables -D FORWARD -i wg0 -j DROP;
  ```
- **PreUp** and **PreDown**: empty.

Then restart the tunnel (`... restart wg`) and check from a phone on the VPN, after adding `10.8.0.0/24, 172.30.0.0/24` to
that client's Allowed IPs for the test: the app at `http://172.30.0.10/up` answers; `http://10.8.0.1:51821`,
`http://172.30.0.2:51821` and `172.30.0.1:22` do not; another client's address does not answer a ping. Remove the extra Allowed
IPs afterwards. This was written from wg-easy's documented defaults and has not been run against your server: if a check
above fails, `... exec wg iptables -S` shows what is actually in force.

One consequence stays: every peer reaches the app through the `wg` container's address, so the app sees all VPN users as
`172.30.0.2` and the 20-a-minute sign-in limit per address is shared by them. The audit trail records the user, not just the
address, so nothing is lost there.

### 7.5 HTTPS over the VPN

**Planned, not started** (decided 2026-10-22, see `DEVELOPMENT.md` Phase 7): the domain's DNS is moving to Cloudflare
first, so step 2 below can use an automatic DNS-01 plugin instead of idwebhost's manual one — chosen specifically
because the people using this are not technical, and a self-signed certificate (the other way around idwebhost's
missing TXT support) would ask each of them to install and trust a root certificate by hand, which is exactly the
kind of step that goes wrong silently for someone who does not know what a certificate is. The steps below still
work as written once `VPN_HOSTNAME`'s DNS lives wherever it ends up; only step 2's exact commands change.

Elevated people get the install, the service worker and a real certificate too, not just plain `http://172.30.0.10`.
This uses its own hostname, `<vpn-host>` — separate from `<hr-host>`, so elevated people end up with a second
bookmark/PWA install for VPN use — in exchange for needing nothing beyond a DNS record and a certificate: no proxy,
no change to what a WireGuard peer's traffic touches. `$entrance` in `docker/server/nginx.conf.template` was always
keyed on the connection's source address, not the hostname, so nothing about which screens open changes; only the
address and the padlock are new. Skip this section to stay on plain `http://172.30.0.10` instead — nothing else here
depends on it.

1. **Add one DNS record**, at whichever provider holds the domain: type `A`, name `vpn` or similar, **172.30.0.10**
   (not the server's public IP — this is deliberate, see below). `172.30.0.10` is a private address
   (`172.16.0.0/12`), unreachable from the internet, so publishing it is harmless: off the VPN the name resolves but
   nothing answers; on the VPN, the peer's Allowed IPs already route that one address through the tunnel. No split
   DNS, no extra container, nothing proxied through the server.
2. **Get the certificate with a DNS-01 challenge**, since `172.30.0.10` cannot be reached from the internet the way
   `certbot --nginx` needs (that only works for the public hostname in step 5, which stays unchanged).
   - **With the domain on Cloudflare** (the plan — see above): `certbot dns-cloudflare` fully automates this, TXT
     record and all, and keeps renewing it without anyone touching anything afterward.
   - **Still on idwebhost**, which has no TXT records or API: do it by hand, and note it in the diary — it cannot
     renew unattended, so repeat this command every 60-90 days:
     ```bash
     certbot certonly --manual --preferred-challenges dns -d <vpn-host>
     ```
     Certbot prints a TXT record (`_acme-challenge.<vpn-host>`) and waits. Add exactly that record at idwebhost,
     wait a minute or two for it to propagate (`dig +short TXT _acme-challenge.<vpn-host>` from the server should
     show it), then press Enter to let Certbot verify and issue the certificate.
3. **Set the hostname and rebuild.** In `docker/server/.env`, set `VPN_HOSTNAME=<vpn-host>` (no scheme, no port —
   the exact name the certificate was issued for), then:
   ```bash
   docker compose -f docker-compose.server.yml --env-file docker/server/.env up -d --build
   docker compose -f docker-compose.server.yml --env-file docker/server/.env exec web nginx -t
   ```
   `nginx -t` must say "syntax is ok" / "test is successful". The HTTPS block is only added when `VPN_HOSTNAME` is set
   **and** `/etc/letsencrypt/live/<vpn-host>/fullchain.pem` exists on the host, so check which happened with
   `docker compose -f docker-compose.server.yml --env-file docker/server/.env logs web | grep 15-vpn-tls`: "is on" is
   what you want; "no certificate at …" means the name does not match the certificate (`certbot certificates` on the host
   lists what exists) or step 2 has not been done yet. Nothing else is affected while it is off.
4. **Install the reload hook once**, so a renewal actually reaches the `web` container (it mounts the host's
   certificates read-only and does not otherwise notice a new one):
   ```bash
   cp docker/server/reload-after-renew.sh /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
   chmod +x /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
   ```
   Edit `REPO_DIR` in that file first if the clone is not at `/opt/hr-application`. This also covers the *public*
   certificate's own automatic renewals, so install it even if you only care about those.
5. **Renewal.** With the `dns-cloudflare` plugin this is automatic, same as the public certificate — nothing to do.
   Doing step 2 the idwebhost way instead means doing it **by hand, every 60-90 days** (the certificate is valid
   90; renew with time to spare): without a DNS provider API, Certbot's own timer cannot do this one unattended — it
   skips it silently when it runs automatically, same as every other manual DNS-01 certificate. Repeat step 2's
   command; a successful run still triggers step 4's hook, so there is nothing else to do afterward. If Certbot has
   your real email from setup, it sends a reminder as the expiry approaches — a calendar reminder of your own is
   cheap insurance regardless.
6. **Verify**, on a phone with the WireGuard app:
   - VPN **off**: `https://<vpn-host>` does not load (no route to a private address) — expected, not a bug.
   - VPN **on**: it loads with a valid certificate, and an elevated account can use the email sign-in and open
     `/employees` etc.
   - Install the PWA from `https://<vpn-host>` while connected. It is a separate install from the public one at
     `https://<hr-host>` — two icons, each only useful on its own network.

Rolling back, or switching it off for now: empty `VPN_HOSTNAME` in `docker/server/.env` and run the `up -d` command again;
the HTTPS block is then not added and Nginx starts without it. Then remove the DNS record and the renewal hook if it is
going away for good.

### Day to day

| Task               | Command or note                                                                                                       |
| ------------------ | --------------------------------------------------------------------------------------------------------------------- |
| Update             | `git pull`, then the `up -d --build` command. Migrations run when `app` starts. A permission added to the code reaches existing roles only when granted in the admin panel |
| Update PHP itself  | `up -d --build` reuses a cached copy of the `php:8.4-fpm` base image. For a PHP security release, run `... build --pull --no-cache app` and then `... up -d` (it restarts the workers), and check `... exec app php -v` |
| Check space        | `docker system df`, `docker volume ls`, `df -h /`, `docker stats --no-stream`                                          |
| Reset test data    | testing server only (`APP_ENV=staging`): `... exec app php artisan migrate:fresh --force`, then the seeders in step 4. Refused when `APP_ENV=production` |
| A profile was closed by mistake, or someone returns | on the employee's profile, **Aktifkan kembali** (HR). It reopens the profile, clears the exit date and switches the sign-in offboarding turned off back on. A scanner mapping is not restored |
| Change the env     | edit `docker/server/.env`, run `up -d` again (the configuration is cached when a container starts)                    |
| Back up the data   | automatic every night once set up, see [Backups](#backups); a copy right now (before a risky change): `bash docker/server/backup.sh` |
| Logs               | `docker compose -f docker-compose.server.yml logs -f app web queue`                                                   |
| Watch for guessing | `... logs app \| grep -E 'Sign-in (failed\|throttled)\|Two-factor (code refused\|attempts throttled)'`: a failed attempt keeps the end of the NIK, the address and the entrance, never the password. Successful sign-ins and 2FA changes are in the admin panel's audit log (type `auth`) |
| Someone lost their phone | admin panel > **Pengguna**: **Reset 2FA** (turns it off and ends their sessions; an elevated account must set it up again at the next sign-in), **Reset sandi** if the password may be known, and delete their WireGuard client |
| Roll back          | remove the Nginx site file and run `nginx -t && nginx -s reload`; `docker compose -f docker-compose.server.yml down` (volumes are kept); remove the DNS record |

In the table, `...` stands for `docker compose -f docker-compose.server.yml --env-file docker/server/.env`.

### Keeping the server lean

Rebuilding leaves old images and build cache behind (several GB after a few rebuilds). The two commands below remove only
what no container uses, so they are safe on a server that also runs other stacks:

```bash
docker builder prune -f     # build cache
docker image prune -f       # images no container uses
```

- **Never** run `docker system prune --volumes` or `... down -v`. They delete volumes, and these hold your data:
  `hr-server_pgdata` (the database), `hr-server_appstorage` (documents and photos) and `hr-server_wgdata` (the WireGuard
  peers). `... down` on its own keeps them. To remove one deliberately, stop the stack and run
  `docker volume rm hr-server_pgdata`, for example.
- **Swap.** A server with none kills a process when memory runs out, possibly the database. A 2 GB swap file is cheap insurance:
  ```bash
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
  ```
- **Limits.** The stack caps its own memory so it cannot starve other apps on the host (`mem_limit` in
  `docker-compose.server.yml`: `app` 1.5 GB, `db` 1 GB, `queue` 512 MB, `scheduler` 256 MB), and PHP-FPM runs 3 to 12 workers
  (`docker/php/php-fpm-pool.conf`). That is sized for about 150 employees; a container that goes over its cap is killed and
  restarted.
- **Logs.** Containers log to Docker's JSON files, capped at 5 files of 10 MB each per container (`x-logging` in
  `docker-compose.server.yml`). Check `docker system df -v` if space shrinks without explanation anyway.

### Uploaded files

- **Size limits** (per file): leave attachments 5 MB (up to 5), employee documents and offboarding files 10 MB, profile photos
  2 MB, employee CSV imports 2 MB, attendance logs 20 MB. The file pickers check these in the browser first. Nginx
  (`client_max_body_size`) and PHP (`post_max_size`) allow 30 MB for one form, which is what five 5 MB attachments need.
  Keep the host Nginx site from step 5 at 30m too.
- **PDF compression.** Leave attachments and offboarding files are shrunk with Ghostscript when they are stored; a PDF
  under 200 KB, one Ghostscript cannot read, or one that does not come out smaller is stored as uploaded. **Employee
  documents are never compressed**, because rewriting a signed PDF breaks its signature. Photos are shrunk in the browser.
  The image installs `ghostscript` (see the `Dockerfile`). **The server template turns it off** (`UPLOADS_COMPRESS_PDF=false`):
  Ghostscript would open PDFs uploaded from the public entrance inside the container that holds the database password and
  `APP_KEY`, and it has had sandbox escapes. Turn it on only once compression runs somewhere with no network and no secrets.

### Backups

A backup is one folder, `YYYY-MM-DD_HHMMSS`, of four encrypted files plus a checksum list and a manifest:

| File                   | What it holds                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------------------------ |
| `database.dump.age`    | the PostgreSQL database                                                                                |
| `storage.tar.gz.age`   | uploaded documents, photos, logos and leave attachments (`storage/app`)                                |
| `wireguard.tar.gz.age` | the VPN's server key and every client's key and address                                                |
| `env.age`              | `docker/server/.env`. Its `APP_KEY` decrypts the 2FA secrets in the database: restore the database without it and every elevated account is locked out |

Everything is encrypted with [age](https://github.com/FiloSottile/age) to a **public** key, as it is produced: no unencrypted
copy is ever written to the server's disk, and the matching private key is **never on the server**. Someone who takes the
server, or the copy kept elsewhere, cannot read a backup. The flip side: **lose the private key and every backup is
unreadable.** Keep it in two safe places (a password manager, and a printed copy in a locked drawer).

Each night at 02:30 the script keeps 7 days on the server, copies the folder to a second place with `rclone` and reads it
back to check it arrived intact, and deletes copies there older than 30 days. A failed run leaves no half-written folder and
exits with an error; with the optional monitoring URL below, a missing or failed run also tells you. If only the copy off
the server fails (the provider is down, say), the backup made on this server is kept and the run reports the failure.

**The admin page "Cadangan data"** (admin panel; permission `manage-backups`, given to the System Admin role, and only a
parent-wide account may open it) holds the same settings as `backup.env`: the public key, the copy destination, how long to
keep backups, the monitoring addresses, and a switch that pauses the backups. Above the form it shows what the last run
reported: when it finished, how big it was, which key and destination it used and where each came from, and a warning when
the last run failed, the copy off the server failed, nothing was reported for 36 hours, or the backups are only on this
server. It asks for your password when you open it and again when you save. How it works:

- **A blank means "use the server's value" (`backup.env`), not "off".** Saving the page can never switch off the copy off
  the server or the monitoring that the server file set up. To stop copying off the server, edit `backup.env`.
- **The server does not trust the page.** The script runs as root, so it checks every value again with the same patterns as
  the form and ignores anything that does not match, with a warning in the report. The destination has to look like
  `name:folder` for a remote that `rclone config` set up on the server (so it can be neither an rclone option such as
  `--password-command=...`, which runs a command, nor an on-the-fly `:sftp,host=...:` connection to somewhere else), and the
  monitoring addresses have to be https. A plain local folder (a mounted disk) can only be set in `backup.env`.
- **The cloud credentials are not on the page, on purpose.** They stay in `rclone`'s own configuration on the server. If
  they were in the application's database, anyone who got into the application could also delete the backups.
- **There is no "back up now" button.** The application cannot start commands on the server, and should not. Run
  `bash docker/server/backup.sh` there.
- Changing the key or the destination is written to the audit log (monitoring addresses only as "set", since their path is
  a secret). Setting `BACKUP_IGNORE_PAGE=true` in `backup.env` ignores the page entirely.

**Set it up (once).** Steps 3 to 5 can be done on the admin page instead of in the file, once an account with the permission
can sign in; the file is the fallback and the place for anything the page refuses.

1. On the server: `apt install age rclone curl` (age is in Ubuntu 22.04+ and Debian 12+; otherwise take the binary from
   the age releases page).
2. **On your own computer, not the server**, make the key pair: `age-keygen -o hr-backup-key.txt`. It prints the public key
   (`age1...`); the file holds the private one. Store the file as described above.
3. On the server: `cp docker/server/backup.env.example docker/server/backup.env`, `chmod 600 docker/server/backup.env`, and
   put the public key in `AGE_RECIPIENT`.
4. Choose where the second copy goes (a different provider from this server, or the backups die with it: Backblaze B2, an S3
   bucket, an SFTP box, a Google Drive). Run `rclone config` as root to add it, and put its path in `BACKUP_REMOTE`
   (for example `b2:company-hr-backups/hr-app`). **Give the backups a path of their own**: the script deletes its own
   files older than `BACKUP_REMOTE_KEEP_DAYS` there.
5. Optional but worth it: a check at a service such as healthchecks.io that expects a ping every day and alerts you when one
   is missing. Put its URLs in `BACKUP_OK_URL` and `BACKUP_FAIL_URL`. The pings carry nothing.
6. Run it once by hand and read the output: `bash docker/server/backup.sh`. It ends with `backup: done`.
7. **Prove it can be restored** before relying on it (see "Test a restore" below), then schedule it:
   ```bash
   echo '30 2 * * * root bash /opt/hr-application/docker/server/backup.sh >> /var/log/hr-backup.log 2>&1' | sudo tee /etc/cron.d/hr-app-backup
   ```
   (Change the path if the clone is elsewhere.) Check `/var/log/hr-backup.log` the next morning.

**Test a restore** (every quarter, and after changing the key or the remote). On your own computer, with Docker and age:

```bash
rclone copy b2:company-hr-backups/hr-app/2026-10-27_023001 ./2026-10-27_023001      # or scp it from the server
bash docker/server/backup-verify.sh ./2026-10-27_023001 hr-backup-key.txt
```

It checks the checksums, decrypts all four files, restores the database into a throwaway container (the live one is never
touched) and counts the rows in the main tables. It ends with `OK`. Doing it from the downloaded copy tests the whole path,
including that the key you keep is the one the backups were made for.

**Restore** (the data is damaged or lost). From your own computer, which holds the key and the backup folder; nothing
decrypted is ever stored on the server, it is streamed into the containers:

```bash
SERVER=user@server
RUN=2026-10-27_023001            # the backup folder
KEY=hr-backup-key.txt
DC='cd /opt/hr-application && docker compose -f docker-compose.server.yml --env-file docker/server/.env'

ssh $SERVER "$DC stop app queue scheduler"
age -d -i $KEY $RUN/database.dump.age | ssh $SERVER "$DC exec -T db sh -c 'pg_restore -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" --clean --if-exists --no-owner --no-privileges --exit-on-error'"
ssh $SERVER "$DC up -d app queue scheduler"
age -d -i $KEY $RUN/storage.tar.gz.age | ssh $SERVER "$DC exec -T app tar -xzf - -C /var/www/backend/storage"
age -d -i $KEY $RUN/wireguard.tar.gz.age | ssh $SERVER "$DC exec -T wg tar -xzf - -C /etc/wireguard"
ssh $SERVER "$DC restart wg"
```

The database restore replaces what is there (`--clean`). Afterwards check that someone can sign in and open a document, and
that `ls -ln` on the restored files (`$DC exec app ls -ln /var/www/backend/storage/app/private`) shows owner `33`, the web user.
Only the parts that are damaged need restoring: a mistaken deletion is usually just the database line.

**If the whole server is lost:** prepare a new one as in steps 1 to 3 above, but before the first `up -d --build` put the
settings back with `age -d -i $KEY $RUN/env.age | ssh $SERVER "cat > /opt/hr-application/docker/server/.env && chmod 600 /opt/hr-application/docker/server/.env"`;
then `up -d --build`, and the restore commands above. The restored `.env` carries the same `APP_KEY` and `WG_HOST`; change
`WG_HOST` (and the DNS records) if the new server has a different address.

**Not in a backup**, so keep these somewhere else: the private key, the `rclone` configuration (`rclone config file` shows
where it is), the host Nginx site file from step 5, and the DNS records. Certificates are re-issued by Certbot.

### Not built yet (before this is a hardened production)

A WAF in front (Cloudflare, once the domain's DNS moves there; Nginx already limits the public entrance's requests), the
Management accounts (they wait on the client's details), Web Push (alerts are in-app only),
a database login that is not a superuser, mounting only the one certificate and the public files into the `web` container,
compressing PDFs in a sandbox, and a server of its own if the public entrance should not share a machine with other sites.
Details and the open items per screen are in [DEVELOPMENT.md](DEVELOPMENT.md).

---

## Configuration reference

Server settings are in `docker/server/.env` (template: `docker/server/server.env.example`). Local settings are in
`backend/.env` (template: `backend/.env.example`).

| Setting                                  | Meaning                                                                                              |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL` | `production` and `false` on a real server; `staging` on a test server; the key is required      |
| `COMPOSER_DEV`                           | `true` only on a test server, so the example seeders can run                                          |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | the stack's PostgreSQL (defaults `hr_app` / `hr_app`; the password is required)                    |
| `WEB_BIND`, `WEB_PORT`                   | where Nginx is published: `127.0.0.1:8090` by default, behind the Nginx that does the https. Widen `WEB_BIND` only for an office-LAN test |
| `OFFICE_CIDR`                            | the office or LAN range that counts as the private entrance. `127.0.0.1/32` (the server itself) by default; set a real range only for a server inside the office LAN, never a wide one such as `192.168.0.0/16` on a VPS |
| `ENTRANCE_DEFAULT`                       | leave unset on a server: a request Nginx cannot place then counts as public                           |
| `TRUSTED_PROXIES`                        | proxies whose forwarded address and scheme the app believes (default `127.0.0.1,::1`)                 |
| `WG_HOST`, `WG_ADMIN_USER`, `WG_ADMIN_PASSWORD` | WireGuard: the public address peers connect to, and the admin page login                       |
| `VPN_HOSTNAME`                           | a hostname of its own for the VPN entrance, DNS-pointed at `172.30.0.10`. **Optional**: empty (or no certificate for it yet) means Nginx starts without the VPN https. Set it once HTTPS over the VPN is set up (step 7.5); it names the certificate Nginx serves there |
| `FEATURE_ATTENDANCE`                     | `false` removes attendance: its pages, scanner ingest API, profile tab, dashboard card, admin screens and the 09:00 notice (404). The calendar and its holidays stay, because leave day counts need them. On by default; nothing is deleted |
| `FEATURE_ATTENDANCE_AGENTS`              | `true` brings back the automatic on-site sync agents: the ingest API, the agent and sync-log admin screens, "Sinkronkan sekarang" and the agent warnings. **Off by default**: attendance arrives by manual import. Needs `FEATURE_ATTENDANCE` on |
| `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD` | the first System Admin, created by `db:seed` on a real server; remove the password afterwards |
| `ATTENDANCE_DEVICE_TIMEZONE`, `ATTENDANCE_WORK_START`, `ATTENDANCE_WORK_END`, `ATTENDANCE_WORK_END_FRIDAY`, `ATTENDANCE_LATE_GRACE_MINUTES` | attendance defaults (`Asia/Jakarta`, 08:00, 17:00, Friday 17:30, 10). Used until someone saves their own under **Jam kerja** in the admin panel, which also has a field for Friday's end; leave it empty there for a Friday that ends like the other days |
| `AGE_RECIPIENT`, `BACKUP_DIR`, `BACKUP_KEEP_DAYS`, `BACKUP_REMOTE`, `BACKUP_REMOTE_KEEP_DAYS`, `BACKUP_OK_URL`, `BACKUP_FAIL_URL`, `BACKUP_IGNORE_PAGE` | not in `.env`: the backup settings live in `docker/server/backup.env` (template `backup.env.example`), and the admin page "Cadangan data" can override all but `BACKUP_DIR` and `BACKUP_IGNORE_PAGE`. See "Backups" |
| `SESSION_LIFETIME`, `SESSION_ABSOLUTE_LIFETIME` | minutes a session may sit idle (480, so about one password and 2FA code per working day) and the most minutes one sign-in may last however active it is (1440; `0` = no cap). There is no "remember me": it would sign someone back in without the password or the 2FA code |
| `REQUIRE_TWO_FACTOR`                     | on by default: elevated accounts (any role above Karyawan, anyone with reports, approvers, checklist owners) must set up two-factor sign-in before they can use the app, and cannot turn it off; an administrator resets it. `false` only for local development |
| `UPLOADS_COMPRESS_PDF`, `UPLOADS_GHOSTSCRIPT`, `UPLOADS_PDF_PRESET` | shrink stored PDFs (`true`, `gs`, `/ebook`); `/screen` is smaller and softer, `/printer` larger and sharper. The code default is on; **`server.env.example` sets it off**, see "Uploaded files". See `backend/config/uploads.php` |

---

## Checks and releases

```bash
docker compose exec app php artisan test --compact        # all tests
docker compose exec app php artisan test --filter=Workflow
# The whole suite reads xlsx fixtures and passes 128 MB of PHP memory; on a host without Docker use
#   php -d memory_limit=1G vendor/bin/pest --compact
docker compose exec app vendor/bin/pint                   # format PHP
docker compose exec app vendor/bin/phpstan analyse        # level 7
docker compose exec vite npm run check                    # lint and format the front end
docker compose exec vite npm run types:check              # tsc --noEmit
```

Tests run on SQLite in memory, so anything specific to PostgreSQL also needs checking against the real database.

The release number is in `backend/VERSION` (shown under the side menu and by `php artisan about`), with semantic
versioning. To cut a release, change that file, commit it with `composer.lock` and `frontend/package-lock.json`, and tag it
(`git tag v0.1.1`); the tag ties the version to its exact dependencies.
