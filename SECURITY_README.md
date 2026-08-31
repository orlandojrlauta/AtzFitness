# ATZ Fitness Gym — Security Update & Forgot Password (2026-08-25)

This update applies the school's "Comprehensive Web Application Security
Checklist" to your existing project and adds a working Forgot Password
flow for both Administrator and Staff accounts, with real Gmail email
sending.

## 1. Setup — do these two things before it will work

### A. Run the database migration
Open phpMyAdmin → your `atz_fitness_db` database → SQL tab → paste the
contents of `database/migration_security_and_password_reset.sql` → Go.

This adds:
- `users.failed_login_attempts`, `users.locked_until` (brute-force lockout)
- a new `password_reset_tokens` table (Forgot Password)

### B. Configure Gmail sending
Edit `includes/mail_config.php`:
1. Turn on 2-Step Verification on the Gmail account you want the system
   to send from: https://myaccount.google.com/security
2. Create an App Password: https://myaccount.google.com/apppasswords
3. Paste your Gmail address into `MAIL_USERNAME` and the 16-character
   App Password (no spaces) into `MAIL_PASSWORD`.

Until you do this, Forgot Password still works (tokens are created and
the same generic "check your email" message is shown either way), but
no email actually goes out — the failure is only logged server-side, on
purpose, so a misconfigured mailer can never leak whether an account
exists.

**Do not commit `includes/mail_config.php` with real credentials** —
it's already excluded via `.gitignore`, and `.htaccess` blocks it from
being requested directly over the web.

## 2. What was added / changed

| File | What it does |
|---|---|
| `forgot_password.php` | Request a reset link by username or email (Admin or Staff) |
| `reset_password.php` | Consume the emailed link, set a new password |
| `includes/mailer.php` + `includes/mail_config.php` | Sends real email via Gmail SMTP (PHPMailer) |
| `includes/PHPMailer/` | Bundled PHPMailer library (no Composer needed) |
| `database/migration_security_and_password_reset.sql` | New DB columns/table |
| `.htaccess` (root, `includes/`, `database/`, `uploads/`) | Server hardening |
| `.gitignore` | Keeps credentials/dumps out of GitHub |

`includes/db.php`, `login.php`, `register.php`, and `change_password.php`
were also edited in place — see inline comments for details.

## 3. Checklist coverage

**1. Authentication and Access Control**
- 1.1 Password policy: bcrypt hashing (already in place) + 8+ chars,
  upper/lower/number now enforced on Register, Change Password, and
  Reset Password.
- 1.3 Brute-Force Protection: 5 failed attempts locks the account for
  15 minutes (`users.failed_login_attempts` / `locked_until`); a simple
  math CAPTCHA appears after 3 failed attempts in the same browser
  session; login errors stay generic ("Invalid username or password")
  whether the username or the password was wrong.
- 1.4 Secure Forgot Password: cryptographically random, single-use,
  30-minute reset tokens, stored only as a SHA-256 hash; identical
  response whether or not the account exists; old tokens invalidated on
  a new request or after a successful reset; confirmation email sent
  after the password changes.
- 1.5 Session Management: session ID regenerated on login (already in
  place), HttpOnly/SameSite=Lax/Secure(on HTTPS) cookies, 30-minute idle
  timeout, custom session name.
- 1.6 RBAC: unchanged — `require_role()` / `require_admin()` were
  already doing server-side checks correctly.

**2–3. Data Security, Input Validation, SQLi/XSS/CSRF**
Already solid in the original code (prepared statements everywhere,
`sanitize()` + `htmlspecialchars` on output, CSRF tokens on
state-changing forms) — Login now also has a CSRF token, matching the
rest of the app.

**4. Secure File Upload** — already solid (content-type checked via
`getimagesize()`/PDF header, not just extension; random filenames).
`uploads/.htaccess` now also blocks any script file from executing
there as defense in depth.

**5. Database Security** — prepared statements and password hashing
were already correct; least-privilege DB accounts, backups, and
encryption-at-rest are server/hosting decisions outside this codebase.

**6. Secure Configuration and Server Hardening** — `display_errors`
turned off with server-side logging on; directory listing disabled;
`.env`-style/config/SQL files blocked from direct web access.

**7. Secure Source Code and GitHub** — `.gitignore` now excludes
`mail_config.php`, `/vendor/`, `/node_modules/`, `*.log`, and uploaded
user files.

**8. Error Handling and Logging** — generic error messages preserved;
`log_activity()` now also logs Account Locked, Password Reset Request,
and Password Reset events (still never logs passwords/tokens).

**10. Security Headers** — `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, a CSP (scoped to the CDNs the
app already uses), and HSTS (only sent once you're on HTTPS) are now
sent on every page.

## 4. Left for your team to do (operational, not code)

These items in the checklist depend on your actual server/hosting, not
the application code, so they're not something a code change can tick
off on its own:
- Section 9: run Wapiti / WAFW00F and record before/after results
- Sections 5.2/5.4: dedicated non-root DB account, encrypted offsite
  backups
- Section 6: enabling HTTPS/HSTS on your actual domain, keeping
  PHP/Apache/MySQL updated
- Section 13: firewall rules, WAF, DDoS/CDN protection
- Section 14: run through the Access Control Testing table with real
  test accounts before your final submission
- Section 16/17: fill in the incident response plan and team security
  practices with your actual names/procedures
