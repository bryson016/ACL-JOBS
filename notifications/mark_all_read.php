<?php
/**
 * Mark All Notifications as Read
 *
 * Marks all unread notifications for the authenticated user as read.
 *
 * Method: POST
 * Auth:   Required (Bearer token)
 *
 * Response (200):
 *   {"success": true, "message": "All notifications marked as read", "data": {"updated_count": 5}}
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Require authentication
$user = requireAuth();

try {
    $pdo = getDB();

    // Mark all unread notifications as read for this user
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1, updated_at = NOW()
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user['user_id']]);

    $updatedCount = $stmt->rowCount();

    sendSuccess([
        'updated_count' => $updatedCount,
    ], 'All notifications marked as read');
} catch (Exception $e) {
    logError("mark_all_read failed: " . $e->getMessage());
    sendError('An error occurred while marking notifications as read.', 500);
}
