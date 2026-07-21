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

    // Vercel Blob — stores uploaded CVs/documents for the portal.
    'BLOB_READ_WRITE_TOKEN' => 'vercel_blob_rw_your_token_here',
    'BLOB_ACCESS'           => 'private',
    'BLOB_API_VERSION'      => '10',
    'BLOB_PREFIX'           => 'uioogf',
];
