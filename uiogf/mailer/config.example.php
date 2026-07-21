<?php
// mailer/config.example.php — SAFE TEMPLATE (commit this, NOT config.php).
// Copy this file to config.php on the server and fill in the real values.
// config.php is git-ignored so your secret key never gets pushed.
return [
    'RESEND_API_KEY'     => 're_your_new_key_here',
    'MAIL_FROM'          => 'United in One Global Foundation <noreply@noreply.allrounditsol.com>',
    'ADMIN_EMAIL'        => 'emmanuel.appiah.dev@gmail.com;info@unitedinone.org',
    'ADMIN_FULL_DETAILS' => true,
    'ALLOWED_ORIGINS'    => ['*'],

    // Private file storage — absolute path OUTSIDE the web root.
    // Holds uploads/ and submissions/. The portal reads from here.
    'PRIVATE_DIR'           => '/var/www/uiogf-private',
];
