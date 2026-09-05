<?php
/**
 * Get dashboard statistics for the authenticated user
 *
 * Endpoint: GET /api/dashboard/stats.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": {
 *     "stats": {
 *       "applications": 12,
 *       "interviews": 5,
 *       "offers": 3,
 *       "saved_jobs": 8,
 *       "unread_notifications": 2
 *     }
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

try {
    $pdo = getDB();

    $stats = [];

    if ($user['role'] === 'job_seeker') {
        // Count applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $stats['applications'] = (int) $stmt->fetch()['count'];

        // Count interviews (upcoming)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM interviews
            WHERE user_id = ? AND status = 'scheduled' AND scheduled_at > NOW()
        ");
        $stmt->execute([$user['user_id']]);
        $stats['interviews'] = (int) $stmt->fetch()['count'];

        // Count offers (accepted applications)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM applications
            WHERE user_id = ? AND status = 'accepted'
        ");
        $stmt->execute([$user['user_id']]);
        $stats['offers'] = (int) $stmt->fetch()['count'];

        // Count saved jobs
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM saved_jobs WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $stats['saved_jobs'] = (int) $stmt->fetch()['count'];

        // Count unread notifications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['user_id']]);
        $stats['unread_notifications'] = (int) $stmt->fetch()['count'];

    } elseif ($user['role'] === 'employer') {
        // Get company IDs for this employer
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $companyIds = array_column($stmt->fetchAll(), 'id');

        if (!empty($companyIds)) {
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';

            // Count jobs posted
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM jobs
                WHERE company_id IN ($placeholders)
            ");
            $stmt->execute($companyIds);
            $stats['jobs_posted'] = (int) $stmt->fetch()['count'];

            // Count applications received
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                WHERE j.company_id IN ($placeholders)
            ");
            $stmt->execute($companyIds);
            $stats['applications_received'] = (int) $stmt->fetch()['count'];

            // Count interviews scheduled
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM interviews
                WHERE company_id IN ($placeholders)
            ");
            $stmt->execute($companyIds);
            $stats['interviews'] = (int) $stmt->fetch()['count'];

            // Count pending applications
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                WHERE j.company_id IN ($placeholders) AND a.status = 'pending'
            ");
            $stmt->execute($companyIds);
            $stats['pending_applications'] = (int) $stmt->fetch()['count'];
        } else {
            $stats['jobs_posted'] = 0;
            $stats['applications_received'] = 0;
            $stats['interviews'] = 0;
            $stats['pending_applications'] = 0;
        }

        // Count unread notifications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['user_id']]);
        $stats['unread_notifications'] = (int) $stmt->fetch()['count'];

    } elseif ($user['role'] === 'admin') {
        // Admin stats
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'job_seeker'");
        $stmt->execute();
        $stats['total_job_seekers'] = (int) $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'employer'");
        $stmt->execute();
        $stats['total_employers'] = (int) $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jobs");
        $stmt->execute();
        $stats['total_jobs'] = (int) $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications");
        $stmt->execute();
        $stats['total_applications'] = (int) $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM interviews WHERE status = 'scheduled'");
        $stmt->execute();
        $stats['upcoming_interviews'] = (int) $stmt->fetch()['count'];

        // Count unread notifications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['user_id']]);
        $stats['unread_notifications'] = (int) $stmt->fetch()['count'];
    }

    sendSuccess(['stats' => $stats], 'Dashboard statistics retrieved successfully');

} catch (Exception $e) {
    logError("Get dashboard stats failed: " . $e->getMessage());
    sendError('An error occurred while retrieving dashboard statistics.', 500);
}
