<?php
// mailer/send.php — receives the website forms, emails via Resend, saves a record.
// Works on any host with PHP + cURL (standard cPanel). No Node / npm needed.
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

// ---- CORS (harmless when same-origin) ----
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['ALLOWED_ORIGINS'] ?? ['*'];
if (in_array('*', $allowed, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'POST only']); exit; }

// ---- Which form is this? ----
$type = $_POST['form_type'] ?? ($_GET['type'] ?? 'general');
$fields = $_POST;
unset($fields['form_type']);

$labels = [
    'contact'     => ['Contact Message',      'message'],
    'opportunity' => ['Volunteer Application', 'volunteer application'],
    'support'     => ['Support Request',       'community support request'],
    'partnership' => ['Partnership Inquiry',   'partnership inquiry'],
    'general'     => ['Website Submission',    'submission'],
];
[$label, $noun] = $labels[$type] ?? $labels['general'];

function firstOf(array $a, array $keys): string {
    foreach ($keys as $k) { if (isset($a[$k]) && trim((string)$a[$k]) !== '') return trim((string)$a[$k]); }
    return '';
}
$senderEmail = firstOf($fields, ['Email','email','contact_email','Email Address']);
$senderName  = firstOf($fields, ['Full Name','full_name','first_name','contact_name','name','Organization Name','organization_name']);
if ($senderName === '') $senderName = 'there';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ---- Resend send helper ----
// $to may be a single address (string) or several (array). Resend takes a list.
function resendSend(array $config, $to, string $subject, string $html, ?string $replyTo = null): array {
    $toList = is_array($to) ? array_values($to) : [$to];
    $payload = ['from' => $config['MAIL_FROM'], 'to' => $toList, 'subject' => $subject, 'html' => $html];
    if ($replyTo) $payload['reply_to'] = $replyTo;
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $config['RESEND_API_KEY'], 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'resp' => (string)$resp, 'err' => $err];
}

$logDir = __DIR__ . '/submissions';
@mkdir($logDir, 0775, true);
function logLine(string $dir, string $msg): void {
    @file_put_contents($dir . '/mail.log', '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// ---- Save the full submission so nothing is lost, even if email hiccups ----
$fileNames = [];
if (!empty($_FILES)) {
    foreach ($_FILES as $f) {
        if (is_array($f['name'])) { foreach ($f['name'] as $n) if ($n) $fileNames[] = $n; }
        elseif (!empty($f['name'])) { $fileNames[] = $f['name']; }
    }
}
$record = ['type' => $type, 'submittedAt' => date('c'), 'fields' => $fields, 'files' => $fileNames];
@file_put_contents(
    $logDir . '/' . preg_replace('/[^a-z]/', '', $type) . '-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.json',
    json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$mailOk = true;

// 1) Confirmation to the person who submitted
if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
    $html = '<div style="font-family:Inter,Arial,sans-serif;color:#1b1b1b;max-width:520px">'
          . '<h2 style="color:#2D4A3E">Thank you, ' . e($senderName) . '!</h2>'
          . '<p>Your <strong>' . e($noun) . '</strong> has been submitted successfully.</p>'
          . '<p>Our team will review it and get back to you soon. You don\'t need to do anything else right now.</p>'
          . '<p style="margin-top:24px;color:#6D6D6D">With gratitude,<br>United in One Global Foundation</p></div>';
    $r = resendSend($config, $senderEmail, 'We\'ve received your ' . $noun . ' — United in One Global Foundation', $html);
    if (!$r['ok']) { $mailOk = false; logLine($logDir, 'sender FAIL ' . $r['code'] . ' ' . $r['resp'] . ' ' . $r['err']); }
}

// 2) Alert to the admin
if (!empty($config['ADMIN_FULL_DETAILS'])) {
    $rows = '';
    foreach ($fields as $k => $v) {
        if (is_array($v)) $v = implode(', ', $v);
        $rows .= '<tr><td style="padding:3px 12px 3px 0;color:#6D6D6D;vertical-align:top">' . e((string)$k)
               . '</td><td>' . nl2br(e((string)$v)) . '</td></tr>';
    }
    $note = $fileNames ? (count($fileNames) . ' file(s): ' . e(implode(', ', $fileNames))) : 'No files attached.';
    $adminHtml = '<div style="font-family:Inter,Arial,sans-serif;color:#1b1b1b;max-width:640px">'
               . '<h3 style="color:#2D4A3E">New ' . e($label) . '</h3>'
               . '<p>A new ' . e($noun) . ' came in from the website (' . date('M j, Y g:i A') . ').</p>'
               . '<table style="font-size:14px;border-collapse:collapse">' . $rows . '</table>'
               . '<p style="color:#6D6D6D;font-size:13px;margin-top:12px">' . $note . '</p></div>';
} else {
    $adminHtml = '<div style="font-family:Inter,Arial,sans-serif;color:#1b1b1b;max-width:520px">'
               . '<h3 style="color:#2D4A3E">New ' . e($label) . ' submitted</h3>'
               . '<p>A new ' . e($noun) . ' just came in from ' . e($senderName)
               . ($senderEmail ? ' (' . e($senderEmail) . ')' : '') . '. Open the portal to view the details.</p></div>';
}
// Support one or more admin addresses, separated by ";" or ","
$adminList = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', (string)$config['ADMIN_EMAIL']))));
$r2 = resendSend($config, $adminList, 'New ' . $label . ' submitted', $adminHtml, $senderEmail ?: null);
if (!$r2['ok']) { $mailOk = false; logLine($logDir, 'admin FAIL ' . $r2['code'] . ' ' . $r2['resp'] . ' ' . $r2['err']); }

// The visitor always sees success once we've safely received + saved the submission.
echo json_encode(['ok' => true, 'emailed' => $mailOk]);
