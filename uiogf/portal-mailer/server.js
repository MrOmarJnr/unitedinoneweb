// server.js — submission service for the UIOGF portal.
// Website forms POST here. Files go to Vercel Blob (private), emails go out via
// Resend, and a JSON record (with the blob pathnames) is saved. Run: node server.js
require('dotenv').config();
const express = require('express');
const cors = require('cors');
const multer = require('multer');
const fs = require('fs');
const path = require('path');
const { Readable } = require('stream');
const { sendSubmissionEmails } = require('./mailer');
const { uploadFiles, getFile } = require('./storage');

const app = express();

const origins = (process.env.ALLOWED_ORIGINS || '*').split(',').map(s => s.trim());
app.use(cors({ origin: origins.includes('*') ? true : origins }));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Files held in memory, then streamed straight to Vercel Blob (no local disk)
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 10 * 1024 * 1024 } });

// JSON record of each submission (swap for your DB if you have one)
const dataDir = path.join(__dirname, 'submissions');
fs.mkdirSync(dataDir, { recursive: true });
function saveRecord(type, body, files) {
  const record = { type, submittedAt: new Date().toISOString(), fields: body, files };
  fs.writeFileSync(path.join(dataDir, `${type}-${Date.now()}.json`), JSON.stringify(record, null, 2));
}

const VALID = ['opportunity', 'support', 'partnership'];

// ---- Receive a submission ----
app.post('/api/submit/:type', upload.any(), async (req, res) => {
  const { type } = req.params;
  if (!VALID.includes(type)) return res.status(404).json({ ok: false, error: 'Unknown submission type' });
  try {
    let files = [];
    try { files = await uploadFiles(type, req.files); }
    catch (e) { console.error('Blob upload failed:', e.message); }

    saveRecord(type, req.body, files);

    try { await sendSubmissionEmails(type, req.body, files); }
    catch (e) { console.error('Email send failed:', e.message); }

    return res.json({ ok: true, message: 'Submission received' });
  } catch (err) {
    console.error('Submission error:', err);
    return res.status(500).json({ ok: false, error: 'Could not process submission' });
  }
});

// ---- Serve a stored file (authenticated) ----
// e.g. GET /api/file?pathname=opportunity/123-resume.pdf&key=YOUR_ADMIN_FILE_KEY
app.get('/api/file', async (req, res) => {
  if (process.env.ADMIN_FILE_KEY && req.query.key !== process.env.ADMIN_FILE_KEY) {
    return res.status(401).send('Unauthorized');
  }
  const pathname = req.query.pathname;
  if (!pathname) return res.status(400).json({ error: 'Missing pathname' });
  try {
    const result = await getFile(pathname);
    if (!result) return res.status(404).send('Not found');
    res.setHeader('Content-Type', (result.blob && result.blob.contentType) || 'application/octet-stream');
    res.setHeader('Cache-Control', 'private, no-cache');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    if (result.stream) return Readable.fromWeb(result.stream).pipe(res);
    return res.status(500).send('No stream');
  } catch (e) {
    console.error('File fetch error:', e.message);
    return res.status(500).send('Error');
  }
});

app.get('/health', (_req, res) => res.json({ ok: true }));

const PORT = Number(process.env.PORT || 3000);
app.listen(PORT, () => console.log(`UIOGF portal listening on :${PORT}`));
