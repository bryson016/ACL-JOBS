<?php
/**
 * Create a new interview
 *
 * Endpoint: POST /api/interviews/create.php
 *
 * Headers: Authorization: Bearer <token>
 * Required role: employer (for their jobs' applications), admin
 *
 * Request body:
 * {
 *   "application_id": 1,
 *   "title": "Technical Interview",
 *   "description": "30-minute technical screening",
 *   "scheduled_at": "2024-02-15 14:00:00",
 *   "duration": 60,
 *   "location": "Remote",
 *   "meeting_link": "https://zoom.us/j/123456789"
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "message": "Interview scheduled successfully",
 *   "data": { "interview": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Require authentication - only employers and admins can schedule interviews
$user = requireRole(['employer', 'admin']);

// Get input data
$input = getInput();

$applicationId = (int) ($input['application_id'] ?? 0);

if ($applicationId <= 0) {
    sendError('Application ID is required.', 400);
}

if (empty($input['title'])) {
    sendError('Interview title is required.', 422);
}

if (empty($input['scheduled_at'])) {
    sendError('Scheduled date and time is required.', 422);
}

try {
    $pdo = getDB();

    // Get the application and verify ownership
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

    // Authorization: employer can only schedule interviews for their own jobs
    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$app['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to schedule interviews for this application.', 403);
        }
    }

    // Create the interview
    $stmt = $pdo->prepare("
        INSERT INTO interviews (
            application_id, job_id, user_id, company_id, title, description,
            scheduled_at, duration, location, meeting_link, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    $stmt->execute([
        $applicationId,
        $app['job_id'],
        $app['user_id'],
        $app['company_id'],
        $input['title'],
        $input['description'] ?? null,
        $input['scheduled_at'],
        (int) ($input['duration'] ?? 60),
        $input['location'] ?? null,
        $input['meeting_link'] ?? null,
    ]);

    $interviewId = $pdo->lastInsertId();

    // Update application status to 'interview'
    $stmt = $pdo->prepare("UPDATE applications SET status = 'interview' WHERE id = ?");
    $stmt->execute([$applicationId]);

    // Create notification for the applicant
    createNotification(
        $app['user_id'],
        'interview_scheduled',
        'Interview Scheduled',
        'An interview has been scheduled for your application to "' . $app['job_title'] . '".',
        ['interview_id' => $interviewId, 'application_id' => $applicationId, 'job_id' => $app['job_id']]
    );

    // Return interview data
    $stmt = $pdo->prepare("
        SELECT i.id, i.application_id, i.job_id, i.user_id, i.company_id,
               i.title, i.description, i.scheduled_at, i.duration, i.location,
               i.meeting_link, i.status, i.notes, i.created_at, i.updated_at,
               j.title as job_title,
               c.name as company_name
        FROM interviews i
        LEFT JOIN jobs j ON j.id = i.job_id
        LEFT JOIN companies c ON c.id = i.company_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$interviewId]);
    $interview = $stmt->fetch();

    $interviewData = [
        'id'             => (int) $interview['id'],
        'application_id' => (int) $interview['application_id'],
        'job_id'         => (int) $interview['job_id'],
        'job_title'      => $interview['job_title'] ?? null,
        'user_id'        => (int) $interview['user_id'],
        'company_id'     => (int) $interview['company_id'],
        'company_name'   => $interview['company_name'] ?? null,
        'title'          => $interview['title'],
        'description'    => $interview['description'] ?? null,
        'scheduled_at'   => $interview['scheduled_at'],
        'duration'       => (int) $interview['duration'],
        'location'       => $interview['location'] ?? null,
        'meeting_link'   => $interview['meeting_link'] ?? null,
        'status'         => $interview['status'],
        'notes'          => $interview['notes'] ?? null,
        'created_at'     => $interview['created_at'],
        'updated_at'     => $interview['updated_at'],
    ];

    sendSuccess(['interview' => $interviewData], 'Interview scheduled successfully', 201);

} catch (Exception $e) {
    logError("Create interview failed: " . $e->getMessage());
    sendError('An error occurred while scheduling the interview.', 500);
}
