# ACL Jobs API — PHP + MySQL Backend

REST API for the **ACL Jobs / Advenware Career Link** mobile app (React Native + Expo).

## Architecture

```
React Native / Expo
        ↓
     PHP REST API
        ↓
      MySQL
        ↑
    phpMyAdmin
```

The React Native app **never** connects directly to MySQL. All data access goes through this PHP API.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Database Setup](#database-setup)
4. [Configuration](#configuration)
5. [Starting the Server](#starting-the-server)
6. [Testing the API](#testing-the-api)
7. [React Native API URL Configuration](#react-native-api-url-configuration)
8. [Testing on a Physical Phone](#testing-on-a-physical-phone)
9. [API Endpoints](#api-endpoints)
10. [Example Requests & Responses](#example-requests--responses)
11. [Security Features](#security-features)
12. [File Structure](#file-structure)

---

## Requirements

| Software | Minimum Version |
|----------|----------------|
| PHP      | 7.4+ (8.0+ recommended) |
| MySQL    | 5.7+ (8.0+ recommended) |
| Apache   | 2.4+ (via XAMPP) |
| phpMyAdmin | 5.0+ |
| Composer | Optional (for JWT library, not required) |

**Required PHP extensions:** `pdo`, `pdo_mysql`, `openssl`, `json`, `mbstring`

---

## Installation

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
2. Run the installer and select **Apache** and **MySQL** (phpMyAdmin is included)
3. After installation, start the **Apache** and **MySQL** services from the XAMPP Control Panel

### Step 2: Place the Backend Files

Copy the entire `backend/` folder to your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\acl-jobs-api\
```

The final structure should look like:

```
C:\xampp\htdocs\acl-jobs-api\
├── .env
├── .htaccess
├── index.php
├── test_connection.php
├── config/
│   ├── database.php
│   ├── cors.php
│   ├── functions.php
│   └── auth.php
├── auth/
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   └── me.php
├── users/
│   └── profile.php
├── jobs/
│   ├── get_jobs.php
│   ├── get_job.php
│   ├── create_job.php
│   ├── update_job.php
│   └── delete_job.php
├── applications/
│   ├── apply.php
│   ├── my_applications.php
│   ├── application_details.php
│   └── update_status.php
├── saved_jobs/
│   ├── save_job.php
│   ├── unsave_job.php
│   └── my_saved_jobs.php
├── interviews/
│   ├── create.php
│   ├── list.php
│   └── update.php
├── notifications/
│   ├── list.php
│   ├── mark_read.php
│   └── mark_all_read.php
├── dashboard/
│   └── stats.php
├── uploads/
│   ├── .gitkeep
│   └── upload.php
└── logs/
    └── .gitkeep
```

### Step 3: Set Directory Permissions

Ensure the web server can write to the `logs/` and `uploads/` directories:

```bash
# On Windows (XAMPP), right-click the folders and set write permissions
# Or via command line:
icacls "C:\xampp\htdocs\acl-jobs-api\logs" /grant Everyone:F
icacls "C:\xampp\htdocs\acl-jobs-api\uploads" /grant Everyone:F
```

---

## Database Setup

### Step 1: Create the Database

1. Open **phpMyAdmin** in your browser: `http://localhost/phpmyadmin`
2. Click **Databases** in the top menu
3. Enter `acl_jobs` as the database name
4. Select `utf8mb4_general_ci` as the collation
5. Click **Create**

### Step 2: Import the SQL File

1. In phpMyAdmin, select the `acl_jobs` database from the left sidebar
2. Click **Import** in the top menu
3. Click **Choose File** and select `database/acl_jobs.sql`
4. Click **Go** to import

Alternatively, via MySQL CLI:

```bash
mysql -u root -p acl_jobs < database/acl_jobs.sql
```

### Step 3: Verify the Import

After importing, you should see the following tables in phpMyAdmin:

| Table | Description |
|-------|-------------|
| `users` | User accounts (job seekers, employers, admins) |
| `companies` | Company profiles linked to employer users |
| `profiles` | Detailed user profiles (full name, skills, CV, etc.) |
| `jobs` | Job listings |
| `applications` | Job applications submitted by users |
| `saved_jobs` | Jobs bookmarked by users |
| `interviews` | Interview scheduling |
| `notifications` | User notifications |
| `auth_tokens` | Authentication tokens for session management |

---

## Configuration

### Environment Variables (`.env`)

All sensitive configuration is stored in `backend/.env`. Edit this file to match your environment:

```ini
# Database Configuration
DB_HOST=localhost
DB_NAME=acl_jobs
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# JWT Secret Key - CHANGE THIS IN PRODUCTION
JWT_SECRET=acl_jobs_secret_key_change_in_production_2024

# Application Configuration
APP_ENV=development
APP_DEBUG=true
```

> **Important:** The `.env` file is protected by `.htaccess` and is not accessible via the web. Never commit it to version control.

### Database Configuration (`config/database.php`)

The database connection is managed via PDO with prepared statements. Key settings:

- **DB_HOST**: MySQL server host (default: `localhost`)
- **DB_NAME**: Database name (default: `acl_jobs`)
- **DB_USER**: MySQL username (default: `root`)
- **DB_PASS**: MySQL password (default: empty)
- **DB_CHARSET**: Character set (default: `utf8mb4`)

### CORS Configuration (`config/cors.php`)

CORS headers are configured to allow requests from:
- `http://localhost` and `http://localhost:8080` (development)
- `exp://localhost:19000` and `exp://127.0.0.1:19000` (Expo dev)
- `https://asljobs.com` and `https://api.asljobs.com` (production)

Add your production domain to the `$allowedOrigins` array in `config/cors.php`.

---

## Starting the Server

### Using XAMPP (Recommended)

1. Open the **XAMPP Control Panel**
2. Start **Apache** and **MySQL** services
3. The API will be available at: `http://localhost/acl-jobs-api/`

### Using PHP Built-in Server (Development Only)

```bash
cd C:\xampp\htdocs\acl-jobs-api
php -S localhost:8080
```

The API will be available at: `http://localhost:8080/`

---

## Testing the API

### Connection Test

Visit: `http://localhost/acl-jobs-api/test_connection.php`

Expected response:

```json
{
  "success": true,
  "message": "Database connection successful",
  "data": {
    "database": "acl_jobs",
    "current_db": "acl_jobs",
    "mysql_version": "8.0.36",
    "host": "localhost",
    "charset": "utf8mb4"
  }
}
```

### API Root

Visit: `http://localhost/acl-jobs-api/`

Returns API information and available endpoints.

### Using Postman / curl

All API requests use JSON. Include the `Authorization` header for protected endpoints:

```bash
# Register
curl -X POST http://localhost/acl-jobs-api/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"full_name":"John Doe","username":"johndoe","email":"john@example.com","phone":"+1234567890","password":"password123","role":"job_seeker"}'

# Login
curl -X POST http://localhost/acl-jobs-api/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Get jobs (public)
curl http://localhost/acl-jobs-api/api/jobs/get_jobs.php

# Get jobs (authenticated)
curl http://localhost/acl-jobs-api/api/jobs/get_jobs.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## React Native API URL Configuration

The API base URL is configured in `src/services/api.js`:

```javascript
// For development on a physical device, use your computer's LAN IP:
const API_BASE_URL = 'http://192.168.1.100:8080/api';

// For development on localhost (simulator/emulator):
// const API_BASE_URL = 'http://localhost:8080/api';

// For production:
// const API_BASE_URL = 'https://api.asljobs.com/api';
```

### Finding Your LAN IP

**Windows:**
```cmd
ipconfig
```
Look for "IPv4 Address" under your active network adapter.

**macOS:**
```bash
ifconfig | grep "inet "
```

**Linux:**
```bash
ip addr show | grep "inet "
```

### Testing on Expo Go (Physical Phone)

1. Ensure your phone and computer are on the **same Wi-Fi network**
2. Update `API_BASE_URL` in `src/services/api.js` to use your computer's LAN IP
3. Start the Expo dev server:
   ```bash
   npm start
   ```
4. Scan the QR code with the Expo Go app on your phone
5. The app will connect to your local PHP server

> **Note:** If your phone cannot reach `localhost`, you **must** use your computer's LAN IP address.

---

## API Endpoints

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register.php` | ❌ | Register a new user |
| POST | `/api/auth/login.php` | ❌ | Login and receive token |
| POST | `/api/auth/logout.php` | ✅ | Logout (revoke token) |
| GET | `/api/auth/me.php` | ✅ | Get current user info |

### Users / Profile

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/api/users/profile.php` | ✅ | any | Get user profile |
| POST | `/api/users/profile.php` | ✅ | any | Create/update profile |
| PUT | `/api/users/profile.php` | ✅ | any | Update profile |

### Jobs

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/api/jobs/get_jobs.php` | ❌ | any | List all active jobs (with search/filter) |
| GET | `/api/jobs/get_job.php?id=1` | ❌ | any | Get a single job |
| POST | `/api/jobs/create_job.php` | ✅ | employer, admin | Create a new job |
| PUT | `/api/jobs/update_job.php` | ✅ | employer, admin | Update a job |
| DELETE | `/api/jobs/delete_job.php` | ✅ | employer, admin | Delete a job |

### Applications

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| POST | `/api/applications/apply.php` | ✅ | job_seeker | Apply to a job |
| GET | `/api/applications/my_applications.php` | ✅ | job_seeker | List my applications |
| GET | `/api/applications/application_details.php?id=1` | ✅ | job_seeker, employer | View application details |
| PUT | `/api/applications/update_status.php` | ✅ | employer, admin | Update application status |

### Saved Jobs

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| POST | `/api/saved_jobs/save_job.php` | ✅ | job_seeker | Save a job |
| POST | `/api/saved_jobs/unsave_job.php` | ✅ | job_seeker | Unsave a job |
| GET | `/api/saved_jobs/my_saved_jobs.php` | ✅ | job_seeker | List saved jobs |

### Interviews

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| POST | `/api/interviews/create.php` | ✅ | employer, admin | Schedule an interview |
| GET | `/api/interviews/list.php` | ✅ | any | List interviews |
| PUT | `/api/interviews/update.php` | ✅ | employer, admin | Update interview |

### Notifications

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/api/notifications/list.php` | ✅ | any | List notifications |
| POST | `/api/notifications/mark_read.php` | ✅ | any | Mark notification as read |
| POST | `/api/notifications/mark_all_read.php` | ✅ | any | Mark all notifications as read |

### Dashboard

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/api/dashboard/stats.php` | ✅ | any | Get dashboard statistics |

### File Uploads

| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| POST | `/api/uploads/upload.php` | ✅ | any | Upload a file (profile photo, CV, etc.) |

---

## Example Requests & Responses

### Register

**Request:**
```http
POST /api/auth/register.php
Content-Type: application/json

{
  "full_name": "John Doe",
  "username": "johndoe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "password123",
  "role": "job_seeker"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "username": "johndoe",
      "email": "john@example.com",
      "role": "job_seeker"
    },
    "token": "eyJ0eXAiOiJKV1Qi..."
  }
}
```

### Login

**Request:**
```http
POST /api/auth/login.php
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "johndoe",
      "email": "john@example.com",
      "role": "job_seeker",
      "full_name": "John Doe",
      "phone": "+1234567890"
    },
    "token": "eyJ0eXAiOiJKV1Qi..."
  }
}
```

### Get Jobs (Public)

**Request:**
```http
GET /api/jobs/get_jobs.php
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "jobs": [
      {
        "id": 1,
        "company_id": 1,
        "company_name": "Acme Corp",
        "title": "Senior PHP Developer",
        "description": "We are looking for a senior PHP developer...",
        "location": "Remote",
        "category": "Development",
        "employment_type": "Full-time",
        "salary": "$80,000 - $120,000",
        "requirements": "5+ years PHP, MySQL, REST APIs",
        "status": "active",
        "is_featured": 1,
        "views_count": 42,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
      }
    ],
    "total": 1,
    "page": 1,
    "limit": 10
  }
}
```

### Apply to a Job

**Request:**
```http
POST /api/applications/apply.php
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
Content-Type: application/json

{
  "job_id": 1,
  "cover_letter": "I am very interested in this position...",
  "cv_url": "https://example.com/cv.pdf"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Application submitted successfully",
  "data": {
    "application": {
      "id": 1,
      "user_id": 1,
      "job_id": 1,
      "status": "pending",
      "cover_letter": "I am very interested in this position...",
      "cv_url": "https://example.com/cv.pdf",
      "applied_at": "2024-01-20T14:00:00Z",
      "updated_at": "2024-01-20T14:00:00Z"
    }
  }
}
```

### Get Dashboard Statistics

**Request:**
```http
GET /api/dashboard/stats.php
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "applications": 5,
    "job_offers": 2,
    "saved_jobs": 12,
    "interviews": 1,
    "unread_notifications": 3
  }
}
```

### Error Response

**Response (401):**
```json
{
  "success": false,
  "message": "Authentication required. Please log in."
}
```

**Response (403):**
```json
{
  "success": false,
  "message": "You do not have permission to perform this action."
}
```

**Response (404):**
```json
{
  "success": false,
  "message": "Job not found."
}
```

---

## Security Features

| Feature | Implementation |
|---------|----------------|
| **Password Hashing** | `password_hash()` with `PASSWORD_DEFAULT` (bcrypt) |
| **Password Verification** | `password_verify()` |
| **SQL Injection Prevention** | PDO prepared statements with parameterized queries |
| **Authentication** | JWT-like tokens stored in database (`auth_tokens` table) |
| **Token Revocation** | Tokens can be revoked on logout |
| **Role-Based Access Control** | `requireAuth()` and `requireRole()` middleware |
| **IDOR Prevention** | Server-side ownership checks on every protected endpoint |
| **CORS** | Configurable allowed origins |
| **Input Validation** | Email, phone, password, username validation |
| **Output Sanitization** | `htmlspecialchars()` and `strip_tags()` |
| **Error Handling** | Internal errors logged, generic messages sent to client |
| **HTTP Status Codes** | 200, 201, 400, 401, 403, 404, 409, 422, 500 |
| **Environment Variables** | `.env` file protected by `.htaccess` |
| **File Upload Security** | Type/size validation, files stored on disk (not DB) |

---

## File Structure

```
backend/
├── .env                    # Environment configuration (DB credentials, JWT secret)
├── .htaccess               # Apache URL rewriting and CORS headers
├── index.php               # API router / entry point
├── test_connection.php     # Database connection test
├── config/
│   ├── database.php        # PDO connection, env loading, constants
│   ├── cors.php            # CORS headers
│   ├── functions.php       # Common helpers (sendJson, sendSuccess, sendError, validation)
│   └── auth.php            # JWT generation/verification, RBAC middleware
├── auth/
│   ├── register.php        # User registration
│   ├── login.php           # User login
│   ├── logout.php          # Token revocation
│   └── me.php              # Get current user
├── users/
│   └── profile.php         # Get/update user profile
├── jobs/
│   ├── get_jobs.php        # List jobs (with search/filter)
│   ├── get_job.php         # Get single job
│   ├── create_job.php      # Create job (employer/admin)
│   ├── update_job.php      # Update job (employer/admin)
│   └── delete_job.php      # Delete job (employer/admin)
├── applications/
│   ├── apply.php           # Apply to a job
│   ├── my_applications.php # List user's applications
│   ├── application_details.php # View application details
│   └── update_status.php   # Update application status (employer/admin)
├── saved_jobs/
│   ├── save_job.php        # Save a job
│   ├── unsave_job.php      # Unsave a job
│   └── my_saved_jobs.php   # List saved jobs
├── interviews/
│   ├── create.php          # Schedule interview (employer/admin)
│   ├── list.php            # List interviews
│   └── update.php          # Update interview (employer/admin)
├── notifications/
│   ├── list.php            # List notifications
│   ├── mark_read.php       # Mark notification as read
│   └── mark_all_read.php   # Mark all as read
├── dashboard/
│   └── stats.php           # Dashboard statistics
├── uploads/
│   ├── .gitkeep
│   └── upload.php          # File upload handler
└── logs/
    └── .gitkeep            # Error log directory
```

---

## Roles & Permissions

| Role | Can Register | Can Login | Can Create Jobs | Can Update Jobs | Can Apply | Can View Own Apps | Can View All Apps | Can Manage Interviews |
|------|-------------|-----------|-----------------|-----------------|-----------|-------------------|-------------------|----------------------|
| `job_seeker` | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | Own only |
| `employer` | ✅ | ✅ | ✅ | Own jobs | ❌ | ❌ | Own jobs' apps | ✅ |
| `admin` | ✅ | ✅ | ✅ | Any | ❌ | ❌ | ✅ | ✅ |

---

## Troubleshooting

### "Database connection failed"

1. Ensure MySQL is running in XAMPP Control Panel
2. Check `backend/.env` for correct `DB_HOST`, `DB_USER`, `DB_PASS`
3. Verify the `acl_jobs` database exists in phpMyAdmin
4. Check `backend/logs/error.log` for detailed error messages

### "404 Endpoint not found"

1. Ensure `.htaccess` is enabled in Apache
2. Check that `mod_rewrite` is enabled: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Verify the URL path matches the file structure (e.g., `/api/auth/login.php`)

### CORS errors in React Native

1. Ensure the origin is in the `$allowedOrigins` array in `config/cors.php`
2. For development, all origins are allowed by default
3. Check that the `Authorization` header is included in requests

### Token not working

1. Ensure the `Authorization: Bearer <token>` header is sent correctly
2. Check that the token hasn't expired (30-day expiry)
3. Verify the token exists in the `auth_tokens` table and is not revoked

### File upload fails

1. Ensure the `uploads/` directory has write permissions
2. Check `MAX_FILE_SIZE` in `config/database.php` (default: 5MB)
3. Verify the file type is in `ALLOWED_IMAGE_TYPES` or `ALLOWED_DOC_TYPES`

### "Fetch failed: java.net.NoRouteToHostException: Host unreachable" (Android)

The phone can resolve the API hostname but packets can't be routed to it.
This is almost always one of these four things:

1. **`DEV_LAN_IP` in `src/services/api.js` is wrong.**
   Open a terminal on your PC and run:

   ```cmd
   ipconfig
   ```

   Take the **IPv4 Address** under your active Wi-Fi/Ethernet adapter
   (e.g. `192.168.1.42`) and put it in `DEV_LAN_IP` in
   `src/services/api.js`. The placeholder `192.168.1.100` will not match
   your real adapter.

2. **The PHP server is not bound to `0.0.0.0` / not running on the right port.**
   Use the built-in PHP server bound to all interfaces:

   ```cmd
   cd C:\xampp\htdocs\acl-jobs-api
   php -S 0.0.0.0:8080 -t .
   ```

   The `-S 0.0.0.0:8080` (not `localhost:8080`) is important — without it,
   Android devices on your LAN can't reach the server.

3. **Windows Firewall is blocking inbound TCP 8080.**
   Allow it once:

   ```cmd
   netsh advfirewall firewall add rule name="PHP Dev Server 8080" dir=in action=allow protocol=TCP localport=8080
   ```

4. **Phone and PC are on different Wi-Fi networks (or guest networks).**
   Both must be on the same subnet. Disable mobile data on the phone
   while testing, or connect both to the same SSID.

Other quick checks:

- From the phone's browser, open `http://<your-lan-ip>:8080/` — if it
  doesn't load, the server isn't reachable and no app code change will help.
- On **Android emulator** the correct host is `http://10.0.2.2:8080/api`
  (this is already wired up automatically in `api.js`).
- On **Android**, plain `http://` is blocked by default since Android 9.
  This project already opts in via `"usesCleartextTraffic": true` in
  `app.json`; if you copied the file out of version control, make sure that
  setting is still there.
