<?php
/**
 * Apply for a job
 *
 * Endpoint: POST /api/applications/apply.php
 *
 * Headers: Authorization: Bearer <token>
 * Required role: job_seeker
 *
 * Request body:
 * {
 *   "job_id": 1,
 *   "cover_letter": "Dear Hiring Manager...",
 *   "cv_url": "https://example.com/resume.pdf"
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "message": "Application submitted successfully",
 *   "data": { "application": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Require authentication - only job seekers can apply
$user = requireRole('job_seeker');

// Get input data
$input = getInput();

$jobId = (int) ($input['job_id'] ?? 0);

if ($jobId <= 0) {
    sendError('Job ID is required.', 400);
}

try {
    $pdo = getDB();

    // Check if job exists and is active
    $stmt = $pdo->prepare("SELECT id, title, company_id FROM jobs WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        sendError('Job not found or is no longer accepting applications.', 404);
    }

    // Check for duplicate application
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([$user['user_id'], $jobId]);
    if ($stmt->fetch()) {
        sendError('You have already applied for this job.', 409);
    }

    // Insert application
    $stmt = $pdo->prepare("
        INSERT INTO applications (user_id, job_id, status, cover_letter, cv_url)
        VALUES (?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $user['user_id'],
        $jobId,
        $input['cover_letter'] ?? null,
        $input['cv_url'] ?? null,
    ]);

    $applicationId = $pdo->lastInsertId();

    // Create notification for the applicant
    createNotification(
        $user['user_id'],
        'application_submitted',
        'Application Submitted',
        'Your application for "' . $job['title'] . '" has been submitted successfully.',
        ['job_id' => $jobId, 'application_id' => $applicationId]
    );

    // Get company user for notification
    $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
    $stmt->execute([$job['company_id']]);
    $company = $stmt->fetch();

    if ($company) {
        // Create notification for the employer
        createNotification(
            $company['user_id'],
            'new_application',
            'New Application Received',
            'A new application has been received for "' . $job['title'] . '".',
            ['job_id' => $jobId, 'application_id' => $applicationId, 'applicant_id' => $user['user_id']]
        );
    }

    // Return application data
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.job_id, a.status, a.cover_letter, a.cv_url,
               a.applied_at, a.updated_at,
               j.title as job_title, j.company_id,
               c.name as company_name
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();

    $appData = [
        'id'           => (int) $app['id'],
        'user_id'      => (int) $app['user_id'],
        'job_id'       => (int) $app['job_id'],
        'job_title'    => $app['job_title'] ?? null,
        'company_name' => $app['company_name'] ?? null,
        'status'       => $app['status'],
        'cover_letter' => $app['cover_letter'] ?? null,
        'cv_url'       => $app['cv_url'] ?? null,
        'applied_at'   => $app['applied_at'],
        'updated_at'   => $app['updated_at'],
    ];

    sendSuccess(['application' => $appData], 'Application submitted successfully', 201);

} catch (Exception $e) {
    logError("Apply for job failed: " . $e->getMessage());
    sendError('An error occurred while submitting your application.', 500);
}
