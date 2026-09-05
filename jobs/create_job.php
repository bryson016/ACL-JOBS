<?php
/**
 * Create a new job listing
 *
 * Endpoint: POST /api/jobs/create_job.php
 *
 * Headers: Authorization: Bearer <token>
 * Required role: employer, admin
 *
 * Request body:
 * {
 *   "title": "Senior Developer",
 *   "description": "Job description...",
 *   "location": "Remote",
 *   "category": "Engineering",
 *   "employment_type": "Full-time",
 *   "salary": "120k - 150k",
 *   "requirements": ["5+ years experience", "React proficiency"],
 *   "status": "active"
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "message": "Job created successfully",
 *   "data": { "job": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Require authentication - only employers and admins can create jobs
$user = requireRole(['employer', 'admin']);

// Get input data
$input = getInput();

// Validate required fields
$errors = [];

if (empty($input['title'])) {
    $errors['title'] = 'Job title is required.';
}

if (empty($input['description'])) {
    $errors['description'] = 'Job description is required.';
}

if (empty($input['location'])) {
    $errors['location'] = 'Location is required.';
}

if (empty($input['employment_type'])) {
    $errors['employment_type'] = 'Employment type is required.';
}

if (!empty($errors)) {
    sendError('Validation failed.', 422, $errors);
}

try {
    $pdo = getDB();

    // Get the company for this user (if employer)
    $companyId = null;
    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user['user_id']]);
        $company = $stmt->fetch();
        if (!$company) {
            sendError('You must create a company profile before posting jobs.', 400);
        }
        $companyId = $company['id'];
    } else {
        // Admin can specify company_id
        $companyId = (int) ($input['company_id'] ?? 0);
        if ($companyId <= 0) {
            sendError('Company ID is required for admin job creation.', 422);
        }
    }

    // Validate company exists
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? LIMIT 1");
    $stmt->execute([$companyId]);
    if (!$stmt->fetch()) {
        sendError('Company not found.', 404);
    }

    // Validate status
    $status = $input['status'] ?? 'active';
    if (!in_array($status, ['active', 'closed', 'draft', 'archived'])) {
        $status = 'active';
    }

    // Format requirements as JSON
    $requirements = $input['requirements'] ?? [];
    if (is_array($requirements)) {
        $requirements = json_encode($requirements);
    }

    // Insert job
    $stmt = $pdo->prepare("
        INSERT INTO jobs (company_id, title, description, location, category, employment_type, salary, requirements, status, is_featured)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $companyId,
        $input['title'],
        $input['description'],
        $input['location'],
        $input['category'] ?? null,
        $input['employment_type'],
        $input['salary'] ?? null,
        $requirements,
        $status,
        isset($input['is_featured']) ? (int) $input['is_featured'] : 0,
    ]);

    $jobId = $pdo->lastInsertId();

    // Return the created job
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

    sendSuccess(['job' => $jobData], 'Job created successfully', 201);

} catch (Exception $e) {
    logError("Create job failed: " . $e->getMessage());
    sendError('An error occurred while creating the job.', 500);
}
