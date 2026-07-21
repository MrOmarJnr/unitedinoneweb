<?php
// mailer/test-email.php — quick Resend HTML test. DELETE after use.
// Usage in browser:  /mailer/test-email.php?to=you@example.com
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/config.php';
$to = $_GET['to'] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit("Add ?to=your@email.com to the URL.\n");
}

$html =
  '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
. '<body style="margin:0;background:#f4f2ee;font-family:Arial,Helvetica,sans-serif;">'
. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px;">'
. '<tr><td align="center"><table width="600" style="max-width:600px;">'
. '<tr><td style="background:#2D4A3E;padding:24px;text-align:center;border-radius:14px 14px 0 0;">'
. '<span style="color:#fff;font-size:20px;font-weight:bold;">United in One <span style="color:#D4933A;">Global Foundation</span></span></td></tr>'
. '<tr><td style="height:4px;background:#D4933A;font-size:0;">&nbsp;</td></tr>'
. '<tr><td style="background:#fff;padding:30px;text-align:center;border-radius:0 0 14px 14px;">'
. '<h1 style="color:#2D4A3E;font-size:22px;">HTML rendering works.</h1>'
. '<p style="color:#555;">If you can read this as a styled message (green header, gold stripe) '
. 'and NOT as raw &lt;table&gt; tags, Resend is delivering HTML correctly.</p>'
. '</td></tr></table></td></tr></table></body></html>';

$payload = [
    'from'    => $config['MAIL_FROM'],
    'to'      => [$to],
    'subject' => 'Resend HTML test — United in One',
    'html'    => $html,
];

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $config['RESEND_API_KEY'],
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 25,
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
echo $err ? ("cURL error: $err\n") : '';
echo "Resend response: $resp\n";
echo "\nNow check the inbox for '$to'. It should be a STYLED email, not raw tags.\n";
