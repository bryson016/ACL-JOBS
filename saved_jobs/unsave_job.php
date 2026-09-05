<?php
/**
 * Remove a job from the user's saved jobs list
 *
 * Endpoint: DELETE /api/saved_jobs/unsave_job.php?job_id=<job_id>
 *
 * Headers: Authorization: Bearer <token>
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Job removed from saved jobs",
 *   "data": { "saved": false }
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
$jobId = (int) ($_GET['job_id'] ?? 0);
if ($jobId <= 0) {
    $input = getInput();
    $jobId = (int) ($input['job_id'] ?? 0);
}

if ($jobId <= 0) {
    sendError('Job ID is required.', 400);
}

try {
    $pdo = getDB();

    // Check if saved
    $stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([$user['user_id'], $jobId]);
    if (!$stmt->fetch()) {
        sendError('Job is not in your saved list.', 404);
    }

    // Remove from saved
    $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$user['user_id'], $jobId]);

    sendSuccess(['saved' => false], 'Job removed from saved jobs');

} catch (Exception $e) {
    logError("Unsave job failed: " . $e->getMessage());
    sendError('An error occurred while removing the job from saved list.', 500);
}
