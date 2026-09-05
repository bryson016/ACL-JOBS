<?php
/**
 * Update an interview
 *
 * Endpoint: PUT /api/interviews/update.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * For employers: can update interviews for their company's jobs
 * For job seekers: can only view (not update)
 * For admins: can update any interview
 *
 * Request body:
 * {
 *   "id": 1,
 *   "title": "Updated Title",
 *   "description": "Updated description",
 *   "scheduled_at": "2024-02-20 15:00:00",
 *   "duration": 45,
 *   "location": "Updated location",
 *   "meeting_link": "https://zoom.us/j/987654321",
 *   "status": "rescheduled",
 *   "notes": "Updated notes"
 * }
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Interview updated successfully",
 *   "data": { "interview": {...} }
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

$interviewId = (int) ($input['id'] ?? 0);

if ($interviewId <= 0) {
    sendError('Interview ID is required.', 400);
}

try {
    $pdo = getDB();

    // Get the interview
    $stmt = $pdo->prepare("
        SELECT i.id, i.application_id, i.job_id, i.user_id, i.company_id,
               j.title as job_title
        FROM interviews i
        LEFT JOIN jobs j ON j.id = i.job_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$interviewId]);
    $interview = $stmt->fetch();

    if (!$interview) {
        sendError('Interview not found.', 404);
    }

    // Authorization: only employers and admins can update interviews
    if ($user['role'] === 'employer') {
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$interview['company_id']]);
        $company = $stmt->fetch();

        if (!$company || $company['user_id'] != $user['user_id']) {
            sendError('You do not have permission to modify this interview.', 403);
        }
    } elseif ($user['role'] === 'job_seeker') {
        sendError('You do not have permission to modify interviews.', 403);
    }

    // Build update query
    $updates = [];
    $params = [];

    $updatableFields = [
        'title', 'description', 'scheduled_at', 'duration',
        'location', 'meeting_link', 'status', 'notes',
    ];

    foreach ($updatableFields as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }

    if (empty($updates)) {
        sendError('No fields to update.', 400);
    }

    $params[] = $interviewId;
    $stmt = $pdo->prepare("UPDATE interviews SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    // If status changed to completed, create notification
    if (isset($input['status']) && $input['status'] === 'completed') {
        createNotification(
            $interview['user_id'],
            'interview_completed',
            'Interview Completed',
            'Your interview for "' . $interview['job_title'] . '" has been marked as completed.',
            ['interview_id' => $interviewId, 'application_id' => $interview['application_id']]
        );
    }

    // Return updated interview
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

    sendSuccess(['interview' => $interviewData], 'Interview updated successfully');

} catch (Exception $e) {
    logError("Update interview failed: " . $e->getMessage());
    sendError('An error occurred while updating the interview.', 500);
}
