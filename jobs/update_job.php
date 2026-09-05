<?php
/**
 * Update an existing job listing
 *
 * Endpoint: PUT /api/jobs/update_job.php
 *
 * Headers: Authorization: Bearer <token>
 * Required role: employer (own jobs only), admin
 *
 * Request body:
 * {
 *   "id": 1,
 *   "title": "Updated Title",
 *   "description": "Updated description...",
 *   ...
 * }
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Job updated successfully",
 *   "data": { "job": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow PUT or POST requests
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT' && $method !== 'POST') {
    sendError('Method not allowed. Use PUT or POST.', 405);
}

// Require authentication
$user = requireAuth();

// Get input data
$input = getInput();

$jobId = (int) ($input['id'] ?? 0);

if ($jobId <= 0) {
    sendError('Job ID is required.', 400);
}

try {
    $pdo = getDB();

    // Get the job
    $stmt = $pdo->prepare("SELECT id, company_id FROM jobs WHERE id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        sendError('Job not found.', 404);
    }

    // Authorization: employer can only update their own jobs, admin can update any
    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$job['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to modify this job.', 403);
        }
    } elseif ($user['role'] !== 'admin') {
        sendError('You do not have permission to modify jobs.', 403);
    }

    // Build update query
    $updates = [];
    $params = [];

    $updatableFields = [
        'title', 'description', 'location', 'category', 'employment_type',
        'salary', 'status', 'is_featured',
    ];

    foreach ($updatableFields as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }

    // Handle requirements as JSON
    if (isset($input['requirements'])) {
        $requirements = $input['requirements'];
        if (is_array($requirements)) {
            $requirements = json_encode($requirements);
        }
        $updates[] = 'requirements = ?';
        $params[] = $requirements;
    }

    if (empty($updates)) {
        sendError('No fields to update.', 400);
    }

    $params[] = $jobId;
    $stmt = $pdo->prepare("UPDATE jobs SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    // Return updated job
    $stmt = $pdo->prepare("
        SELECT j.id, j.company_id, j.title, j.description, j.location, j.category,
               j.employment_type, j.salary, j.requirements, j.status, j.is_featured,
               j.views_count, j.created_at, j.updated_at,
               c.name as company_name, c.logo as company_logo
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE j.id = ?
        LIMIT 1
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    $jobData = [
        'id'              => (int) $job['id'],
        'company_id'      => (int) $job['company_id'],
        'company_name'    => $job['company_name'] ?? null,
        'company_logo'    => $job['company_logo'] ?? null,
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
        'created_at'      => $job['created_at'],
        'updated_at'      => $job['updated_at'],
    ];

    sendSuccess(['job' => $jobData], 'Job updated successfully');

} catch (Exception $e) {
    logError("Update job failed: " . $e->getMessage());
    sendError('An error occurred while updating the job.', 500);
}
