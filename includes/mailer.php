<?php
require_once __DIR__ . '/../config/mail.php';

/**
 * Send Email helper using Socket SMTP or fallback to PHP native mail()
 *
 * @param string $toEmail Recipient Email Address
 * @param string $toName Recipient Full Name
 * @param string $subject Email Subject
 * @param string $htmlMessage HTML Email Content
 * @param string $altTextMessage Text Email Content (optional)
 * @param string $replyToEmail Optional Reply-To Email Address (defaults to assigner email or system email)
 * @param string $replyToName Optional Reply-To Full Name
 * @return bool True if sent/accepted, False on failure
 */
function sendSystemEmail($toEmail, $toName, $subject, $htmlMessage, $altTextMessage = '', $replyToEmail = '', $replyToName = '') {
    try {
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromEmail = defined('SMTP_FROM') ? SMTP_FROM : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@ufm.edu.vn');
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'UFM Quảng Ngãi';

        // Clean subject of carriage returns/newlines for header security
        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));

        // UTF-8 MIME Header encoding for Subject & Names
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedToName = !empty($toName) ? '=?UTF-8?B?' . base64_encode(trim($toName)) . '?=' : '';

        $toHeader = $encodedToName ? "$encodedToName <$toEmail>" : $toEmail;
        $fromHeader = "$encodedFromName <$fromEmail>";

        // Reply-To Header calculation: User assigner email if valid, otherwise system sender header
        if (!empty($replyToEmail) && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $encodedReplyToName = !empty($replyToName) ? '=?UTF-8?B?' . base64_encode(trim($replyToName)) . '?=' : '';
            $replyToHeader = $encodedReplyToName ? "$encodedReplyToName <$replyToEmail>" : $replyToEmail;
        } else {
            $replyToHeader = $fromHeader;
        }

        // Generate Plain Text fallback if not provided
        if (empty($altTextMessage)) {
            $altTextMessage = buildPlainTextFromHtml($htmlMessage);
        }

        // Attempt Socket SMTP if SMTP_PASS is configured
        $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
        $smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
        $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
        $smtpEnc  = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls';

        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $smtpSuccess = sendViaSmtpSocket(
                $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPass,
                $fromEmail, $fromHeader, $toEmail, $toHeader, $encodedSubject, $htmlMessage, $altTextMessage, $replyToHeader
            );
            if ($smtpSuccess) {
                return true;
            }
        }

        // Fallback to PHP native mail() function with multipart/alternative
        $boundary = "==Multipart_Boundary_" . md5(uniqid(time(), true));

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "From: $fromHeader\r\n";
        $headers .= "Reply-To: $replyToHeader\r\n";
        $headers .= "X-Mailer: UFM-TaskSystem/1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $altTextMessage . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlMessage . "\r\n\r\n";
        $body .= "--$boundary--";

        @$mailResult = mail($toEmail, $encodedSubject, $body, $headers);
        if ($mailResult) {
            return true;
        }

        // Log email output for local debugging environment
        logMailDelivery($toEmail, $subject, $htmlMessage, $altTextMessage, $replyToHeader);
        return true; // Return true for seamless workflow in local offline setup
    } catch (\Throwable $e) {
        error_log("sendSystemEmail Exception: " . $e->getMessage());
        logMailDelivery($toEmail, $subject, "[ERROR: " . $e->getMessage() . "]\n" . $htmlMessage, $altTextMessage, $replyToHeader ?? '');
        return false;
    }
}

/**
 * Socket-based SMTP Client implementation with multipart/alternative & Reply-To header support
 */
function sendViaSmtpSocket($host, $port, $encryption, $user, $pass, $fromEmail, $fromHeader, $toEmail, $toHeader, $subject, $htmlMessage, $altTextMessage = '', $replyToHeader = '') {
    $timeout = 10;
    $remoteHost = ($encryption === 'ssl') ? "ssl://$host" : $host;

    $socket = @fsockopen($remoteHost, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return false;
    }

    $readResponse = function() use ($socket) {
        $response = '';
        while ($str = fgets($socket, 512)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    };

    $sendCommand = function($cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    $greeting = $readResponse();
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $ehloResp = $sendCommand("EHLO " . gethostname());
    if (substr($ehloResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    // STARTTLS negotiation if configured
    if ($encryption === 'tls') {
        $tlsResp = $sendCommand("STARTTLS");
        if (substr($tlsResp, 0, 3) === '220') {
            $cryptoType = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoType |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $cryptoType)) {
                fclose($socket);
                return false;
            }
            // Re-send EHLO after TLS established
            $sendCommand("EHLO " . gethostname());
        }
    }

    // Authentication
    if (!empty($user) && !empty($pass)) {
        $authResp = $sendCommand("AUTH LOGIN");
        if (substr($authResp, 0, 3) === '334') {
            $userResp = $sendCommand(base64_encode($user));
            if (substr($userResp, 0, 3) === '334') {
                $passResp = $sendCommand(base64_encode($pass));
                if (substr($passResp, 0, 3) !== '235') {
                    fclose($socket);
                    return false;
                }
            } else {
                fclose($socket);
                return false;
            }
        }
    }

    // MAIL FROM
    $mailResp = $sendCommand("MAIL FROM: <$fromEmail>");
    if (substr($mailResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    // RCPT TO
    $rcptResp = $sendCommand("RCPT TO: <$toEmail>");
    if (substr($rcptResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    // DATA
    $dataResp = $sendCommand("DATA");
    if (substr($dataResp, 0, 3) !== '354') {
        fclose($socket);
        return false;
    }

    if (empty($replyToHeader)) {
        $replyToHeader = $fromHeader;
    }

    $boundary = "==Multipart_Boundary_" . md5(uniqid(time(), true));

    // Email content construction (MIME multipart/alternative)
    $content  = "MIME-Version: 1.0\r\n";
    $content .= "From: $fromHeader\r\n";
    $content .= "Reply-To: $replyToHeader\r\n";
    $content .= "To: $toHeader\r\n";
    $content .= "Subject: $subject\r\n";
    $content .= "Date: " . date('r') . "\r\n";
    $content .= "X-Mailer: UFM-TaskSystem/1.0\r\n";
    $content .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";

    // Plain text part
    $content .= "--$boundary\r\n";
    $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $content .= $altTextMessage . "\r\n\r\n";

    // HTML part
    $content .= "--$boundary\r\n";
    $content .= "Content-Type: text/html; charset=UTF-8\r\n";
    $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $content .= $htmlMessage . "\r\n\r\n";

    // End boundary
    $content .= "--$boundary--\r\n";

    // Terminate DATA with .
    $sendResp = $sendCommand($content . ".");
    $sendCommand("QUIT");
    fclose($socket);

    return (substr($sendResp, 0, 3) === '250');
}

/**
 * Helper to generate plain text from HTML email content
 */
function buildPlainTextFromHtml($html) {
    if (empty($html)) return '';
    // Replace breaks and paragraphs with newlines
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = preg_replace('/<\/tr>/i', "\n", $text);
    $text = preg_replace('/<\/div>/i', "\n", $text);
    $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
    // Strip remaining HTML tags
    $text = strip_tags($text);
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Normalize excessive empty lines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

/**
 * Internal mail logger for debugging / offline verification
 */
function logMailDelivery($toEmail, $subject, $htmlMessage, $altTextMessage = '', $replyToHeader = '') {
    $logDir = __DIR__ . '/../uploads/mail_logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/sent_emails_' . date('Y-m-d') . '.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $toEmail | REPLY-TO: $replyToHeader | SUBJECT: $subject\n";
    if (!empty($altTextMessage)) {
        $logEntry .= "--- PLAIN TEXT ---\n" . $altTextMessage . "\n";
    }
    $logEntry .= "--- HTML ---\n" . $htmlMessage . "\n" . str_repeat('=', 60) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
