// mailer.js — sends emails via Resend (https://resend.com).
// A short confirmation to the person who submitted, and a brief alert to the admin.
const { Resend } = require('resend');
const resend = new Resend(process.env.RESEND_API_KEY);

const TYPES = {
  opportunity: { label: 'Volunteer Application', noun: 'volunteer application' },
  support:     { label: 'Support Request',       noun: 'community support request' },
  partnership: { label: 'Partnership Inquiry',   noun: 'partnership inquiry' },
};

function pickEmail(b) {
  return b.email || b.Email || b.contact_email || b['Email Address'] || '';
}
function pickName(b) {
  return b.full_name || b['Full Name'] || b.contact_name || b.name ||
         b.organization_name || b['Organization Name'] || 'there';
}
function esc(s) {
  return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

async function sendSubmissionEmails(type, body, files) {
  const meta = TYPES[type] || { label: 'Request', noun: 'request' };
  const from = process.env.MAIL_FROM || 'onboarding@resend.dev';
  const adminEmail = process.env.ADMIN_EMAIL;
  const senderEmail = pickEmail(body);
  const senderName = pickName(body);
  const portalUrl = process.env.PORTAL_URL || '';
  const out = {};

  // 1) Confirmation to the submitter (no sensitive details echoed back)
  if (senderEmail) {
    out.sender = await resend.emails.send({
      from,
      to: senderEmail,
      subject: `We've received your ${meta.noun} — United in One Global Foundation`,
      html:
`<div style="font-family:Inter,Arial,sans-serif;color:#1b1b1b;max-width:520px">
  <h2 style="color:#2D4A3E">Thank you, ${esc(senderName)}!</h2>
  <p>Your <strong>${meta.noun}</strong> has been submitted successfully.</p>
  <p>Our team will review it and get back to you soon. You don't need to do anything else right now.</p>
  <p style="margin-top:24px;color:#6D6D6D">With gratitude,<br>United in One Global Foundation<br>
  <a href="mailto:info@unitedinone.org">info@unitedinone.org</a></p>
</div>`,
    });
  }

  // 2) Brief alert to the admin — only a little info; full details live in the portal
  if (adminEmail) {
    const fileNote = files && files.length ? `${files.length} file(s) attached (stored in the portal).` : 'No files attached.';
    out.admin = await resend.emails.send({
      from,
      to: adminEmail,
      replyTo: senderEmail || undefined,
      subject: `New ${meta.label} submitted`,
      html:
`<div style="font-family:Inter,Arial,sans-serif;color:#1b1b1b;max-width:520px">
  <h3 style="color:#2D4A3E">New ${esc(meta.label)} submitted</h3>
  <p>A new ${meta.noun} just came in through the website.</p>
  <table style="font-size:14px;color:#333">
    <tr><td style="padding:2px 10px 2px 0;color:#6D6D6D">From</td><td>${esc(senderName)}${senderEmail ? ' &lt;'+esc(senderEmail)+'&gt;' : ''}</td></tr>
    <tr><td style="padding:2px 10px 2px 0;color:#6D6D6D">Type</td><td>${esc(meta.label)}</td></tr>
    <tr><td style="padding:2px 10px 2px 0;color:#6D6D6D">Time</td><td>${new Date().toLocaleString()}</td></tr>
    <tr><td style="padding:2px 10px 2px 0;color:#6D6D6D">Files</td><td>${esc(fileNote)}</td></tr>
  </table>
  <p style="margin-top:18px">
    <a href="${portalUrl}" style="background:#D4933A;color:#182E25;font-weight:700;padding:10px 18px;border-radius:8px;text-decoration:none">Open the portal</a>
  </p>
  <p style="color:#9a9a9a;font-size:12px">Full details are intentionally not included in this email.</p>
</div>`,
    });
  }
  return out;
}

module.exports = { sendSubmissionEmails };
