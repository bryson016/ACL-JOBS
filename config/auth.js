/**
 * Authentication middleware for ACL Jobs API.
 *
 * Token format is intentionally compatible with the original PHP JWT-like
 * implementation so existing tokens continue to work during migration:
 *
 *   header . payload . signature
 *
 * Where signature = HMAC-SHA256(header + '.' + payload, JWT_SECRET).
 * The token_hash stored in the auth_tokens table is SHA-256(token).
 *
 * Tokens are also looked up in the database to support revocation and
 * last-used tracking, just like the PHP version.
 *
 * Uses Prisma ORM for all database access.
 */

const crypto = require('crypto');
const { prisma } = require('./database');
const { sendError } = require('./functions');

const TOKEN_EXPIRY_SECONDS = 60 * 60 * 24 * 30; // 30 days

function base64UrlEncode(input) {
  return Buffer.from(input)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

function base64UrlDecode(input) {
  const padded = input + '='.repeat((4 - (input.length % 4)) % 4);
  return Buffer.from(
    padded.replace(/-/g, '+').replace(/_/g, '/'),
    'base64'
  ).toString('utf8');
}

/**
 * Generate a token for a user. Same shape as PHP generateJWT().
 */
function generateToken(userId, role) {
  const header = base64UrlEncode(JSON.stringify({ typ: 'JWT', alg: 'HS256' }));
  const payload = base64UrlEncode(
    JSON.stringify({
      user_id: userId,
      role,
      iat: Math.floor(Date.now() / 1000),
      exp: Math.floor(Date.now() / 1000) + TOKEN_EXPIRY_SECONDS,
    })
  );

  const signature = crypto
    .createHmac('sha256', getSecret())
    .update(`${header}.${payload}`)
    .digest('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

  return `${header}.${payload}.${signature}`;
}

/**
 * Verify a token's signature and expiration. Returns the payload object or null.
 */
function verifyTokenSignature(token) {
  if (!token || typeof token !== 'string') return null;
  const parts = token.split('.');
  if (parts.length !== 3) return null;
  const [header, payload, signature] = parts;

  const expected = crypto
    .createHmac('sha256', getSecret())
    .update(`${header}.${payload}`)
    .digest('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

  // Constant-time comparison
  const sigBuf = Buffer.from(signature);
  const expBuf = Buffer.from(expected);
  if (
    sigBuf.length !== expBuf.length ||
    !crypto.timingSafeEqual(sigBuf, expBuf)
  ) {
    return null;
  }

  let payloadData;
  try {
    payloadData = JSON.parse(base64UrlDecode(payload));
  } catch (_) {
    return null;
  }
  if (!payloadData) return null;

  if (payloadData.exp && payloadData.exp < Math.floor(Date.now() / 1000)) {
    return null;
  }
  return payloadData;
}

function getSecret() {
  return (
    process.env.JWT_SECRET || 'acl_jobs_secret_key_change_in_production_2024'
  );
}

function sha256(str) {
  return crypto.createHash('sha256').update(str).digest('hex');
}

/**
 * Extract a bearer token from the Authorization header.
 */
function extractToken(req) {
  const authHeader =
    req.headers.authorization || req.headers.Authorization || '';
  if (!authHeader) return null;
  if (authHeader.startsWith('Bearer ')) {
    return authHeader.slice(7).trim();
  }
  return authHeader.trim() || null;
}

/**
 * Authenticate the request. Returns { user_id, role } or null.
 * Also bumps last_used_at if the token is found and valid.
 */
async function authenticate(req) {
  const token = extractToken(req);
  if (!token) return null;

  const payload = verifyTokenSignature(token);
  if (!payload) return null;

  const tokenHash = sha256(token);
  try {
    const record = await prisma.auth_tokens.findFirst({
      where: { token_hash: tokenHash },
    });
    if (!record) return null;
    if (record.is_revoked) return null;
    if (new Date(record.expires_at).getTime() < Date.now()) return null;

    // Update last used (best-effort, do not block on failure)
    prisma.auth_tokens
      .update({
        where: { id: record.id },
        data: { last_used_at: new Date() },
      })
      .catch(() => {});

    return { user_id: Number(payload.user_id), role: payload.role };
  } catch (err) {
    console.error('authenticate failed:', err.message);
    return null;
  }
}

/**
 * Require a valid auth token. Sends 401 and returns null otherwise.
 */
async function requireAuth(req, res) {
  const user = await authenticate(req);
  if (!user) {
    sendError(res, 'Authentication required. Please log in.', 401);
    return null;
  }
  return user;
}

/**
 * Require a specific role (or one of several roles).
 */
async function requireRole(req, res, roles) {
  const user = await requireAuth(req, res);
  if (!user) return null;

  const roleList = Array.isArray(roles) ? roles : [roles];
  if (!roleList.includes(user.role)) {
    sendError(res, 'You do not have permission to perform this action.', 403);
    return null;
  }
  return user;
}

/**
 * Persist a generated token in auth_tokens for revocation/last-used tracking.
 */
async function storeToken(userId, token) {
  const tokenHash = sha256(token);
  const expiresAt = new Date(Date.now() + TOKEN_EXPIRY_SECONDS * 1000);

  try {
    await prisma.auth_tokens.create({
      data: {
        user_id: Number(userId),
        token,
        token_hash: tokenHash,
        expires_at: expiresAt,
      },
    });
    return true;
  } catch (err) {
    console.error('storeToken failed:', err.message);
    return false;
  }
}

/**
 * Revoke a token (logout). Returns true if a row was updated.
 */
async function revokeToken(token) {
  if (!token) return false;
  const tokenHash = sha256(token);
  try {
    const result = await prisma.auth_tokens.updateMany({
      where: { token_hash: tokenHash },
      data: { is_revoked: 1 },
    });
    return result.count > 0;
  } catch (err) {
    console.error('revokeToken failed:', err.message);
    return false;
  }
}

/**
 * Fetch a user row joined with their profile.
 */
async function getUserWithProfile(userId) {
  return prisma.users.findUnique({
    where: { id: Number(userId) },
    include: { profile: true },
  });
}

/**
 * Create a notification row.
 */
async function createNotification(userId, type, title, message, data = null) {
  try {
    await prisma.notifications.create({
      data: {
        user_id: Number(userId),
        type,
        title,
        message,
        data: data ? data : undefined,
      },
    });
    return true;
  } catch (err) {
    console.error('createNotification failed:', err.message);
    return false;
  }
}

module.exports = {
  generateToken,
  verifyTokenSignature,
  extractToken,
  authenticate,
  requireAuth,
  requireRole,
  storeToken,
  revokeToken,
  getUserWithProfile,
  createNotification,
};
