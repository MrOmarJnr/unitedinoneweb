<?php
// mailer/config.php — SECRET. Protected by .htaccess (never served as text).
// If this key is ever exposed, rotate it in Resend and update here.
return [
    // Resend API key (https://resend.com -> API Keys)
    'RESEND_API_KEY'   => 're_LXP5dyuy_P7eXPvLwRsEyGQFUGUzapMAz',

    // "From" address — must be on a domain VERIFIED in Resend.
    // noreply.allrounditsol.com is verified, so any name@noreply.allrounditsol.com works.
    'MAIL_FROM'        => 'United in One Global Foundation <noreply@noreply.allrounditsol.com>',

    // Who receives the admin alert. For a first test, set this to YOUR own inbox
    // so you can confirm delivery, then change it to the real address.
    'ADMIN_EMAIL'      => 'emmanuel.appiah.dev@gmail.com;info@unitedinone.org',

    // true  = admin email includes the full submission (useful until the portal exists)
    // false = admin email is just "a new X was submitted" (details live in the portal)
    'ADMIN_FULL_DETAILS' => true,

    // Website origins allowed to POST here. Same-origin needs nothing; '*' is fine
    // for a public contact form. Tighten to your domain(s) if you prefer.
    'ALLOWED_ORIGINS'  => ['*'],
];
