<?php
/**
 * Get notifications for the authenticated user
 *
 * Endpoint: GET /api/notifications/list.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Query parameters:
 *   - unread_only (int, optional) - 1 to get only unread notifications
 *   - page (int, default 1)
 *   - limit (int, default 20)
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "notifications": [...],
 *     "unread_count": 5,
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
$unreadOnly = (int) ($_GET['unread_only'] ?? 0);

try {
    $pdo = getDB();

    // Build WHERE clause
    $whereParts = ['user_id = ?'];
    $params = [$user['user_id']];

    if ($unreadOnly) {
        $whereParts[] = 'is_read = 0';
    }

    $whereClause = implode(' AND ', $whereParts);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM notifications
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];

    // Get unread count
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $unreadStmt->execute([$user['user_id']]);
    $unreadCount = (int) $unreadStmt->fetch()['unread_count'];

    // Get notifications
    $stmt = $pdo->prepare("
        SELECT id, user_id, type, title, message, data, is_read, created_at, updated_at
        FROM notifications
        WHERE $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    $totalPages = ceil($total / $limit);

    // Format notifications
    $formattedNotifications = array_map(function($n) {
        return [
            'id'         => (int) $n['id'],
            'type'       => $n['type'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'data'       => $n['data'] ? json_decode($n['data'], true) : null,
            'is_read'    => (bool) $n['is_read'],
            'created_at' => $n['created_at'],
            'updated_at' => $n['updated_at'],
        ];
    }, $notifications);

    sendSuccess([
        'notifications' => $formattedNotifications,
        'unread_count'  => $unreadCount,
        'pagination'    => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int) $totalPages,
        ],
    ], 'Notifications retrieved successfully');

} catch (Exception $e) {
    logError("Get notifications failed: " . $e->getMessage());
    sendError('An error occurred while retrieving notifications.', 500);
}
