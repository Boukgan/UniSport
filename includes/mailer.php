<?php
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function mail_config() {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/mail_config.php';
    return $cfg;
}

function app_base_url() {
    $cfg = mail_config();
    if (!empty($cfg['app_url'])) return rtrim($cfg['app_url'], '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($script,-6)==='/admin') $script = substr($script,0,-6);
    return $scheme.'://'.$host.rtrim($script,'/');
}

function send_mail($to, $subject, $htmlBody, $textBody = '') {
    $cfg = mail_config();
    if (!empty($cfg['dev_mode'])) {
        $logFile = sys_get_temp_dir() . '/unisport_mail_log.txt';
        $entry  = "==== ".date('Y-m-d H:i:s')." ====\nTO: $to\nSUBJECT: $subject\n\n".strip_tags($htmlBody)."\n\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
        return [true, 'dev-mode: message logged to uploads/_mail_log.txt'];
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$cfg['port'];
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->Timeout = 10;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        $mail->send();
        return [true, 'Message sent successfully'];
    } catch (Exception $e) {
        $errMsg = !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
        return [false, "Message could not be sent. Mailer Error: $errMsg" ];
    }
}

function email_template($title, $bodyHTML) {
    return '<div style="font-family:Arial,sans-serif;background:#f0f5ff;padding:24px">
      <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #ccdaf5">
        <div style="background:#003b8e;color:#fff;padding:18px 22px;font-weight:700;font-size:18px">
          Uni<span style="color:#5ba3f5">Sport</span> · UTeM
        </div>
        <div style="padding:24px 22px;color:#003b8e">
          <h2 style="margin:0 0 10px;font-size:20px;color:#003b8e">'.htmlspecialchars($title).'</h2>
          <div style="font-size:14px;line-height:1.7;color:#1f2a44">'.$bodyHTML.'</div>
        </div>
        <div style="padding:14px 22px;background:#f0f5ff;font-size:12px;color:#6b7fa3">UniSport · UTeM Sports Centre</div>
      </div>
    </div>';
}