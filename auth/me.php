<?php
/**
 * Get the currently authenticated user's information
 *
 * Endpoint: GET /api/auth/me.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Response (200):
 * {
 *   "success": true,
 *   "data": { "user": {...} }
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

// Get full user data
$userData = getUserWithProfile($user['user_id']);

if (!$userData) {
    sendError('User not found.', 404);
}

// Build the response
$responseData = [
    'id'                   => (int) $userData['id'],
    'username'             => $userData['username'],
    'email'                => $userData['email'],
    'full_name'            => $userData['full_name'] ?? null,
    'phone'                => $userData['phone'] ?? null,
    'location'             => $userData['location'] ?? null,
    'bio'                  => $userData['bio'] ?? null,
    'profile_photo'        => $userData['profile_photo'] ?? null,
    'cv_url'               => $userData['cv_url'] ?? null,
    'role'                 => $userData['role'],
    'is_active'            => (bool) $userData['is_active'],
    'created_at'           => $userData['created_at'],
    'updated_at'           => $userData['updated_at'],
];

sendSuccess(['user' => $responseData], 'User information retrieved successfully');
