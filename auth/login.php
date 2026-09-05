<?php
/**
 * Login user and return authentication token
 *
 * Endpoint: POST /api/auth/login.php
 *
 * Request body:
 * {
 *   "email": "john@example.com",
 *   "password": "password123"
 * }
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "Login successful",
 *   "data": { "user": {...}, "token": "..." }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Get input data
$input = getInput();

// Validate required fields
if (empty($input['email'])) {
    sendError('Email is required.', 422, ['email' => 'Email is required.']);
}

if (empty($input['password'])) {
    sendError('Password is required.', 422, ['password' => 'Password is required.']);
}

try {
    $pdo = getDB();

    // Find user by email
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.password, u.role, u.is_active, u.created_at,
               p.full_name, p.phone, p.location, p.bio, p.profile_photo, p.cv_url
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([strtolower(trim($input['email']))]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('Invalid email or password.', 401);
    }

    if (!$user['is_active']) {
        sendError('Your account has been deactivated. Please contact support.', 403);
    }

    // Verify password
    if (!password_verify($input['password'], $user['password'])) {
        sendError('Invalid email or password.', 401);
    }

    // Generate token
    $token = generateJWT($user['id'], $user['role']);
    storeToken($user['id'], $token);

    // Return user data (without password)
    $userData = [
        'id'         => (int) $user['id'],
        'username'   => $user['username'],
        'email'      => $user['email'],
        'full_name'  => $user['full_name'] ?? null,
        'phone'      => $user['phone'] ?? null,
        'location'   => $user['location'] ?? null,
        'bio'        => $user['bio'] ?? null,
        'profile_photo' => $user['profile_photo'] ?? null,
        'cv_url'     => $user['cv_url'] ?? null,
        'role'       => $user['role'],
        'created_at' => $user['created_at'],
    ];

    sendSuccess([
        'user'  => $userData,
        'token' => $token,
    ], 'Login successful');

} catch (Exception $e) {
    logError("Login failed: " . $e->getMessage());
    sendError('An error occurred during login. Please try again.', 500);
}
