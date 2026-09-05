<?php
/**
 * Register a new user
 *
 * Endpoint: POST /api/auth/register.php
 *
 * Request body:
 * {
 *   "full_name": "John Doe",
 *   "username": "johndoe",
 *   "email": "john@example.com",
 *   "phone": "+1234567890",
 *   "password": "password123",
 *   "role": "job_seeker"
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "message": "User registered successfully",
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
$errors = [];

if (empty($input['full_name'])) {
    $errors['full_name'] = 'Full name is required.';
}

if (empty($input['username'])) {
    $errors['username'] = 'Username is required.';
} elseif (!isValidUsername($input['username'])) {
    $errors['username'] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
}

if (empty($input['email'])) {
    $errors['email'] = 'Email is required.';
} elseif (!isValidEmail($input['email'])) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (empty($input['password'])) {
    $errors['password'] = 'Password is required.';
} elseif (!isValidPassword($input['password'])) {
    $errors['password'] = 'Password must be at least 6 characters.';
}

// Role is optional, defaults to job_seeker
$role = $input['role'] ?? 'job_seeker';
if (!isValidRole($role)) {
    $errors['role'] = 'Invalid role. Must be job_seeker, employer, or admin.';
}

if (!empty($errors)) {
    sendError('Validation failed.', 422, $errors);
}

try {
    $pdo = getDB();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([strtolower(trim($input['email']))]);
    if ($stmt->fetch()) {
        sendError('An account with this email already exists.', 409);
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$input['username']]);
    if ($stmt->fetch()) {
        sendError('This username is already taken.', 409);
    }

    // Hash the password
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

    // Start transaction
    $pdo->beginTransaction();

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $input['username'],
        strtolower(trim($input['email'])),
        $passwordHash,
        $role,
    ]);

    $userId = $pdo->lastInsertId();

    // Create profile
    $stmt = $pdo->prepare("
        INSERT INTO profiles (user_id, full_name, username, email, phone)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $input['full_name'],
        $input['username'],
        strtolower(trim($input['email'])),
        $input['phone'] ?? null,
    ]);

    // If employer, create a company record
    if ($role === 'employer') {
        $companyName = $input['company_name'] ?? $input['full_name'];
        $companySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $companyName)) . '-' . $userId;

        $stmt = $pdo->prepare("
            INSERT INTO companies (user_id, name, slug, description, email, phone)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $companyName,
            $companySlug,
            $input['company_description'] ?? null,
            strtolower(trim($input['email'])),
            $input['phone'] ?? null,
        ]);
    }

    $pdo->commit();

    // Generate token
    $token = generateJWT($userId, $role);
    storeToken($userId, $token);

    // Return user data (without password)
    $userData = [
        'id'         => $userId,
        'username'   => $input['username'],
        'email'      => strtolower(trim($input['email'])),
        'full_name'  => $input['full_name'],
        'phone'      => $input['phone'] ?? null,
        'role'       => $role,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    sendSuccess([
        'user'  => $userData,
        'token' => $token,
    ], 'User registered successfully', 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError("Register failed: " . $e->getMessage());
    sendError('An error occurred during registration. Please try again.', 500);
}
