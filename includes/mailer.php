<?php
/**
 * ATZ Fitness Gym Management System
 * Email Sending (Gmail SMTP via PHPMailer)
 *
 * Uses the bundled PHPMailer library (includes/PHPMailer/) so the
 * project doesn't require Composer to send real email. Configure your
 * Gmail address + App Password in includes/mail_config.php first.
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Sends an HTML email via Gmail SMTP.
 *
 * @param string $to_email   Recipient address.
 * @param string $to_name    Recipient display name.
 * @param string $subject    Email subject.
 * @param string $html_body  Email body (HTML).
 * @param string $alt_body   Plain-text fallback body.
 * @return bool True on success, false on failure (never throws).
 */
function send_email($to_email, $to_name, $subject, $html_body, $alt_body = '') {
    // Guard against the placeholder credentials in mail_config.php so a
    // fresh install fails loudly in the server log instead of silently
    // pretending to have sent an email.
    if (MAIL_USERNAME === 'your_gym_gmail@gmail.com' || MAIL_PASSWORD === 'your_16_char_app_password') {
        error_log('[Mailer] Gmail credentials are not configured yet — edit includes/mail_config.php.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody  = $alt_body !== '' ? $alt_body : strip_tags($html_body);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[Mailer] Failed to send email to ' . $to_email . ': ' . $mail->ErrorInfo);
        return false;
    }
}
?>
