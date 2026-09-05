<?php
/**
 * List, search, and filter jobs
 *
 * Endpoint: GET /api/jobs/get_jobs.php
 *
 * Query parameters:
 *   - page (int, default 1)
 *   - limit (int, default 20)
 *   - search (string, optional)
 *   - category (string, optional)
 *   - employment_type (string, optional)
 *   - location (string, optional)
 *   - status (string, optional, default 'active')
 *   - company_id (int, optional)
 *   - featured (int, optional)
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "jobs": [...],
 *     "pagination": { "page": 1, "limit": 20, "total": 100, "total_pages": 5 }
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

// Optional authentication - if authenticated, we can show more data
$user = authenticate();

// Get query parameters
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$employmentType = trim($_GET['employment_type'] ?? '');
$location = trim($_GET['location'] ?? '');
$status = trim($_GET['status'] ?? 'active');
$companyId = (int) ($_GET['company_id'] ?? 0);
$featured = isset($_GET['featured']) ? (int) $_GET['featured'] : null;

try {
    $pdo = getDB();

    // Build WHERE clause
    $whereParts = [];
    $params = [];

    // Default: only show active jobs (unless employer/admin viewing their own)
    if ($user && in_array($user['role'], ['employer', 'admin'])) {
        if ($status !== 'all') {
            $whereParts[] = 'j.status = ?';
            $params[] = $status;
        }
    } else {
        $whereParts[] = 'j.status = ?';
        $params[] = $status;
    }

    if (!empty($search)) {
        $whereParts[] = '(j.title LIKE ? OR j.description LIKE ? OR j.requirements LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($category)) {
        $whereParts[] = 'j.category = ?';
        $params[] = $category;
    }

    if (!empty($employmentType)) {
        $whereParts[] = 'j.employment_type = ?';
        $params[] = $employmentType;
    }

    if (!empty($location)) {
        $whereParts[] = 'j.location LIKE ?';
        $params[] = '%' . $location . '%';
    }

    if ($companyId > 0) {
        $whereParts[] = 'j.company_id = ?';
        $params[] = $companyId;
    }

    if ($featured !== null) {
        $whereParts[] = 'j.is_featured = ?';
        $params[] = $featured;
    }

    $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        $whereClause
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];

    // Get jobs
    $stmt = $pdo->prepare("
        SELECT j.id, j.company_id, j.title, j.description, j.location, j.category,
               j.employment_type, j.salary, j.requirements, j.status, j.is_featured,
               j.views_count, j.created_at, j.updated_at,
               c.name as company_name, c.logo as company_logo, c.industry as company_industry
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        $whereClause
        ORDER BY j.is_featured DESC, j.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    // Check if user has saved any jobs (if authenticated)
    $savedJobIds = [];
    if ($user) {
        $savedStmt = $pdo->prepare("
            SELECT job_id FROM saved_jobs WHERE user_id = ?
        ");
        $savedStmt->execute([$user['user_id']]);
        $savedJobIds = array_column($savedStmt->fetchAll(), 'job_id');
    }

    // Format jobs
    $formattedJobs = array_map(function($job) use ($savedJobIds) {
        return [
            'id'              => (int) $job['id'],
            'company_id'      => (int) $job['company_id'],
            'company_name'    => $job['company_name'] ?? null,
            'company_logo'    => $job['company_logo'] ?? null,
            'company_industry'=> $job['company_industry'] ?? null,
            'title'           => $job['title'],
            'description'     => $job['description'],
            'location'        => $job['location'],
            'category'        => $job['category'] ?? null,
            'employment_type' => $job['employment_type'],
            'salary'          => $job['salary'] ?? null,
            'requirements'    => $job['requirements'] ? json_decode($job['requirements'], true) : [],
            'status'          => $job['status'],
            'is_featured'     => (bool) $job['is_featured'],
            'views_count'     => (int) $job['views_count'],
            'is_saved'        => in_array($job['id'], $savedJobIds),
            'created_at'      => $job['created_at'],
            'updated_at'      => $job['updated_at'],
        ];
    }, $jobs);

    $totalPages = ceil($total / $limit);

    sendSuccess([
        'jobs'       => $formattedJobs,
        'pagination' => [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'total_pages' => (int) $totalPages,
        ],
    ], 'Jobs retrieved successfully');

} catch (Exception $e) {
    logError("Get jobs failed: " . $e->getMessage());
    sendError('An error occurred while retrieving jobs.', 500);
}
