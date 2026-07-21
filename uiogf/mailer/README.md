# PHP mailer (Resend)

A tiny server-side endpoint the website forms post to. It emails a confirmation
to the submitter and an alert to the admin **via Resend**, and saves a JSON copy
of each submission in `submissions/`. Works on any host with PHP + cURL
(standard cPanel) — **no Node, no npm**.

## Setup
1. Upload this `mailer/` folder alongside the website (same domain).
2. Open `config.php` and set:
   - `RESEND_API_KEY` (from resend.com → API Keys)
   - `MAIL_FROM` — must use a domain verified in Resend
     (currently `noreply@noreply.allrounditsol.com`, which is verified)
   - `ADMIN_EMAIL` — where alerts go (use YOUR inbox for the first test)

That's it. The forms already point to `mailer/send.php`.

## Test locally (needs PHP on your machine — nothing else)
From the website root:
```
php -S localhost:8000
```
Then open http://localhost:8000/contact-us.html, submit the form, and check
your inbox. Or test the endpoint directly:
```
curl -X POST http://localhost:8000/mailer/send.php \
  -d "form_type=contact" -d "Full Name=Test" -d "Email=you@yourdomain.com" -d "message=Hello"
```
Response `{"ok":true,"emailed":true}` means both emails sent. If `emailed` is
false, the reason is logged in `submissions/mail.log`.

## Notes
- The visitor always sees "submitted successfully" once the submission is saved,
  even if an email hiccups — so a mail glitch never blocks the user. Failures are
  logged for you.
- `config.php` and `submissions/` are blocked from the web by `.htaccess`.
- File uploads (CVs/docs) are received and their names recorded now; storing the
  actual files in Vercel Blob is the next step.
- **Rotate the Resend key** if it was ever shared in plain text.
