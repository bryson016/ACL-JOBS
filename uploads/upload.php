<?php
/**
 * Handle file uploads (profile photos, CVs, etc.)
 *
 * Endpoint: POST /api/uploads/upload.php
 *
 * Headers: Authorization: Bearer <token>
 *
 * Request: multipart/form-data
 *   - file: The file to upload
 *   - type: "profile_photo" or "cv"
 *
 * Response (200):
 * {
 *   "success": true,
 *   "message": "File uploaded successfully",
 *   "data": { "url": "/api/uploads/files/xxx.jpg" }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Require authentication
$user = requireAuth();

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    sendError('No file was uploaded.', 400);
}

// Check for upload errors
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendError('File upload failed.', 400);
}

$fileType = $_GET['type'] ?? $_POST['type'] ?? 'profile_photo';
$allowedTypes = $fileType === 'cv' ? ALLOWED_DOC_TYPES : ALLOWED_IMAGE_TYPES;

// Validate file type
if (!in_array($_FILES['file']['type'], $allowedTypes)) {
    sendError('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes), 422);
}

// Validate file size
if ($_FILES['file']['size'] > MAX_FILE_SIZE) {
    sendError('File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.', 422);
}

try {
    // Create upload directory if it doesn't exist
    $uploadDir = UPLOAD_DIR . $fileType . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = $user['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $filename;

    // Move the uploaded file
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
        sendError('Failed to save the uploaded file.', 500);
    }

    // Build the URL
    $fileUrl = UPLOAD_BASE_URL . $fileType . '/' . $filename;

    // Update user profile with the file URL
    if ($fileType === 'profile_photo') {
        $stmt = getDB()->prepare("UPDATE profiles SET profile_photo = ? WHERE user_id = ?");
        $stmt->execute([$fileUrl, $user['user_id']]);
    } elseif ($fileType === 'cv') {
        $stmt = getDB()->prepare("UPDATE profiles SET cv_url = ? WHERE user_id = ?");
        $stmt->execute([$fileUrl, $user['user_id']]);
    }

    sendSuccess([
        'url' => $fileUrl,
        'filename' => $filename,
        'size' => $_FILES['file']['size'],
        'type' => $_FILES['file']['type'],
    ], 'File uploaded successfully');

} catch (Exception $e) {
    logError("File upload failed: " . $e->getMessage());
    sendError('An error occurred while uploading the file.', 500);
}
