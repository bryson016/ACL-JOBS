<?php
/**
 * Mark notifications as read
 *
 * Endpoint: POST /api/notifications/mark_read.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Request body:
 * {
 *   "id": 1  // Optional: mark a single notification as read
 *   // If no id provided, marks all notifications as read
 * }
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Notification(s) marked as read",
 *   "data": { "marked_count": 5 }
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

try {
    $pdo = getDB();

    if (isset($input['id']) && $input['id'] > 0) {
        // Mark a single notification as read
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([(int) $input['id'], $user['user_id']]);

        if ($stmt->rowCount() === 0) {
            sendError('Notification not found.', 404);
        }

        sendSuccess(['marked_count' => 1], 'Notification marked as read');
    } else {
        // Mark all notifications as read
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['user_id']]);

        $markedCount = $stmt->rowCount();

        sendSuccess(['marked_count' => $markedCount], 'All notifications marked as read');
    }

} catch (Exception $e) {
    logError("Mark notification read failed: " . $e->getMessage());
    sendError('An error occurred while marking notifications as read.', 500);
}
