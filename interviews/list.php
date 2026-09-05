<?php
/**
 * List interviews for the authenticated user
 *
 * Endpoint: GET /api/interviews/list.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Query parameters:
 *   - status (string, optional) - Filter by status (scheduled, completed, cancelled, rescheduled)
 *   - page (int, default 1)
 *   - limit (int, default 20)
 *
 * For job seekers: returns interviews for their applications
 * For employers: returns interviews for their company's jobs
 * For admins: returns all interviews
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "interviews": [...],
 *     "pagination": {...}
 *   }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed. Use GET.', 405);
}

// Require authentication
$user = requireAuth();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$statusFilter = trim($_GET['status'] ?? '');

try {
    $pdo = getDB();

    // Build WHERE clause based on role
    $whereParts = [];
    $params = [];

    if ($user['role'] === 'job_seeker') {
        $whereParts[] = 'i.user_id = ?';
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'employer') {
        $whereParts[] = 'i.company_id IN (SELECT id FROM companies WHERE user_id = ?)';
        $params[] = $user['user_id'];
    }
    // Admins see all interviews

    if (!empty($statusFilter)) {
        $whereParts[] = 'i.status = ?';
        $params[] = $statusFilter;
    }

    $whereClause = implode(' AND ', $whereParts);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM interviews i
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];

    // Get interviews
    $stmt = $pdo->prepare("
        SELECT i.id, i.application_id, i.job_id, i.user_id, i.company_id,
               i.title, i.description, i.scheduled_at, i.duration, i.location,
               i.meeting_link, i.status, i.notes, i.created_at, i.updated_at,
               j.title as job_title,
               c.name as company_name, c.logo as company_logo,
               u.username as applicant_username, p.full_name as applicant_name
        FROM interviews i
        LEFT JOIN jobs j ON j.id = i.job_id
        LEFT JOIN companies c ON c.id = i.company_id
        LEFT JOIN users u ON u.id = i.user_id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE $whereClause
        ORDER BY i.scheduled_at ASC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $interviews = $stmt->fetchAll();

    $totalPages = ceil($total / $limit);

    // Format interviews
    $formattedInterviews = array_map(function($interview) {
        return [
            'id'             => (int) $interview['id'],
            'application_id' => (int) $interview['application_id'],
            'job_id'         => (int) $interview['job_id'],
            'job_title'      => $interview['job_title'] ?? null,
            'user_id'        => (int) $interview['user_id'],
            'applicant_name' => $interview['applicant_name'] ?? $interview['applicant_username'] ?? null,
            'company_id'     => (int) $interview['company_id'],
            'company_name'   => $interview['company_name'] ?? null,
            'company_logo'   => $interview['company_logo'] ?? null,
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
    }, $interviews);

    sendSuccess([
        'interviews' => $formattedInterviews,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int) $totalPages,
        ],
    ], 'Interviews retrieved successfully');

} catch (Exception $e) {
    logError("List interviews failed: " . $e->getMessage());
    sendError('An error occurred while retrieving interviews.', 500);
}
