<?php
/**
 * CORS configuration for ASL Jobs API
 *
 * Handles Cross-Origin Resource Sharing headers for mobile app and web requests.
 */

// Allowed origins (add your production domain here)
$allowedOrigins = [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8080',
    'exp://localhost:19000',
    'exp://localhost:19001',
    'exp://127.0.0.1:19000',
    'exp://127.0.0.1:19001',
    'https://asljobs.com',
    'https://api.asljobs.com',
];

// Get the origin from the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if the origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    // For development, allow all origins
    // In production, you should restrict this
    header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
}

// Allow credentials (for cookies, authorization headers)
header("Access-Control-Allow-Credentials: true");

// Allowed HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

// Allowed headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token, Accept, Origin");

// Cache preflight requests
header("Access-Control-Max-Age: 3600");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}
