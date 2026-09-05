<?php
/**
 * Get a single job by ID
 *
 * Endpoint: GET /api/jobs/get_job.php?id=<job_id>
 *
 * Query parameters:
 *   - id (int, required) - Job ID
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": { "job": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed. Use GET.', 405);
}

// Get job ID
$jobId = (int) ($_GET['id'] ?? 0);

if ($jobId <= 0) {
    sendError('Job ID is required.', 400);
}

// Optional authentication
$user = authenticate();

try {
    $pdo = getDB();

    // Get job details
    $stmt = $pdo->prepare("
        SELECT j.id, j.company_id, j.title, j.description, j.location, j.category,
               j.employment_type, j.salary, j.requirements, j.status, j.is_featured,
               j.views_count, j.created_at, j.updated_at,
               c.name as company_name, c.logo as company_logo, c.industry as company_industry,
               c.description as company_description, c.website as company_website,
               c.email as company_email, c.phone as company_phone, c.address as company_address
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE j.id = ?
        LIMIT 1
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        sendError('Job not found.', 404);
    }

    // Increment view count
    $stmt = $pdo->prepare("UPDATE jobs SET views_count = views_count + 1 WHERE id = ?");
    $stmt->execute([$jobId]);

    // Check if user has saved this job
    $isSaved = false;
    if ($user) {
        $savedStmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
        $savedStmt->execute([$user['user_id'], $jobId]);
        $isSaved = (bool) $savedStmt->fetch();
    }

    // Check if user has applied to this job
    $hasApplied = false;
    $applicationStatus = null;
    if ($user) {
        $appStmt = $pdo->prepare("SELECT id, status FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
        $appStmt->execute([$user['user_id'], $jobId]);
        $app = $appStmt->fetch();
        if ($app) {
            $hasApplied = true;
            $applicationStatus = $app['status'];
        }
    }

    $jobData = [
        'id'              => (int) $job['id'],
        'company_id'      => (int) $job['company_id'],
        'company_name'    => $job['company_name'] ?? null,
        'company_logo'    => $job['company_logo'] ?? null,
        'company_industry'=> $job['company_industry'] ?? null,
        'company_description' => $job['company_description'] ?? null,
        'company_website' => $job['company_website'] ?? null,
        'company_email'   => $job['company_email'] ?? null,
        'company_phone'   => $job['company_phone'] ?? null,
        'company_address' => $job['company_address'] ?? null,
        'title'           => $job['title'],
        'description'     => $job['description'],
        'location'        => $job['location'],
        'category'        => $job['category'] ?? null,
        'employment_type' => $job['employment_type'],
        'salary'          => $job['salary'] ?? null,
        'requirements'    => $job['requirements'] ? json_decode($job['requirements'], true) : [],
        'status'          => $job['status'],
        'is_featured'     => (bool) $job['is_featured'],
        'views_count'     => (int) $job['views_count'],
        'is_saved'        => $isSaved,
        'has_applied'     => $hasApplied,
        'application_status' => $applicationStatus,
        'created_at'      => $job['created_at'],
        'updated_at'      => $job['updated_at'],
    ];

    sendSuccess(['job' => $jobData], 'Job retrieved successfully');

} catch (Exception $e) {
    logError("Get job failed: " . $e->getMessage());
    sendError('An error occurred while retrieving the job.', 500);
}
