<?php
/**
 * Authentication and Authorization middleware for ASL Jobs API
 *
 * Provides token-based authentication using JWT-like tokens stored in the database.
 * Includes role-based access control (RBAC) for protected endpoints.
 */

require_once __DIR__ . '/functions.php';

/**
 * Generate a JWT-like token for a user.
 *
 * @param int $userId
 * @param string $role
 * @return string
 */
function generateJWT($userId, $role) {
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]));
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'role'    => $role,
        'iat'     => time(),
        'exp'     => time() + TOKEN_EXPIRY,
    ]));

    $signature = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET);
    return $header . '.' . $payload . '.' . $signature;
}

/**
 * Verify a JWT token and return the payload.
 *
 * @param string $token
 * @return array|null
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    list($header, $payload, $signature) = $parts;

    // Verify signature
    $expectedSignature = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET);
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    // Decode payload
    $payloadData = json_decode(base64_decode($payload), true);
    if (!$payloadData) {
        return null;
    }

    // Check expiration
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return null;
    }

    return $payloadData;
}

/**
 * Store a token in the database for session management.
 *
 * @param int $userId
 * @param string $token
 * @return bool
 */
function storeToken($userId, $token) {
    try {
        $pdo = getDB();
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);

        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (user_id, token, token_hash, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $token, $tokenHash, $expiresAt]);
        return true;
    } catch (Exception $e) {
        logError("storeToken failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate a token from the Authorization header.
 * Checks both JWT validity and database record.
 *
 * @return array|null Returns ['user_id' => int, 'role' => string] or null
 */
function authenticate() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader)) {
        return null;
    }

    // Extract token from "Bearer <token>"
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        $token = $authHeader;
    }

    $token = trim($token);
    if (empty($token)) {
        return null;
    }

    // Verify JWT structure and signature
    $payload = verifyJWT($token);
    if (!$payload) {
        return null;
    }

    // Check token in database (not revoked, not expired)
    try {
        $pdo = getDB();
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare("
            SELECT user_id, expires_at, is_revoked
            FROM auth_tokens
            WHERE token_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $tokenRecord = $stmt->fetch();

        if (!$tokenRecord) {
            return null;
        }

        if ($tokenRecord['is_revoked']) {
            return null;
        }

        if (strtotime($tokenRecord['expires_at']) < time()) {
            return null;
        }

        // Update last used time
        $stmt = $pdo->prepare("UPDATE auth_tokens SET last_used_at = NOW() WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);

        return [
            'user_id' => $payload['user_id'],
            'role'    => $payload['role'],
        ];
    } catch (Exception $e) {
        logError("authenticate failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Require authentication. Returns the authenticated user info or sends 401.
 *
 * @return array
 */
function requireAuth() {
    $user = authenticate();
    if (!$user) {
        sendError('Authentication required. Please log in.', 401);
    }
    return $user;
}

/**
 * Require a specific role. Returns the authenticated user info or sends 403.
 *
 * @param string|array $roles Single role or array of roles
 * @return array
 */
function requireRole($roles) {
    $user = requireAuth();

    if (is_string($roles)) {
        $roles = [$roles];
    }

    if (!in_array($user['role'], $roles)) {
        sendError('You do not have permission to perform this action.', 403);
    }

    return $user;
}

/**
 * Get the full user record from the database.
 *
 * @param int $userId
 * @return array|null
 */
function getUserById($userId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at, u.updated_at,
                   p.full_name, p.phone, p.location, p.bio, p.profile_photo, p.cv_url
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logError("getUserById failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get the full user record including profile data.
 *
 * @param int $userId
 * @return array|null
 */
function getUserWithProfile($userId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at, u.updated_at,
                   p.full_name, p.phone, p.location, p.bio, p.skills, p.education, p.experience,
                   p.professional_title, p.years_of_experience, p.employment_type,
                   p.work_preference, p.expected_salary, p.career_objective,
                   p.profile_photo, p.cv_url, p.profile_completion
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logError("getUserWithProfile failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Revoke a token (logout).
 *
 * @param string $token
 * @return bool
 */
function revokeToken($token) {
    try {
        $pdo = getDB();
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare("UPDATE auth_tokens SET is_revoked = 1 WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        logError("revokeToken failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a user.
 *
 * @param int $userId
 * @param string $type
 * @param string $title
 * @param string $message
 * @param array|null $data
 * @return bool
 */
function createNotification($userId, $type, $title, $message, $data = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, ?, ?, ?, ?)
        ");
        $jsonData = $data ? json_encode($data) : null;
        $stmt->execute([$userId, $type, $title, $message, $jsonData]);
        return true;
    } catch (Exception $e) {
        logError("createNotification failed: " . $e->getMessage());
        return false;
    }
}
