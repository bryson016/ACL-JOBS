<?php
/**
 * Get the authenticated user's applications
 *
 * Endpoint: GET /api/applications/my_applications.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Query parameters:
 *   - status (string, optional) - Filter by status
 *   - page (int, default 1)
 *   - limit (int, default 20)
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "applications": [...],
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
    $whereParts = ['a.user_id = ?'];
    $params = [$user['user_id']];

    if (!empty($statusFilter)) {
        $whereParts[] = 'a.status = ?';
        $params[] = $statusFilter;
    }

    $whereClause = implode(' AND ', $whereParts);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM applications a
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];

    // Get applications
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.job_id, a.status, a.cover_letter, a.cv_url,
               a.applied_at, a.updated_at,
               j.title as job_title, j.company_id, j.location, j.employment_type, j.salary,
               c.name as company_name, c.logo as company_logo
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE $whereClause
        ORDER BY a.applied_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    $totalPages = ceil($total / $limit);

    // Format applications
    $formattedApps = array_map(function($app) {
        return [
            'id'             => (int) $app['id'],
            'user_id'        => (int) $app['user_id'],
            'job_id'         => (int) $app['job_id'],
            'job_title'      => $app['job_title'] ?? null,
            'company_name'   => $app['company_name'] ?? null,
            'company_logo'   => $app['company_logo'] ?? null,
            'location'       => $app['location'] ?? null,
            'employment_type'=> $app['employment_type'] ?? null,
            'salary'         => $app['salary'] ?? null,
            'status'         => $app['status'],
            'cover_letter'   => $app['cover_letter'] ?? null,
            'cv_url'         => $app['cv_url'] ?? null,
            'applied_at'     => $app['applied_at'],
            'updated_at'     => $app['updated_at'],
        ];
    }, $applications);

    sendSuccess([
        'applications' => $formattedApps,
        'pagination'   => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int) $totalPages,
        ],
    ], 'Applications retrieved successfully');

} catch (Exception $e) {
    logError("Get applications failed: " . $e->getMessage());
    sendError('An error occurred while retrieving applications.', 500);
}
