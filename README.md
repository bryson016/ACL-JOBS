# ACL Jobs API — Node.js / Express / Prisma

This is the **only** backend for the project. It is written in
**JavaScript** (Node.js + Express), uses **Prisma ORM** with a
**Prisma schema** ([`prisma/schema.prisma`](prisma/schema.prisma:1)) for all
database access, and talks to the existing MySQL `acl_jobs` database.

**There is no PHP in this project.** Every PHP file that previously lived
under `backend/` has been removed; the schema it created is now mirrored
read-only by the Prisma schema. Existing data, users, and roles are
preserved exactly as they were.

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

## Project layout

```
backend/
├── server.js                  # Express entry point, mounts all routes
├── package.json               # Deps + npm start
├── .env                       # DB / JWT / DATABASE_URL
├── .gitignore
├── README.md                  # This file
├── prisma/
│   └── schema.prisma          # Read-only Prisma schema mirroring MySQL
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
| Database         | MySQL (existing `acl_jobs` database)                      |
| Auth             | bcrypt password hashes + HMAC-SHA256 tokens (PHP-compatible) |
| File uploads     | multer (memory storage, 5 MB cap)                         |
| CORS             | Hand-rolled middleware (same allowed-origin list as PHP)   |

`mysql2` is **not** used anywhere — all DB access goes through the
generated Prisma client.

---

## Prisma schema

[`prisma/schema.prisma`](prisma/schema.prisma:1) declares models for every
existing table:

* `users`, `companies`, `profiles`, `jobs`, `applications`,
  `saved_jobs`, `interviews`, `notifications`, `auth_tokens`.

The schema uses **`@map` table names** to match the existing
`snake_case` MySQL tables exactly, and the column types / nullability /
indexes mirror the original `acl_jobs.sql` definitions.

**Important**: do **not** run `prisma migrate dev` or `prisma db push`
against this schema. The live database already has the tables and data
we need; the schema is a read-only mapping used purely to generate the
typed client. The only Prisma commands you should run with this schema
are:

```bash
npx prisma generate   # regenerate the typed client after edits
npx prisma db pull    # re-introspect the live DB if columns change
npx prisma format     # auto-format the schema
```

`postinstall` in `package.json` runs `prisma generate` automatically
after `npm install`.

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

Already set in `backend/.env`:

| Variable      | Purpose                                                                |
| ------------- | ---------------------------------------------------------------------- |
| `DB_HOST`     | MySQL host                                                             |
| `DB_PORT`     | MySQL port                                                             |
| `DB_USER`     | MySQL user                                                             |
| `DB_PASSWORD` | MySQL password                                                         |
| `DB_NAME`     | Database name (`acl_jobs`)                                             |
| `DATABASE_URL`| `mysql://USER:PASSWORD@HOST:PORT/DATABASE` — used by Prisma           |
| `JWT_SECRET`  | HMAC secret for signing tokens. **Change in production.**              |
| `PORT`        | Server port (default `5000`, Render sets this automatically)           |

---

## Running locally

```bash
cd backend
npm install      # also runs `prisma generate` via postinstall
npm start
```

You should see:

```
Database connection OK.
ACL Jobs API listening on http://0.0.0.0:5000
```

The frontend (`src/services/api.js`) currently expects the API at
`http://localhost:5000/api` on web, `http://10.0.2.2:5000/api` on the
Android emulator, or `http://<DEV_LAN_IP>:5000/api` on a physical
device. Either set `EXPO_PUBLIC_API_BASE_URL` to that base URL, or just
keep using the existing XAMPP/8080 config until you cut over.

---

## Deploying to Render

1. Push this repo to GitHub.
2. Render → **New Web Service** → pick the repo.
3. Settings:
   - **Root Directory**: `backend`
   - **Build Command**: `npm install` (runs `prisma generate` via `postinstall`)
   - **Start Command**: `npm start`
   - **Environment Variables**:
     - `DATABASE_URL` (e.g. `mysql://user:pass@host:3306/acl_jobs`)
     - `JWT_SECRET` (a fresh long random string)
     - Any of the `DB_*` vars if you prefer those over `DATABASE_URL`.
4. After first deploy, the API will be reachable at
   `https://acl-jobs.onrender.com/api/...` — exactly where the frontend
   already looks for it (`https://acl-jobs.onrender.com/api` is the
   production fallback URL in `src/services/api.js`).

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

- **`Database connection failed` on boot** — check `DATABASE_URL` (and
  the `DB_*` aliases) in `backend/.env`. Both forms are read.
- **`Authentication required` for protected routes** — every protected
  endpoint expects `Authorization: Bearer <token>`. The frontend sends
  this automatically from `src/services/api.js`.
- **Prisma client out of date after editing the schema** — run
  `npx prisma generate` (or `npm install` again, which triggers
  `postinstall`).
- **File uploads return 413 (too large)** — multer is configured with a
  5MB limit to match the original PHP setting
  (`MAX_FILE_SIZE = 5 * 1024 * 1024`).
- **CORS errors from the browser** — the allowed-origin list in
  `config/cors.js` mirrors the original PHP list. Add your domain there
  if you deploy under a new hostname.
