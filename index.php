<?php
/**
 * ASL Jobs API - Entry Point
 *
 * This file serves as the main entry point for the API.
 * It handles CORS, routing, and dispatches requests to the appropriate endpoint.
 */

// Include CORS configuration
require_once __DIR__ . '/config/cors.php';

// Include common functions
require_once __DIR__ . '/config/functions.php';

// Include auth functions
require_once __DIR__ . '/config/auth.php';

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

// Parse the path
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the script directory (the part of the URL that points at
// /acl-jobs-api, not just /index.php). This handles deployments under
// a subpath like /acl-jobs-api/api/... as well as the document root.
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($scriptDir !== '' && $scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}

// Strip query string artifacts and leading/trailing slashes
$path = trim($path, '/');

// If no path, return API info
if (empty($path)) {
    sendSuccess([
        'name'        => 'ASL Jobs API',
        'version'     => '1.0.0',
        'description' => 'REST API for Advenware Career Link',
        'endpoints'   => [
            'auth'         => '/api/auth/',
            'users'        => '/api/users/',
            'jobs'         => '/api/jobs/',
            'applications' => '/api/applications/',
            'saved_jobs'   => '/api/saved_jobs/',
            'interviews'   => '/api/interviews/',
            'notifications'=> '/api/notifications/',
            'dashboard'    => '/api/dashboard/',
            'uploads'      => '/api/uploads/',
        ],
    ], 'ASL Jobs API v1.0.0');
}

// Split path into segments
$segments = explode('/', $path);

// Check if the first segment is 'api'
if ($segments[0] !== 'api') {
    sendError('Invalid API endpoint.', 404);
}

// Remove 'api' from segments
array_shift($segments);

// If no more segments, return API info
if (empty($segments) || (count($segments) === 1 && empty($segments[0]))) {
    sendSuccess([
        'name'        => 'ASL Jobs API',
        'version'     => '1.0.0',
        'description' => 'REST API for Advenware Career Link',
    ], 'ASL Jobs API v1.0.0');
}

// Build the file path
$module = $segments[0];
$endpoint = isset($segments[1]) ? $segments[1] : '';

// Map module to directory
$moduleMap = [
    'auth'         => 'auth',
    'users'        => 'users',
    'jobs'         => 'jobs',
    'applications' => 'applications',
    'saved_jobs'   => 'saved_jobs',
    'interviews'   => 'interviews',
    'notifications'=> 'notifications',
    'dashboard'    => 'dashboard',
    'uploads'      => 'uploads',
];

if (!isset($moduleMap[$module])) {
    sendError('Invalid API module.', 404);
}

// Drop a trailing .php so URLs like /api/jobs/get_jobs.php resolve to
// the same file as /api/jobs/get_jobs. Without this we'd look for
// jobs/get_jobs.php.php and 404.
if (substr($endpoint, -4) === '.php') {
    $endpoint = substr($endpoint, 0, -4);
}

$filePath = __DIR__ . '/' . $moduleMap[$module] . '/' . $endpoint . '.php';

if (!file_exists($filePath)) {
    sendError('Endpoint not found.', 404);
}

// Include the endpoint file
require_once $filePath;
