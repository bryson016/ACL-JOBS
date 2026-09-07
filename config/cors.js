/**
 * CORS configuration for ACL Jobs API
 *
 * Handles Cross-Origin Resource Sharing headers for mobile app and web requests.
 * Mirrors the original PHP config so the frontend doesn't need any change.
 */

const allowedOrigins = [
  'http://localhost',
  'http://localhost:3000',
  'http://localhost:8080',
  'http://127.0.0.1',
  'http://127.0.0.1:3000',
  'http://127.0.0.1:8080',
  'exp://localhost:19000',
  'exp://localhost:19001',
  'exp://127.0.0.1:19000',
  'exp://127.0.0.1:19001',
  'https://acl-jobs.onrender.com',
  'https://asljobs.com',
  'https://api.asljobs.com',
  'https://www.asljobs.com',
];

function corsMiddleware(req, res, next) {
  const origin = req.headers.origin || '';

  if (allowedOrigins.includes(origin)) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
  } else {
    // React Native / mobile clients don't always send an Origin header.
    res.setHeader('Access-Control-Allow-Origin', origin || '*');
    if (origin) {
      res.setHeader('Access-Control-Allow-Credentials', 'true');
    }
  }

  res.setHeader(
    'Access-Control-Allow-Methods',
    'GET, POST, PUT, DELETE, PATCH, OPTIONS'
  );
  res.setHeader(
    'Access-Control-Allow-Headers',
    'Content-Type, Authorization, X-Requested-With, X-Auth-Token, Accept, Origin'
  );
  res.setHeader('Access-Control-Max-Age', '3600');

  if (req.method === 'OPTIONS') {
    return res.status(204).end();
  }

  next();
}

module.exports = corsMiddleware;
