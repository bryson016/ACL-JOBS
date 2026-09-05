<?php
/**
 * Get the authenticated user's saved jobs
 *
 * Endpoint: GET /api/saved_jobs/my_saved_jobs.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Query parameters:
 *   - page (int, default 1)
 *   - limit (int, default 20)
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "saved_jobs": [...],
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

try {
    $pdo = getDB();

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM saved_jobs WHERE user_id = ?");
    $countStmt->execute([$user['user_id']]);
    $total = (int) $countStmt->fetch()['total'];

    // Get saved jobs
    $stmt = $pdo->prepare("
        SELECT sj.id, sj.user_id, sj.job_id, sj.saved_at,
               j.title, j.company_id, j.location, j.employment_type, j.salary,
               j.description, j.requirements, j.is_featured, j.created_at as job_created_at,
               c.name as company_name, c.logo as company_logo
        FROM saved_jobs sj
        LEFT JOIN jobs j ON j.id = sj.job_id
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE sj.user_id = ?
        ORDER BY sj.saved_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user['user_id'], $limit, $offset]);
    $savedJobs = $stmt->fetchAll();

    $totalPages = ceil($total / $limit);

    // Format saved jobs
    $formattedJobs = array_map(function($job) {
        return [
            'id'              => (int) $job['id'],
            'job_id'          => (int) $job['job_id'],
            'job_title'       => $job['title'] ?? null,
            'company_name'    => $job['company_name'] ?? null,
            'company_logo'    => $job['company_logo'] ?? null,
            'location'        => $job['location'] ?? null,
            'employment_type' => $job['employment_type'] ?? null,
            'salary'          => $job['salary'] ?? null,
            'description'     => $job['description'] ?? null,
            'requirements'    => $job['requirements'] ? json_decode($job['requirements'], true) : [],
            'is_featured'     => (bool) $job['is_featured'],
            'saved_at'        => $job['saved_at'],
            'job_created_at'  => $job['job_created_at'] ?? null,
        ];
    }, $savedJobs);

    sendSuccess([
        'saved_jobs' => $formattedJobs,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int) $totalPages,
        ],
    ], 'Saved jobs retrieved successfully');

} catch (Exception $e) {
    logError("Get saved jobs failed: " . $e->getMessage());
    sendError('An error occurred while retrieving saved jobs.', 500);
}
