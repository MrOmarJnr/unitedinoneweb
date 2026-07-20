// Quick check that Resend + Vercel Blob are configured correctly.
// Run:  node test-setup.js your-own-email@example.com
// (Use the SAME email you logged into Resend/GitHub with, because the test
//  sender onboarding@resend.dev can only deliver to your own Resend address.)
require('dotenv').config();
const { Resend } = require('resend');
const { put, get } = require('@vercel/blob');

const to = process.argv[2];
if (!to) { console.error('Usage: node test-setup.js you@example.com'); process.exit(1); }

(async () => {
  // 1) Resend — sends one test email
  try {
    const resend = new Resend(process.env.RESEND_API_KEY);
    const r = await resend.emails.send({
      from: process.env.MAIL_FROM || 'onboarding@resend.dev',
      to,
      subject: 'UIOGF portal — test email',
      html: '<p>If you can read this, <b>Resend is working</b>. ✅</p>',
    });
    if (r.error) console.log('❌ Resend error:', JSON.stringify(r.error));
    else console.log('✅ Resend: email sent to', to, '(id ' + (r.data && r.data.id) + ')');
  } catch (e) { console.log('❌ Resend failed:', e.message); }

  // 2) Vercel Blob — uploads a tiny file (private) and reads it back
  try {
    const token = process.env.BLOB_READ_WRITE_TOKEN;
    const pathname = 'test/hello-' + Date.now() + '.txt';
    await put(pathname, 'Hello from UIOGF at ' + new Date().toISOString(), {
      access: 'private', token, addRandomSuffix: false, contentType: 'text/plain',
    });
    console.log('✅ Blob: uploaded', pathname);
    const g = await get(pathname, { access: 'private', token });
    console.log('✅ Blob: read back OK (contentType ' + (g && g.blob && g.blob.contentType) + ')');
  } catch (e) { console.log('❌ Blob failed:', e.message); }

  console.log('\nDone. Any ✅ means that service is set up correctly.');
})();
