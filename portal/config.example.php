<?php
// Copy this file to config.php and fill in your real values.
// config.php is gitignored on purpose — never commit real credentials.
//
// Find/create the DB values in Hostinger hPanel → Databases → MySQL Databases.

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Outgoing mail for "Send maintenance email" — your real Hostinger mailbox,
// so messages are authenticated and don't land in spam. Find these under
// hPanel → Emails → your mailbox → "Configure email client" (the SMTP host
// is usually smtp.hostinger.com; port 465 uses implicit SSL, port 587 uses
// STARTTLS — use whichever your mailbox's settings page shows).
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); // 'ssl' for port 465, 'tls' for port 587
define('SMTP_USER', 'admin@startupsitesph.com');
define('SMTP_PASS', 'your_mailbox_password');
define('SMTP_FROM_NAME', 'Startup Sites PH');
