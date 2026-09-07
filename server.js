/**
 * ACL Jobs API - Node.js / Express entry point.
 *
 * This server is a 1:1 functional replacement for the original PHP
 * backend. It listens on the same URL paths the frontend already uses
 * (including the `.php` suffixes), so no frontend changes are required.
 *
 * Run locally:   npm start
 * Run on Render: same `npm start` command (Render sets the PORT env var).
 */

require('dotenv').config();

const express = require('express');
const path = require('path');

const corsMiddleware = require('./config/cors');
const { testConnection } = require('./config/database');

const authRoutes = require('./routes/auth');
const usersRoutes = require('./routes/users');
const jobsRoutes = require('./routes/jobs');
const applicationsRoutes = require('./routes/applications');
const savedJobsRoutes = require('./routes/savedJobs');
const interviewsRoutes = require('./routes/interviews');
const notificationsRoutes = require('./routes/notifications');
const dashboardRoutes = require('./routes/dashboard');
const uploadsRoutes = require('./routes/uploads');

const app = express();

// --- Global middleware -----------------------------------------------------

app.use(corsMiddleware);

// JSON body parser with a generous limit (upload endpoint uses multer, not this).
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Serve uploaded files statically so URLs returned by /api/uploads/upload
// (e.g. /api/uploads/files/profile_photo/user_123_....jpg) resolve correctly.
app.use(
  '/api/uploads/files',
  express.static(path.join(__dirname, 'uploads'), { fallthrough: true })
);

// --- Routes ----------------------------------------------------------------

app.get('/', (req, res) => {
  res.json({
    name: 'ASL Jobs API',
    version: '1.0.0',
    description: 'Node.js/Express backend for Advenware Career Link',
    endpoints: {
      auth: '/api/auth/',
      users: '/api/users/',
      jobs: '/api/jobs/',
      applications: '/api/applications/',
      saved_jobs: '/api/saved_jobs/',
      interviews: '/api/interviews/',
      notifications: '/api/notifications/',
      dashboard: '/api/dashboard/',
      uploads: '/api/uploads/',
    },
  });
});

app.get('/api', (req, res) => {
  res.json({
    name: 'ASL Jobs API',
    version: '1.0.0',
    description: 'REST API for Advenware Career Link',
  });
});

app.use('/api/auth', authRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/jobs', jobsRoutes);
app.use('/api/applications', applicationsRoutes);
app.use('/api/saved_jobs', savedJobsRoutes);
app.use('/api/interviews', interviewsRoutes);
app.use('/api/notifications', notificationsRoutes);
app.use('/api/dashboard', dashboardRoutes);
app.use('/api/uploads', uploadsRoutes);

// 404 fallthrough
app.use((req, res) => {
  res.status(404).json({ success: false, message: 'Endpoint not found.' });
});

// Centralized error handler
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res
    .status(err.status || 500)
    .json({ success: false, message: err.message || 'Internal server error' });
});

// --- Boot ------------------------------------------------------------------

const PORT = parseInt(process.env.PORT || '5000', 10);
const HOST = process.env.HOST || '0.0.0.0';

async function start() {
  const ok = await testConnection();
  if (!ok) {
    console.error(
      'Could not connect to MySQL. Check DB_HOST / DB_USER / DB_PASSWORD / DB_NAME in backend/.env.'
    );
    // Don't hard-exit so Render logs surface the issue clearly.
  } else {
    console.log('Database connection OK.');
  }

  app.listen(PORT, HOST, () => {
    console.log(`ACL Jobs API listening on http://${HOST}:${PORT}`);
  });
}

start();

module.exports = app;
