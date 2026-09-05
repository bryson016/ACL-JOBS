<?php
/**
 * Logout user and revoke authentication token
 *
 * Endpoint: POST /api/auth/logout.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Logout successful"
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Get the token from the Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (empty($authHeader)) {
    // If no token, just return success (user was already logged out)
    sendSuccess(null, 'Logout successful');
}

// Extract token
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
} else {
    $token = $authHeader;
}

$token = trim($token);

if (empty($token)) {
    sendSuccess(null, 'Logout successful');
}

// Revoke the token
revokeToken($token);

sendSuccess(null, 'Logout successful');
