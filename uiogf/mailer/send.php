<?php
// mailer/send.php — receives the website forms, emails via Resend, saves a record.
// Works on any host with PHP + cURL. No Node / npm needed.
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

// ---- Branded email wrapper (table-based + inline styles for email clients) ----
function emailShell(string $bodyHtml): string {
    return
      '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
    . '<body style="margin:0;padding:0;background:#f4f2ee;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ee;padding:24px 12px;">'
    . '<tr><td align="center">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;font-family:Arial,Helvetica,sans-serif;">'
    . '<tr><td style="background:#2D4A3E;padding:26px 30px;text-align:center;border-radius:14px 14px 0 0;">'
    . '<div style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:.3px;">United in One <span style="color:#D4933A;">Global Foundation</span></div>'
    . '</td></tr>'
    . '<tr><td style="height:4px;background:#D4933A;font-size:0;line-height:0;">&nbsp;</td></tr>'
    . '<tr><td style="background:#ffffff;padding:34px 32px 26px;">' . $bodyHtml . '</td></tr>'
    . '<tr><td style="background:#ffffff;border-radius:0 0 14px 14px;border-top:1px solid #f0ede7;padding:18px 32px 26px;text-align:center;color:#9a9a9a;font-size:12px;line-height:1.7;">'
    . 'United in One Global Foundation &middot; 501(c)(3) nonprofit<br>'
    . '<a href="https://unitedinone.org" style="color:#9a9a9a;">unitedinone.org</a></td></tr>'
    . '</table></td></tr></table></body></html>';
}

// ---- Resend send helper (single or multiple recipients) ----
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
    $body = '<div style="text-align:center;margin:0 0 6px;">'
          . '<span style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:50%;background:#eef4f0;color:#2D4A3E;font-size:28px;font-weight:bold;">&#10003;</span></div>'
          . '<h1 style="color:#2D4A3E;font-size:23px;margin:14px 0 10px;text-align:center;">Thank you, ' . e($senderName) . '!</h1>'
          . '<p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 18px;text-align:center;">Your <strong>' . e($noun) . '</strong> has been received. Our team will review it and get back to you soon &mdash; you don&rsquo;t need to do anything else right now.</p>'
          . '<div style="background:#eef4f0;border-left:4px solid #D4933A;padding:13px 18px;border-radius:8px;color:#2D4A3E;font-size:14px;margin:0 0 24px;">We typically respond within <strong>two business days</strong>.</div>'
          . '<div style="text-align:center;margin:0 0 6px;"><a href="https://unitedinone.org" style="display:inline-block;background:#D4933A;color:#182E25;font-weight:bold;text-decoration:none;padding:12px 28px;border-radius:30px;font-size:14px;">Visit our website</a></div>'
          . '<p style="color:#9a9a9a;font-size:13px;margin:24px 0 0;text-align:center;">With gratitude,<br>The United in One team</p>';
    $r = resendSend($config, $senderEmail, 'We\'ve received your ' . $noun . ' — United in One Global Foundation', emailShell($body));
    if (!$r['ok']) { $mailOk = false; logLine($logDir, 'sender FAIL ' . $r['code'] . ' ' . $r['resp'] . ' ' . $r['err']); }
}

// 2) Alert to the admin
if (!empty($config['ADMIN_FULL_DETAILS'])) {
    $rows = '';
    foreach ($fields as $k => $v) {
        if ($k === '' || $k[0] === '_') continue;          // skip internal / relay fields
        if (is_array($v)) $v = implode(', ', $v);
        if (trim((string)$v) === '') continue;             // skip empty
        $niceKey = ucwords(trim(str_replace(['_', '-'], ' ', (string)$k)));
        $rows .= '<tr>'
               . '<td style="padding:9px 14px;border:1px solid #eee;background:#f7f5f0;color:#2D4A3E;font-weight:bold;vertical-align:top;white-space:nowrap;">' . e($niceKey) . '</td>'
               . '<td style="padding:9px 14px;border:1px solid #eee;color:#1b1b1b;">' . nl2br(e((string)$v)) . '</td>'
               . '</tr>';
    }
    $note = $fileNames ? (count($fileNames) . ' file(s): ' . e(implode(', ', $fileNames))) : 'No files attached.';
    $body = '<h2 style="color:#2D4A3E;font-size:21px;margin:0 0 4px;">New ' . e($label) . '</h2>'
          . '<p style="color:#9a9a9a;font-size:13px;margin:0 0 20px;">Received ' . date('M j, Y \a\t g:i A') . '</p>'
          . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table>'
          . '<p style="color:#9a9a9a;font-size:13px;margin-top:16px;">' . $note . '</p>';
} else {
    $body = '<h2 style="color:#2D4A3E;font-size:21px;margin:0 0 10px;">New ' . e($label) . ' submitted</h2>'
          . '<p style="color:#555;font-size:15px;line-height:1.7;">A new ' . e($noun) . ' just came in from <strong>' . e($senderName) . '</strong>'
          . ($senderEmail ? ' (' . e($senderEmail) . ')' : '') . '.</p>'
          . '<p style="color:#555;font-size:15px;">Log in to the portal to view the full details.</p>';
}
$adminList = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', (string)$config['ADMIN_EMAIL']))));
$r2 = resendSend($config, $adminList, 'New ' . $label . ' submitted', emailShell($body), $senderEmail ?: null);
if (!$r2['ok']) { $mailOk = false; logLine($logDir, 'admin FAIL ' . $r2['code'] . ' ' . $r2['resp'] . ' ' . $r2['err']); }

// The visitor always sees success once we've safely received + saved the submission.
echo json_encode(['ok' => true, 'emailed' => $mailOk]);
