# Client Portal

An internal client/project tracker for the team — separate from the main
marketing site. This is plain PHP + MySQL (no build step, no Node), because
it needs a real server and database, unlike the static Astro site.

## What it does

- Tracks every client through a pipeline: Lead → Proposal Sent → Deposit
  Paid → In Design → In Development → In Review → Launched → Maintenance
- Per-client contact info, service type, key dates
- A payments ledger (each payment received, dated, with a note) — paid
  total and balance due are always computed from it, never a separate
  number that can drift
- A customizable monthly maintenance breakdown per client (hosting, plugin
  licenses, whatever applies) — the monthly total is the sum of its rows
- A "Send maintenance email" button that emails the client their current
  breakdown, sent via your real Hostinger mailbox over authenticated SMTP
- A notes timeline, a task checklist (assignable to a team member, with an
  optional photo per task), and file/asset links (paste a URL or upload a
  real file) per client
- Real accounts with two roles: **Admin** (full access) and **Developer**
  (sees only their own assigned tasks across every client, nothing else —
  no pricing, contact info, or notes)
- A dashboard with pipeline counts, revenue/expenses/net-profit/outstanding
  totals, a 12-month revenue-vs-expenses chart, and a pipeline-by-stage
  chart
- An expense log (hosting, tools, salaries — optionally linked to a
  specific client, or left general)

## Deploying to Hostinger

1. **Create a database.** In hPanel → **Databases → MySQL Databases**,
   create a new database and a database user with access to it. Note the
   database name, username, password, and host (usually `localhost`).

2. **Import the schema.** Open **phpMyAdmin** (linked from the same
   Databases page), select your new database, go to **Import**, and upload
   [`schema.sql`](schema.sql). This creates all the tables the app needs.
   *(If you'd previously imported an older version of this file before
   accounts/payments/maintenance/expenses existed, drop the database and
   re-import fresh — there's no real client data on it yet worth
   preserving.)*

3. **Configure credentials.** Copy [`config.example.php`](config.example.php)
   to `config.php` in this same folder, and fill in:
   - The database name, username, password, and host from step 1.
   - Your Hostinger mailbox's SMTP settings (for the "Send maintenance
     email" button) — find these under hPanel → **Emails** → your mailbox
     → *Configure email client*. Defaults to `smtp.hostinger.com` on port
     465 (SSL); use port 587 + `tls` if that's what your mailbox shows
     instead.

   **Never commit `config.php`** — it's already gitignored.

4. **Upload this folder.** Upload the entire `portal/` folder (via File
   Manager or FTP) into `public_html/portal/` on your hosting — so it ends
   up alongside the main site, reachable at
   `https://startupsitesph.com/portal/`. It does **not** go through
   `npm run build`; these are plain `.php` files served as-is.

5. **Create your admin account.** Visit
   `https://startupsitesph.com/portal/setup.php` once. This is a one-time
   page — it creates the first admin account, then refuses to run again.

6. **Log in**, then add the rest of the team from the **Team** page (pick
   Admin or Developer for each person). Developers only ever see a "My
   Tasks" list — no client details, pricing, or notes.

## Forgot a password?

There's no self-service "reset password" yet. To fix it: open phpMyAdmin,
either update that user's row in the `users` table with a new bcrypt hash,
or delete the row and re-add them from the Team page with a fresh password.
If every admin account is locked out, delete all rows from `users` and
revisit `setup.php` to create a new one.

## Assigning tasks to a developer

On a client's page, the "Add task" form has an assignee dropdown listing
everyone on the Team page. Whoever it's assigned to will see that task (with
the client's name, but nothing else about the client) on their own My Tasks
page, and can check it off or attach a photo from there.

## File uploads

Uploaded files (task photos, client assets) are stored under `uploads/`,
each renamed to a random filename — never trust the original filename or
the browser's claimed file type. That folder has its own `.htaccess`
disabling script execution entirely, so even if something slipped past the
upload checks, it could never run as PHP. Limits: 10MB per file; images
(jpg/png/gif/webp) everywhere a photo is expected, plus PDF/Word/Excel for
general client file uploads.

## Notes

- This tool is intentionally not linked from the public marketing site, and
  its pages send `noindex, nofollow` — but it isn't hidden by anything
  stronger than obscurity. Don't put anything in here you wouldn't want
  found if the URL leaked.
- Updating the portal later: edit files in `portal/`, then re-upload just
  the changed files (or the whole folder) — no rebuild step required.
