# Client Portal

An internal client/project tracker for the team — separate from the main
marketing site. This is plain PHP + MySQL (no build step, no Node), because
it needs a real server and database, unlike the static Astro site.

## What it does

- Tracks every client through a pipeline: Lead → Proposal Sent → Deposit
  Paid → In Design → In Development → In Review → Launched → Maintenance
- Per-client contact info, service type, key dates, and payment status
- A task checklist, notes timeline, and file/asset links per client
- A dashboard with pipeline counts, revenue overview, and a due-soon/overdue
  list for follow-ups and launch dates
- One shared password for the whole team (no individual accounts) — this is
  an internal tool, not client-facing

## Deploying to Hostinger

1. **Create a database.** In hPanel → **Databases → MySQL Databases**,
   create a new database and a database user with access to it. Note the
   database name, username, password, and host (usually `localhost`).

2. **Import the schema.** Open **phpMyAdmin** (linked from the same
   Databases page), select your new database, go to **Import**, and upload
   [`schema.sql`](schema.sql). This creates the `clients`, `notes`, `tasks`,
   `files`, and `settings` tables.

3. **Configure credentials.** Copy [`config.example.php`](config.example.php)
   to `config.php` in this same folder, and fill in the database name,
   username, password, and host from step 1. **Never commit `config.php`** —
   it's already gitignored.

4. **Upload this folder.** Upload the entire `portal/` folder (via File
   Manager or FTP) into `public_html/portal/` on your hosting — so it ends
   up alongside the main site, reachable at
   `https://startupsitesph.com/portal/`. It does **not** go through
   `npm run build`; these are plain `.php` files served as-is.

5. **Set the admin password.** Visit `https://startupsitesph.com/portal/setup.php`
   once. This is a one-time page — it sets the shared password everyone on
   the team will use, then refuses to run again.

6. **Log in.** Visit `https://startupsitesph.com/portal/login.php` and log
   in with the password you just set.

## Forgot the password?

There's no "forgot password" flow (it's one shared password, not individual
accounts). To reset it: open phpMyAdmin, delete the row where
`key = 'admin_password_hash'` in the `settings` table, then visit
`setup.php` again to set a new one.

## Notes

- This tool is intentionally not linked from the public marketing site, and
  its pages send `noindex, nofollow` — but it isn't hidden by anything
  stronger than obscurity. Don't put anything in here you wouldn't want
  found if the URL leaked.
- Updating the portal later: edit files in `portal/`, then re-upload just
  the changed files (or the whole folder) — no rebuild step required.
