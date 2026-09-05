<?php
/**
 * Delete a job listing
 *
 * Endpoint: DELETE /api/jobs/delete_job.php?id=<job_id>
 *
 * Headers: Authorization: Bearer <token>
 * Required role: employer (own jobs only), admin
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Job deleted successfully"
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow DELETE or POST requests
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE' && $method !== 'POST') {
    sendError('Method not allowed. Use DELETE or POST.', 405);
}

// Require authentication
$user = requireAuth();

// Get job ID
$jobId = (int) ($_GET['id'] ?? 0);
if ($jobId <= 0) {
    $input = getInput();
    $jobId = (int) ($input['id'] ?? 0);
}

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

    // Authorization: employer can only delete their own jobs, admin can delete any
    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$job['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to delete this job.', 403);
        }
    } elseif ($user['role'] !== 'admin') {
        sendError('You do not have permission to delete jobs.', 403);
    }

    // Delete the job (applications and saved_jobs will be cascade deleted)
    $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);

    sendSuccess(null, 'Job deleted successfully');

} catch (Exception $e) {
    logError("Delete job failed: " . $e->getMessage());
    sendError('An error occurred while deleting the job.', 500);
}
