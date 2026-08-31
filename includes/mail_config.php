<?php
/**
 * ATZ Fitness Gym Management System
 * Gmail SMTP Configuration (used by includes/mailer.php)
 *
 * HOW TO GET A GMAIL "APP PASSWORD" (required — your normal Gmail
 * password will NOT work here because Gmail blocks direct password
 * logins from apps):
 *   1. Go to https://myaccount.google.com/security
 *   2. Turn on "2-Step Verification" for the Gmail account you want
 *      the system to send from (e.g. your gym's Gmail address).
 *   3. Go to https://myaccount.google.com/apppasswords
 *   4. Create a new App Password (name it e.g. "ATZ Fitness System").
 *   5. Google shows you a 16-character password like: abcd efgh ijkl mnop
 *      Copy it (remove the spaces) and paste it below as MAIL_PASSWORD.
 *
 * DO NOT commit this file with real credentials to a public GitHub
 * repository — see Section 7 of the security checklist. Add
 * includes/mail_config.php to .gitignore once you've filled in your
 * real Gmail address and App Password.
 */

// The Gmail address the system sends FROM (e.g. your gym's Gmail).
define('MAIL_USERNAME', 'saltpap2006@gmail.com');

// The 16-character Gmail App Password generated above (no spaces).
define('MAIL_PASSWORD', 'xwzy usmy ocss gxke');

// Name shown to recipients as the sender, e.g. "ATZ Fitness".
define('MAIL_FROM_NAME', 'ATZ Fitness');

// Gmail SMTP connection settings — these normally don't need to change.
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls'); // 'tls' for port 587, 'ssl' for port 465
?>
