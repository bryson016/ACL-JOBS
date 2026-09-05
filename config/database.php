<?php
/**
 * Database configuration for ASL Jobs API
 *
 * This file contains the database connection settings.
 * In production, override these values using environment variables
 * or a secure configuration management system.
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    if ($env) {
        foreach ($env as $key => $value) {
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'acl_jobs');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// JWT / Token configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'acl_jobs_secret_key_change_in_production_2024');
define('JWT_ALGORITHM', 'HS256');
define('TOKEN_EXPIRY', 60 * 60 * 24 * 30); // 30 days in seconds

// File upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_BASE_URL', '/api/uploads/');

// Allowed file types for uploads
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

/**
 * Get a PDO database connection.
 *
 * @return PDO
 * @throws PDOException
 */
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the actual error but don't expose it to the client
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed");
        }
    }

    return $pdo;
}
