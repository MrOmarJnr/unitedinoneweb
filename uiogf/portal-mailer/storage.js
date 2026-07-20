// storage.js — stores uploaded files PRIVATELY in Vercel Blob and fetches them
// back (authenticated) for the portal. Requires BLOB_READ_WRITE_TOKEN.
const { put, get } = require('@vercel/blob');

async function uploadFiles(type, files) {
  const token = process.env.BLOB_READ_WRITE_TOKEN;
  const results = [];
  for (const f of files || []) {
    const safe = (f.originalname || 'file').replace(/[^a-zA-Z0-9._-]/g, '_');
    const pathname = `${type}/${Date.now()}-${safe}`;
    await put(pathname, f.buffer, {
      access: 'private',            // documents are not publicly downloadable
      token,
      contentType: f.mimetype,
      addRandomSuffix: false,
    });
    // Store the pathname; the file is served later via /api/file (authenticated)
    results.push({ field: f.fieldname, name: f.originalname, pathname });
  }
  return results;
}

// Fetch a private blob by its pathname (used by the authenticated serve route)
async function getFile(pathname) {
  const token = process.env.BLOB_READ_WRITE_TOKEN;
  return get(pathname, { access: 'private', token });
}

module.exports = { uploadFiles, getFile };
