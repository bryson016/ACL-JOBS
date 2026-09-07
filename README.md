# ACL Jobs API — Node.js / Express / Prisma

This is the **only** backend for the project. It is written in
**JavaScript** (Node.js + Express) and uses **Prisma ORM** with a
**Prisma schema** ([`prisma/schema.prisma`](prisma/schema.prisma:1)) for all
database access.

**There is no PHP in this project.** Every PHP file that previously lived
under `backend/` has been removed.

**The default database is SQLite** (a local file at
`backend/acl_jobs.db`), so the app works out of the box on any machine
without needing a MySQL server. **MySQL is fully supported as a drop-in
swap** — change two lines and you're on the original `acl_jobs` MySQL
database. See [Database providers](#database-providers) below.

The React Native client in `src/services/api.js` was not modified — it
calls endpoints like `auth/login.php`, `jobs/get_jobs.php`,
`applications/apply.php`, and the Express routes use the **same paths
including the `.php` suffix**, so the same URLs resolve to the new
handlers.

Response shape is preserved:

```json
{ "success": true, "message": "...", "data": {...} }
{ "success": false, "message": "...", "errors": { "field": "..." } }
```

---

## Quick start (works immediately, no MySQL needed)

```bash
cd backend
npm install
npx prisma generate      # already runs automatically via postinstall
npx prisma db push       # creates the local SQLite DB on first run
npm start
```

Then open <http://localhost:5000>. You should see:

```
✅ Database connection OK.
🚀 ACL Jobs API listening on http://0.0.0.0:5000
   Database: configured via DATABASE_URL
```

The first user you register becomes the first account in the system.

---

## Project layout

```
backend/
├── server.js                  # Express entry point, mounts all routes
├── package.json               # Deps + npm start
├── .env                       # DATABASE_URL + JWT_SECRET (+ optional PORT)
├── .gitignore
├── README.md                  # This file
├── acl_jobs.db                # SQLite database (created on first run)
├── prisma/
│   └── schema.prisma          # Prisma schema (SQLite default, MySQL-ready)
├── config/
│   ├── database.js            # Prisma client (singleton)
│   ├── auth.js                # Token generation/verification + middleware
│   ├── cors.js                # CORS headers
│   └── functions.js           # sendSuccess / sendError / validators
├── routes/
│   ├── auth.js                # /api/auth/*
│   ├── users.js               # /api/users/profile
│   ├── jobs.js                # /api/jobs/*
│   ├── applications.js        # /api/applications/*
│   ├── savedJobs.js           # /api/saved_jobs/*
│   ├── interviews.js          # /api/interviews/*
│   ├── notifications.js       # /api/notifications/*
│   ├── dashboard.js           # /api/dashboard/stats
│   └── uploads.js             # /api/uploads/*
└── uploads/                   # Created on first upload
```

---

## Tech stack

| Layer            | Choice                                                    |
| ---------------- | --------------------------------------------------------- |
| Runtime          | Node.js (>= 18)                                            |
| HTTP framework   | Express 4                                                 |
| ORM              | Prisma 5 (`@prisma/client`)                               |
| Database (default) | SQLite (file-based, no server required)                 |
| Database (swap)  | MySQL — see [Database providers](#database-providers)     |
| Auth             | bcrypt password hashes + HMAC-SHA256 tokens               |
| File uploads     | multer (memory storage, 5 MB cap)                         |
| CORS             | Hand-rolled middleware (same allowed-origin list as PHP)   |

`mysql2` is **not** used anywhere — all DB access goes through the
generated Prisma client.

---

## Prisma schema

[`prisma/schema.prisma`](prisma/schema.prisma:1) declares models for every
table in the system:

* `users`, `companies`, `profiles`, `jobs`, `applications`,
  `saved_jobs`, `interviews`, `notifications`, `auth_tokens`.

The schema is valid for **both SQLite and MySQL** (the two supported
Prisma providers) — it uses plain Prisma types with no MySQL-only
decorators, and the enum-like fields (`role`, `status`) are stored as
`String` with documented value sets so the same schema validates on
both providers.

`postinstall` in `package.json` runs `prisma generate` automatically
after `npm install`.

---

## Database providers

The backend works with **two** Prisma providers. Switch by changing two
lines.

### Option A — SQLite (default, used in `backend/.env`)

A local file `backend/acl_jobs.db` is created automatically. No server
required — perfect for development and demoing the app on any machine.

```env
# backend/.env
DATABASE_URL=file:./acl_jobs.db
```

```prisma
// prisma/schema.prisma
datasource db {
  provider = "sqlite"
  url      = env("DATABASE_URL")
}
```

First run only:

```bash
npx prisma db push
npm start
```

### Option B — MySQL (for production against the original database)

Point at the original `acl_jobs` MySQL database the PHP backend used.

```env
# backend/.env
DATABASE_URL=mysql://USER:PASSWORD@HOST:3306/acl_jobs
```

```prisma
// prisma/schema.prisma
datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}
```

After editing the provider, regenerate the client and create the
tables (only needed once on a fresh DB):

```bash
npx prisma generate
npx prisma db push
```

Existing tables and data are preserved — Prisma only adds what's
missing.

---

## Authentication & roles

Token format and signing are **byte-compatible** with the original PHP
implementation, so existing sessions and `auth_tokens` rows keep working
through the migration:

```
base64url(header).base64url(payload).base64url(hmacSHA256(header.payload, JWT_SECRET))
```

`JWT_SECRET` is read from `backend/.env`. Tokens are also looked up in
`auth_tokens` by their SHA-256 hash so revocation and last-used tracking
continue to work.

Role enforcement happens on the server in every protected route. The
frontend cannot bypass role checks — `requireAuth()` and `requireRole()`
return 401 / 403 before the handler runs.

Passwords use `bcrypt.compare()`, which understands the `$2y$` hashes
PHP produces, so the existing user passwords still log in.

---

## Environment variables

Set in `backend/.env`:

| Variable       | Purpose                                                                                |
| -------------- | -------------------------------------------------------------------------------------- |
| `DATABASE_URL` | Prisma connection string. Default `file:./acl_jobs.db` (SQLite). See [Database providers](#database-providers) for the MySQL form. |
| `JWT_SECRET`   | HMAC secret for signing tokens. **Change in production.**                              |
| `PORT`         | Server port (default `5000`, Render sets this automatically)                           |

The `DATABASE_URL` value is the only database setting. Prisma reads it
directly; there are no separate `DB_HOST` / `DB_USER` / `DB_PASSWORD`
variables.

---

## Running locally

```bash
cd backend
npm install      # also runs `prisma generate` via postinstall
npx prisma db push   # only the first time, creates the SQLite DB
npm start
```

You should see:

```
✅ Database connection OK.
🚀 ACL Jobs API listening on http://0.0.0.0:5000
   Database: configured via DATABASE_URL
```

### How the frontend finds the API

The frontend (`src/services/api.js`) automatically picks the right URL
based on platform and build mode:

| Build / platform       | API base URL                                  |
| ---------------------- | --------------------------------------------- |
| Web (dev)              | `http://localhost:5000/api`                   |
| Web (prod build)       | `https://acl-jobs.onrender.com/api`           |
| Android **emulator**   | `http://10.0.2.2:5000/api` (host loopback)    |
| iOS simulator          | `http://localhost:5000/api`                   |
| Physical device        | `http://<your-LAN-IP>:5000/api`               |

`10.0.2.2` is the Android emulator's alias for the host machine's
loopback — that's why the local server works from the emulator even
though it binds to `127.0.0.1`.

To override the URL for a custom build, set the
`EXPO_PUBLIC_API_BASE_URL` env var before running `expo start` / `eas
build`.

---

## Deploying to Render

The repo includes a Render Blueprint at [`render.yaml`](../render.yaml:1)
that creates a `acl-jobs-api` web service pre-configured for this
project.

### One-click deploy with the Blueprint

1. Push this repo to GitHub.
2. Render dashboard → **New** → **Blueprint**.
3. Pick the repo — Render reads `render.yaml` and provisions
   `acl-jobs-api` with:
   - **Root Directory**: `backend`
   - **Build Command**: `npm install`
   - **Start Command**: `npm start`
   - **Health check**: `GET /`
4. After it's created, open the service → **Environment** and set
   - `JWT_SECRET` → a fresh long random string
   - `DATABASE_URL` → either the SQLite default
     `file:./acl_jobs.db` (fine for a quick demo — Render's free tier
     has **no persistent disk**, so data will reset on every redeploy)
     **or** the MySQL connection string to your real database
     (recommended for production).
5. Trigger a deploy. Once it's up, the API is reachable at
   `https://acl-jobs.onrender.com/api/...` — exactly the URL the
   frontend already uses.

### Manual setup (without the Blueprint)

1. Render → **New Web Service** → pick the repo.
2. Settings:
   - **Root Directory**: `backend`
   - **Build Command**: `npm install`
   - **Start Command**: `npm start`
   - **Environment Variables**:
     - `DATABASE_URL` (e.g. `file:./acl_jobs.db` or
       `mysql://user:pass@host:3306/acl_jobs`)
     - `JWT_SECRET` (a fresh long random string)
3. After first deploy, the API will be reachable at
   `https://<your-service>.onrender.com/api/...`.

The server reads `process.env.PORT`, so it works with Render's automatic
port assignment.

---

## API endpoint reference

All endpoints are mounted under `/api/`. The trailing `.php` is
optional — both `/api/jobs/get_jobs` and `/api/jobs/get_jobs.php`
resolve to the same handler.

| Module        | Methods     | Path                              | Roles allowed                          |
| ------------- | ----------- | --------------------------------- | -------------------------------------- |
| auth          | POST        | `/api/auth/login`                 | public                                 |
| auth          | POST        | `/api/auth/register`              | public                                 |
| auth          | POST        | `/api/auth/logout`                | authenticated                          |
| auth          | GET         | `/api/auth/me`                    | authenticated                          |
| users         | GET/PUT/POST| `/api/users/profile`              | authenticated                          |
| jobs          | GET         | `/api/jobs/get_jobs`              | public (optional auth)                 |
| jobs          | GET         | `/api/jobs/get_job?id=…`          | public (optional auth)                 |
| jobs          | POST        | `/api/jobs/create_job`            | employer, admin                        |
| jobs          | PUT/POST    | `/api/jobs/update_job`            | employer (own), admin                  |
| jobs          | DELETE/POST | `/api/jobs/delete_job`            | employer (own), admin                  |
| applications  | POST        | `/api/applications/apply`         | job_seeker                             |
| applications  | GET         | `/api/applications/my_applications` | authenticated                        |
| applications  | GET         | `/api/applications/application_details` | authenticated (own/own-job)        |
| applications  | PUT/POST    | `/api/applications/update_status` | job_seeker (withdraw), employer, admin |
| saved_jobs    | POST        | `/api/saved_jobs/save_job`        | authenticated                          |
| saved_jobs    | DELETE/POST | `/api/saved_jobs/unsave_job`      | authenticated                          |
| saved_jobs    | GET         | `/api/saved_jobs/my_saved_jobs`   | authenticated                          |
| interviews    | GET         | `/api/interviews/list`            | authenticated (scoped by role)         |
| interviews    | POST        | `/api/interviews/create`          | employer (own jobs), admin             |
| interviews    | PUT/POST    | `/api/interviews/update`          | employer (own), admin                  |
| notifications | GET         | `/api/notifications/list`         | authenticated                          |
| notifications | POST        | `/api/notifications/mark_read`    | authenticated                          |
| notifications | POST        | `/api/notifications/mark_all_read`| authenticated                          |
| dashboard     | GET         | `/api/dashboard/stats`            | authenticated (different stats per role) |
| uploads       | POST        | `/api/uploads/upload?type=…`      | authenticated                          |
| uploads       | GET         | `/api/uploads/files/...`          | public (static file serving)           |

---

## Database

The MySQL schema is unchanged. The Prisma client talks to the same
`acl_jobs` database using the same tables and columns:

* `users`, `companies`, `profiles`, `jobs`, `applications`,
  `saved_jobs`, `interviews`, `notifications`, `auth_tokens`.

No tables were dropped, recreated, or renamed. Existing records are
intact, including bcrypt password hashes. The Prisma schema in
`prisma/schema.prisma` is a **read-only mapping** of the existing
schema; it is never used to migrate or alter the database.

---

## Troubleshooting

- **First-time setup: `acl_jobs.db does not exist`** — run
  `npx prisma db push` once. It creates the SQLite file and all
  tables. (Or `npx prisma generate` first if you haven't run
  `npm install` yet.)
- **Database not reachable on boot** — the server still starts and
  serves every request. It logs a single friendly warning and prints
  the current `DATABASE_URL` so you can see exactly which host:port
  it is trying. Routes that touch the DB return a clean JSON
  `503 { success: false, message: "Database temporarily unavailable.
  Please try again shortly." }` instead of crashing. If you set
  `DATABASE_URL` to a MySQL URL, make sure the MySQL server is running
  and reachable.
- **Switching to MySQL but seeing schema errors** — change **both** the
  `provider` line in `prisma/schema.prisma` AND `DATABASE_URL` in
  `backend/.env`, then run `npx prisma generate` and
  `npx prisma db push` once.
- **`Authentication required` for protected routes** — every protected
  endpoint expects `Authorization: Bearer <token>`. The frontend sends
  this automatically from `src/services/api.js`.
- **Prisma client out of date after editing the schema** — run
  `npx prisma generate` (or `npm install` again, which triggers
  `postinstall`).
- **File uploads return 413 (too large)** — multer is configured with
  a 5MB limit to match the original PHP setting
  (`MAX_FILE_SIZE = 5 * 1024 * 1024`).
- **CORS errors from the browser** — the allowed-origin list in
  `config/cors.js` mirrors the original PHP list. Add your domain
  there if you deploy under a new hostname.
