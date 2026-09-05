<?php
/**
 * Save a job to the user's saved jobs list
 *
 * Endpoint: POST /api/saved_jobs/save_job.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Request body:
 * {
 *   "job_id": 1
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "message": "Job saved successfully",
 *   "data": { "saved": true }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Require authentication
$user = requireAuth();

// Get input data
$input = getInput();

$jobId = (int) ($input['job_id'] ?? 0);

if ($jobId <= 0) {
    sendError('Job ID is required.', 400);
}

try {
    $pdo = getDB();

    // Check if job exists
    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    if (!$stmt->fetch()) {
        sendError('Job not found.', 404);
    }

    // Check if already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([$user['user_id'], $jobId]);
    if ($stmt->fetch()) {
        sendError('Job is already saved.', 409);
    }

    // Save the job
    $stmt = $pdo->prepare("INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)");
    $stmt->execute([$user['user_id'], $jobId]);

    sendSuccess(['saved' => true], 'Job saved successfully', 201);

} catch (Exception $e) {
    logError("Save job failed: " . $e->getMessage());
    sendError('An error occurred while saving the job.', 500);
}
