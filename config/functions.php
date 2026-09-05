<?php
/**
 * Common helper functions for ASL Jobs API
 *
 * Provides consistent JSON responses, input validation,
 * and utility functions used across all API endpoints.
 */

require_once __DIR__ . '/database.php';

/**
 * Send a JSON response with the given data and HTTP status code.
 *
 * @param mixed $data
 * @param int $statusCode
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit(0);
}

/**
 * Send a success JSON response.
 *
 * @param mixed $data
 * @param string $message
 * @param int $statusCode
 */
function sendSuccess($data = null, $message = '', $statusCode = 200) {
    $response = [
        'success' => true,
    ];

    if ($message !== '') {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    sendJson($response, $statusCode);
}

/**
 * Send an error JSON response.
 *
 * @param string $message
 * @param int $statusCode
 * @param mixed $errors
 */
function sendError($message, $statusCode = 400, $errors = null) {
    $response = [
        'success' => false,
        'message' => $message,
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    sendJson($response, $statusCode);
}

/**
 * Get the raw JSON input from the request body.
 *
 * @return array
 */
function getJsonInput() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        return [];
    }

    $decoded = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * Get input from either JSON body or POST data.
 *
 * @return array
 */
function getInput() {
    $jsonInput = getJsonInput();
    if (!empty($jsonInput)) {
        return $jsonInput;
    }

    return $_POST ?? [];
}

/**
 * Sanitize a string value for output.
 *
 * @param string $value
 * @return string
 */
function sanitize($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate an email address.
 *
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a phone number (basic check).
 *
 * @param string $phone
 * @return bool
 */
function isValidPhone($phone) {
    if (empty($phone)) {
        return true; // Phone is optional
    }
    // Allow +, digits, spaces, dashes, parentheses
    return preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $phone);
}

/**
 * Validate a password (minimum 6 characters).
 *
 * @param string $password
 * @return bool
 */
function isValidPassword($password) {
    return strlen($password) >= 6;
}

/**
 * Validate a username (alphanumeric and underscores, 3-50 chars).
 *
 * @param string $username
 * @return bool
 */
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

/**
 * Validate a role.
 *
 * @param string $role
 * @return bool
 */
function isValidRole($role) {
    return in_array($role, ['job_seeker', 'employer', 'admin']);
}

/**
 * Generate a random token.
 *
 * @param int $length
 * @return string
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Format a date to ISO 8601 string.
 *
 * @param string|null $date
 * @return string|null
 */
function formatDate($date) {
    if ($date === null) {
        return null;
    }
    return date('Y-m-d H:i:s', strtotime($date));
}

/**
 * Get the client IP address.
 *
 * @return string
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Log an error message.
 *
 * @param string $message
 */
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/../logs/error.log');
}
