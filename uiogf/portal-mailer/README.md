# UIOGF Portal Mailer

Backend for the website's submission forms. When someone submits **Apply**,
**Request Support**, or **Partnership**, the form POSTs here and this service:

1. Uploads any files (CVs, supporting docs) to **Vercel Blob** and gets back a URL.
2. Saves a JSON record of the submission (including those file URLs).
3. Emails the **sender** a short "we received it" confirmation via **Resend**.
4. Emails the **admin** a brief alert via **Resend** (just who + type + a link;
   full details stay in the portal).

> This is the **portal side**, not the website. Deploy it on the server at
> `54.211.205.141`. The website just points its forms at it.

## Endpoints
- `POST /api/submit/opportunity`  ← Apply form
- `POST /api/submit/support`      ← Request Support form
- `POST /api/submit/partnership`  ← Partnership form
- `GET  /health`                  ← is it running?

---

## One-time setup

### 0. Install
```bash
cd portal-mailer
npm install
cp .env.example .env      # then fill in the values below
```

### 1. Resend (sends the emails) — https://resend.com
1. Go to resend.com and **Login → Continue with GitHub**.
2. Left menu → **API Keys → Create API Key** (name it "UIOGF portal"). Copy it
   (starts with `re_`) and paste into `.env` as `RESEND_API_KEY`.
3. Left menu → **Domains → Add Domain → unitedinone.org**. Resend shows a few DNS
   records (SPF/DKIM). Add them in your domain's DNS. Once it says **Verified**,
   emails can be sent **from** `info@unitedinone.org`. Set `MAIL_FROM` in `.env`.
   - Not ready to verify the domain yet? Use `MAIL_FROM=onboarding@resend.dev`
     to test — but that test sender can only email **your own** Resend login
     address. Switch to the real domain before go-live.

### 2. Vercel Blob (stores uploaded files) — https://vercel.com/storage/blob
1. Go to vercel.com and **Login → Continue with GitHub**.
2. **Storage → your Blob store** (create one if needed).
3. Open the store → **`.env.local`** tab (or Project → Settings → Environment
   Variables) → copy **`BLOB_READ_WRITE_TOKEN`** (starts with `vercel_blob_rw_`)
   and paste into `.env`.

### 3. Fill the rest of `.env`
- `ADMIN_EMAIL` — who receives the brief alerts (e.g. info@unitedinone.org)
- `ALLOWED_ORIGINS` — the website URL(s)
- `PORT` — 3000 (matches the form URLs on the website)

---

## Run it
```bash
node server.js
# or keep it running with pm2:
npm install -g pm2
pm2 start server.js --name uiogf-portal
pm2 save
```

## Test
```bash
curl -X POST http://localhost:3000/api/submit/partnership \
  -F "Full Name=Test User" -F "Email=you@yourdomain.com"
```
You should get `{"ok":true}`, a confirmation email at that address, and an alert
at `ADMIN_EMAIL`. (While using the Resend test sender, both must be your Resend
login email.)

## Notes
- **HTTP vs HTTPS:** the website posts to `http://54.211.205.141:3000`. If the
  website is served over https, browsers block that call — serve the site over
  http, or put this behind https and update the form URLs.
- **Already have `/api/submit` routes in the portal?** You don't need `server.js`.
  Just `require('./mailer')` and `require('./storage')` in your existing route:
  ```js
  const { uploadFiles } = require('./storage');
  const { sendSubmissionEmails } = require('./mailer');
  const files = await uploadFiles('opportunity', req.files); // multer memoryStorage
  await sendSubmissionEmails('opportunity', req.body, files);
  ```

---

## Files are stored **privately**

Uploaded CVs / documents go into Vercel Blob with `access: "private"`, so they
are **not** publicly downloadable. The submission record stores each file's
`pathname`. To view a file, an admin calls the authenticated route:

```
GET /api/file?pathname=opportunity/1699999999999-resume.pdf&key=YOUR_ADMIN_FILE_KEY
```

Set `ADMIN_FILE_KEY` in `.env` to a long random string (this is a simple guard;
swap it for your portal's real login/session check when you wire up the admin UI).

## 🔐 Security
- **Never commit `.env`** (a `.gitignore` already excludes it) and **never put the
  `portal-mailer` folder inside the public website root** — the `.env` would be
  exposed. Deploy it as a separate backend service.
- If the Resend key / Blob token were ever shared in plain text (chat, email),
  **rotate them**: regenerate in Resend (API Keys) and Vercel (Blob store tokens),
  then update `.env`.
