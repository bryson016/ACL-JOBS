<?php
/**
 * Update application status
 *
 * Endpoint: PUT /api/applications/update_status.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * For job seekers: can only withdraw their own applications
 * For employers: can update status of applications for their jobs
 * For admins: can update any application
 *
 * Request body:
 * {
 *   "id": 1,
 *   "status": "interview",
 *   "cover_letter": "Updated cover letter..."  // optional, for job seekers
 * }
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Application status updated successfully",
 *   "data": { "application": {...} }
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

$applicationId = (int) ($input['id'] ?? 0);

if ($applicationId <= 0) {
    sendError('Application ID is required.', 400);
}

$validStatuses = ['pending', 'reviewing', 'shortlisted', 'interview', 'accepted', 'rejected', 'withdrawn'];
$newStatus = $input['status'] ?? '';

if (!in_array($newStatus, $validStatuses)) {
    sendError('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 422);
}

try {
    $pdo = getDB();

    // Get the application
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.job_id, a.status,
               j.title as job_title, j.company_id
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();

    if (!$app) {
        sendError('Application not found.', 404);
    }

    // Authorization checks
    if ($user['role'] === 'job_seeker') {
        // Job seekers can only update their own applications
        // And can only withdraw (set status to 'withdrawn')
        if ($app['user_id'] != $user['user_id']) {
            sendError('You do not have permission to modify this application.', 403);
        }

        if ($newStatus !== 'withdrawn') {
            sendError('You can only withdraw your own application.', 403);
        }
    } elseif ($user['role'] === 'employer') {
        // Employers can only update applications for their own jobs
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$app['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to modify this application.', 403);
        }
    }
    // Admins can update any application

    // Update the application
    $updates = ['status = ?'];
    $params = [$newStatus];

    // Job seekers can update their cover letter
    if ($user['role'] === 'job_seeker' && isset($input['cover_letter'])) {
        $updates[] = 'cover_letter = ?';
        $params[] = $input['cover_letter'];
    }

    $params[] = $applicationId;
    $stmt = $pdo->prepare("UPDATE applications SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    // Create notification for status change
    if ($newStatus === 'interview') {
        createNotification(
            $app['user_id'],
            'interview_scheduled',
            'Interview Scheduled',
            'Your application for "' . $app['job_title'] . '" has been moved to the interview stage.',
            ['application_id' => $applicationId, 'job_id' => $app['job_id']]
        );
    } elseif ($newStatus === 'accepted') {
        createNotification(
            $app['user_id'],
            'application_accepted',
            'Application Accepted',
            'Congratulations! Your application for "' . $app['job_title'] . '" has been accepted.',
            ['application_id' => $applicationId, 'job_id' => $app['job_id']]
        );
    } elseif ($newStatus === 'rejected') {
        createNotification(
            $app['user_id'],
            'application_rejected',
            'Application Update',
            'Your application for "' . $app['job_title'] . '" has been rejected.',
            ['application_id' => $applicationId, 'job_id' => $app['job_id']]
        );
    } elseif ($newStatus === 'withdrawn') {
        createNotification(
            $app['user_id'],
            'application_withdrawn',
            'Application Withdrawn',
            'You have withdrawn your application for "' . $app['job_title'] . '".',
            ['application_id' => $applicationId, 'job_id' => $app['job_id']]
        );
    }

    // Return updated application
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

    sendSuccess(['application' => $appData], 'Application status updated successfully');

} catch (Exception $e) {
    logError("Update application status failed: " . $e->getMessage());
    sendError('An error occurred while updating the application.', 500);
}
