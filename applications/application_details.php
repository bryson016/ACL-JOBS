<?php
/**
 * Get details of a specific application
 *
 * Endpoint: GET /api/applications/application_details.php?id=<application_id>
 *
 * Headers: Authorization: Bearer <token>
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": { "application": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed. Use GET.', 405);
}

// Require authentication
$user = requireAuth();

$applicationId = (int) ($_GET['id'] ?? 0);

if ($applicationId <= 0) {
    sendError('Application ID is required.', 400);
}

try {
    $pdo = getDB();

    // Get application with job and company details
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.job_id, a.status, a.cover_letter, a.cv_url,
               a.applied_at, a.updated_at,
               j.title as job_title, j.company_id, j.location, j.employment_type,
               j.salary, j.description as job_description, j.requirements as job_requirements,
               c.name as company_name, c.logo as company_logo, c.industry as company_industry,
               c.website as company_website, c.email as company_email, c.phone as company_phone
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();

    if (!$app) {
        sendError('Application not found.', 404);
    }

    // Authorization: job seekers can only view their own applications
    // Employers can view applications for their own jobs
    // Admins can view any application
    if ($user['role'] === 'job_seeker' && $app['user_id'] != $user['user_id']) {
        sendError('You do not have permission to view this application.', 403);
    }

    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$app['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to view this application.', 403);
        }
    }

    // Get interview info if exists
    $interview = null;
    $stmt = $pdo->prepare("
        SELECT id, title, description, scheduled_at, duration, location, meeting_link, status, notes
        FROM interviews
        WHERE application_id = ?
        ORDER BY scheduled_at DESC
        LIMIT 1
    ");
    $stmt->execute([$applicationId]);
    $interview = $stmt->fetch();

    $appData = [
        'id'             => (int) $app['id'],
        'user_id'        => (int) $app['user_id'],
        'job_id'         => (int) $app['job_id'],
        'job_title'      => $app['job_title'] ?? null,
        'company_name'   => $app['company_name'] ?? null,
        'company_logo'   => $app['company_logo'] ?? null,
        'company_industry' => $app['company_industry'] ?? null,
        'company_website' => $app['company_website'] ?? null,
        'company_email'  => $app['company_email'] ?? null,
        'company_phone'  => $app['company_phone'] ?? null,
        'location'       => $app['location'] ?? null,
        'employment_type'=> $app['employment_type'] ?? null,
        'salary'         => $app['salary'] ?? null,
        'job_description'=> $app['job_description'] ?? null,
        'job_requirements'=> $app['job_requirements'] ? json_decode($app['job_requirements'], true) : [],
        'status'         => $app['status'],
        'cover_letter'   => $app['cover_letter'] ?? null,
        'cv_url'         => $app['cv_url'] ?? null,
        'applied_at'     => $app['applied_at'],
        'updated_at'     => $app['updated_at'],
        'interview'      => $interview ? [
            'id'           => (int) $interview['id'],
            'title'        => $interview['title'],
            'description'  => $interview['description'],
            'scheduled_at' => $interview['scheduled_at'],
            'duration'     => (int) $interview['duration'],
            'location'     => $interview['location'],
            'meeting_link' => $interview['meeting_link'],
            'status'       => $interview['status'],
            'notes'        => $interview['notes'],
        ] : null,
    ];

    sendSuccess(['application' => $appData], 'Application details retrieved successfully');

} catch (Exception $e) {
    logError("Get application details failed: " . $e->getMessage());
    sendError('An error occurred while retrieving application details.', 500);
}
