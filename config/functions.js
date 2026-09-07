/**
 * Common helper functions for ACL Jobs API
 *
 * Mirrors the original PHP helpers so all API responses keep the same
 * `{ success, message?, data?, errors? }` shape the frontend already expects.
 */

/**
 * Send a JSON response and end the request.
 */
function sendJson(res, data, statusCode = 200) {
  res.status(statusCode).json(data);
}

function sendSuccess(res, data = null, message = '', statusCode = 200) {
  const response = { success: true };
  if (message) response.message = message;
  if (data !== null) response.data = data;
  sendJson(res, response, statusCode);
}

function sendError(res, message, statusCode = 400, errors = null) {
  const response = { success: false, message };
  if (errors !== null) response.errors = errors;
  sendJson(res, response, statusCode);
}

/**
 * Read and parse JSON body. Returns {} if empty or invalid.
 */
function getJsonInput(req) {
  if (
    req.body == null ||
    (typeof req.body === 'object' && Object.keys(req.body).length === 0)
  ) {
    return {};
  }
  if (typeof req.body === 'string') {
    try {
      const parsed = JSON.parse(req.body);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  }
  return req.body;
}

function getInput(req) {
  // Express + cors/json middleware already parses JSON into req.body.
  return getJsonInput(req);
}

function sanitize(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/<[^>]*>/g, '')
    .trim();
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || ''));
}

function isValidPhone(phone) {
  if (!phone) return true; // optional
  return /^[\+]?[0-9\s\-()]{7,20}$/.test(String(phone));
}

function isValidPassword(password) {
  return typeof password === 'string' && password.length >= 6;
}

function isValidUsername(username) {
  return /^[a-zA-Z0-9_]{3,50}$/.test(String(username || ''));
}

function isValidRole(role) {
  return ['job_seeker', 'employer', 'admin'].includes(role);
}

function formatDate(date) {
  if (!date) return null;
  const d = new Date(date);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString().slice(0, 19).replace('T', ' ');
}

function getClientIp(req) {
  const xff = req.headers['x-forwarded-for'];
  if (xff) return String(xff).split(',')[0].trim();
  if (req.headers['x-real-ip']) return req.headers['x-real-ip'];
  return req.ip || req.connection?.remoteAddress || '';
}

/**
 * Parse a JSON column value that may already be a JSON object or a stringified
 * blob. Returns null if empty.
 */
function parseJsonField(value) {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch (_) {
    return null;
  }
}

module.exports = {
  sendJson,
  sendSuccess,
  sendError,
  getJsonInput,
  getInput,
  sanitize,
  isValidEmail,
  isValidPhone,
  isValidPassword,
  isValidUsername,
  isValidRole,
  formatDate,
  getClientIp,
  parseJsonField,
};
