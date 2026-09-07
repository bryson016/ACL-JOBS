/**
 * Uploads route. Uses Prisma ORM to update profile rows.
 *
 * POST /api/uploads/upload?type=profile_photo|cv
 * Accepts multipart/form-data with a "file" field. Stores the file under
 * uploads/{type}/ and updates the user's profile row.
 *
 * Also serves static files at /api/uploads/files/{type}/{filename} so the
 * frontend can load previously uploaded images / CVs.
 */

const express = require('express');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const multer = require('multer');
const { sendSuccess, sendError } = require('../config/functions');
const { requireAuth } = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALLOWED_DOC_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

const UPLOAD_BASE_DIR = path.resolve(__dirname, '..', 'uploads');
const UPLOAD_BASE_URL = '/api/uploads/files';

const storage = multer.memoryStorage();
const upload = multer({
  storage,
  limits: { fileSize: MAX_FILE_SIZE },
});

/**
 * POST /api/uploads/upload?type=...
 */
router.post(['/upload', '/upload.php'], upload.single('file'), async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  if (!req.file) {
    return sendError(res, 'No file was uploaded.', 400);
  }

  const fileType = (req.query.type || req.body.type || 'profile_photo').toString();
  const allowedTypes =
    fileType === 'cv' ? ALLOWED_DOC_TYPES : ALLOWED_IMAGE_TYPES;

  if (!allowedTypes.includes(req.file.mimetype)) {
    return sendError(
      res,
      `Invalid file type. Allowed types: ${allowedTypes.join(', ')}`,
      422
    );
  }
  if (req.file.size > MAX_FILE_SIZE) {
    return sendError(
      res,
      `File is too large. Maximum size is ${MAX_FILE_SIZE / 1024 / 1024}MB.`,
      422
    );
  }

  try {
    const uploadDir = path.join(UPLOAD_BASE_DIR, fileType);
    await fs.promises.mkdir(uploadDir, { recursive: true });

    const ext = path.extname(req.file.originalname || '') || guessExt(req.file.mimetype);
    const filename = `${user.user_id}_${Date.now()}_${crypto
      .randomBytes(4)
      .toString('hex')}${ext}`;
    const filePath = path.join(uploadDir, filename);
    await fs.promises.writeFile(filePath, req.file.buffer);

    const fileUrl = `${UPLOAD_BASE_URL}/${fileType}/${filename}`;

    if (fileType === 'profile_photo') {
      await prisma.profiles.upsert({
        where: { user_id: Number(user.user_id) },
        update: { profile_photo: fileUrl },
        create: {
          user_id: Number(user.user_id),
          full_name: '',
          profile_photo: fileUrl,
        },
      });
    } else if (fileType === 'cv') {
      await prisma.profiles.upsert({
        where: { user_id: Number(user.user_id) },
        update: { cv_url: fileUrl },
        create: {
          user_id: Number(user.user_id),
          full_name: '',
          cv_url: fileUrl,
        },
      });
    }

    return sendSuccess(
      res,
      {
        url: fileUrl,
        filename,
        size: req.file.size,
        type: req.file.mimetype,
      },
      'File uploaded successfully'
    );
  } catch (err) {
    console.error('File upload failed:', err.message);
    return sendError(res, 'An error occurred while uploading the file.', 500);
  }
});

function guessExt(mime) {
  if (mime === 'image/jpeg') return '.jpg';
  if (mime === 'image/png') return '.png';
  if (mime === 'image/gif') return '.gif';
  if (mime === 'image/webp') return '.webp';
  if (mime === 'application/pdf') return '.pdf';
  if (mime === 'application/msword') return '.doc';
  if (mime ===
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
    return '.docx';
  }
  return '';
}

module.exports = router;
