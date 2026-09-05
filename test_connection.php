<?php
/**
 * Database Connection Test
 *
 * This file tests the database connection and verifies that
 * the acl_jobs database is accessible.
 *
 * Usage:
 *   Via browser:  http://localhost:8080/api/test_connection.php
 *   Via CLI:      php backend/test_connection.php
 *
 * Expected output on success:
 *   {"success": true, "message": "Database connection successful", "database": "acl_jobs"}
 */

// Include CORS configuration
require_once __DIR__ . '/config/cors.php';

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Include common functions
require_once __DIR__ . '/config/functions.php';

try {
    $pdo = getDB();

    // Verify the database name
    $dbName = DB_NAME;

    // Test a simple query
    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as current_db");
    $info = $stmt->fetch();

    sendSuccess([
        'database'       => $dbName,
        'current_db'     => $info['current_db'],
        'mysql_version'  => $info['version'],
        'host'           => DB_HOST,
        'charset'        => DB_CHARSET,
    ], 'Database connection successful');
} catch (PDOException $e) {
    // Log the actual error but don't expose it to the client
    logError("Connection test failed: " . $e->getMessage());
    sendError('Database connection failed. Check server logs for details.', 500);
}
