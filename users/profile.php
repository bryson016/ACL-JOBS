<?php
/**
 * Get, update, and manage user profile
 *
 * Endpoint: GET|PUT|POST /api/users/profile.php
 *
 * GET: Retrieve the authenticated user's profile
 * PUT: Update the authenticated user's profile
 * POST: Update profile (alias for PUT, for form compatibility)
 *
 * Headers: Authorization: Bearer <token>
 *
 * GET Response (200):
 * {
 *   "success": true,
 *   "data": { "profile": {...} }
 * }
 *
 * PUT Response (200):
 * {
 *   "success": true,
 *   "message": "Profile updated successfully",
 *   "data": { "profile": {...} }
 * }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

// Require authentication
$user = requireAuth();
$userId = $user['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get profile
    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role, u.created_at, u.updated_at,
                   p.full_name, p.phone, p.location, p.bio, p.skills, p.education,
                   p.experience, p.professional_title, p.years_of_experience,
                   p.employment_type, p.work_preference, p.expected_salary,
                   p.career_objective, p.profile_photo, p.cv_url, p.profile_completion
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            sendError('Profile not found.', 404);
        }

        // Parse JSON fields
        $skills = $profile['skills'] ? json_decode($profile['skills'], true) : [];
        $education = $profile['education'] ? json_decode($profile['education'], true) : [];
        $experience = $profile['experience'] ? json_decode($profile['experience'], true) : [];

        $profileData = [
            'id'                   => (int) $profile['id'],
            'username'             => $profile['username'],
            'email'                => $profile['email'],
            'role'                 => $profile['role'],
            'full_name'            => $profile['full_name'] ?? null,
            'phone'                => $profile['phone'] ?? null,
            'location'             => $profile['location'] ?? null,
            'bio'                  => $profile['bio'] ?? null,
            'skills'               => $skills,
            'education'            => $education,
            'experience'           => $experience,
            'professional_title'   => $profile['professional_title'] ?? null,
            'years_of_experience'  => $profile['years_of_experience'] ?? null,
            'employment_type'      => $profile['employment_type'] ?? null,
            'work_preference'      => $profile['work_preference'] ?? null,
            'expected_salary'      => $profile['expected_salary'] ?? null,
            'career_objective'     => $profile['career_objective'] ?? null,
            'profile_photo'        => $profile['profile_photo'] ?? null,
            'cv_url'               => $profile['cv_url'] ?? null,
            'profile_completion'   => (int) ($profile['profile_completion'] ?? 0),
            'created_at'           => $profile['created_at'],
            'updated_at'           => $profile['updated_at'],
        ];

        sendSuccess(['profile' => $profileData], 'Profile retrieved successfully');

    } catch (Exception $e) {
        logError("Get profile failed: " . $e->getMessage());
        sendError('An error occurred while retrieving your profile.', 500);
    }
}

if ($method === 'PUT' || $method === 'POST') {
    // Update profile
    $input = getInput();

    try {
        $pdo = getDB();
        $pdo->beginTransaction();

        // Update user table fields
        $userUpdates = [];
        $userParams = [];

        if (isset($input['username'])) {
            $userUpdates[] = 'username = ?';
            $userParams[] = $input['username'];
        }

        if (isset($input['email'])) {
            $userUpdates[] = 'email = ?';
            $userParams[] = strtolower(trim($input['email']));
        }

        if (!empty($userUpdates)) {
            $userParams[] = $userId;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $userUpdates) . " WHERE id = ?");
            $stmt->execute($userParams);
        }

        // Update profile table fields
        $profileUpdates = [];
        $profileParams = [];

        $profileFields = [
            'full_name', 'phone', 'location', 'bio', 'professional_title',
            'years_of_experience', 'employment_type', 'work_preference',
            'expected_salary', 'career_objective', 'profile_photo', 'cv_url',
        ];

        foreach ($profileFields as $field) {
            if (array_key_exists($field, $input)) {
                $profileUpdates[] = "$field = ?";
                $profileParams[] = $input[$field];
            }
        }

        // Handle JSON fields
        if (isset($input['skills'])) {
            $profileUpdates[] = 'skills = ?';
            $profileParams[] = is_array($input['skills']) ? json_encode($input['skills']) : $input['skills'];
        }

        if (isset($input['education'])) {
            $profileUpdates[] = 'education = ?';
            $profileParams[] = is_array($input['education']) ? json_encode($input['education']) : $input['education'];
        }

        if (isset($input['experience'])) {
            $profileUpdates[] = 'experience = ?';
            $profileParams[] = is_array($input['experience']) ? json_encode($input['experience']) : $input['experience'];
        }

        // Calculate profile completion
        if (isset($input['profile_completion'])) {
            $profileUpdates[] = 'profile_completion = ?';
            $profileParams[] = (int) $input['profile_completion'];
        }

        if (!empty($profileUpdates)) {
            $profileParams[] = $userId;
            $stmt = $pdo->prepare("UPDATE profiles SET " . implode(', ', $profileUpdates) . " WHERE user_id = ?");
            $stmt->execute($profileParams);
        }

        $pdo->commit();

        // Return updated profile
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role, u.created_at, u.updated_at,
                   p.full_name, p.phone, p.location, p.bio, p.skills, p.education,
                   p.experience, p.professional_title, p.years_of_experience,
                   p.employment_type, p.work_preference, p.expected_salary,
                   p.career_objective, p.profile_photo, p.cv_url, p.profile_completion
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        $skills = $profile['skills'] ? json_decode($profile['skills'], true) : [];
        $education = $profile['education'] ? json_decode($profile['education'], true) : [];
        $experience = $profile['experience'] ? json_decode($profile['experience'], true) : [];

        $profileData = [
            'id'                   => (int) $profile['id'],
            'username'             => $profile['username'],
            'email'                => $profile['email'],
            'role'                 => $profile['role'],
            'full_name'            => $profile['full_name'] ?? null,
            'phone'                => $profile['phone'] ?? null,
            'location'             => $profile['location'] ?? null,
            'bio'                  => $profile['bio'] ?? null,
            'skills'               => $skills,
            'education'            => $education,
            'experience'           => $experience,
            'professional_title'   => $profile['professional_title'] ?? null,
            'years_of_experience'  => $profile['years_of_experience'] ?? null,
            'employment_type'      => $profile['employment_type'] ?? null,
            'work_preference'      => $profile['work_preference'] ?? null,
            'expected_salary'      => $profile['expected_salary'] ?? null,
            'career_objective'     => $profile['career_objective'] ?? null,
            'profile_photo'        => $profile['profile_photo'] ?? null,
            'cv_url'               => $profile['cv_url'] ?? null,
            'profile_completion'   => (int) ($profile['profile_completion'] ?? 0),
            'created_at'           => $profile['created_at'],
            'updated_at'           => $profile['updated_at'],
        ];

        sendSuccess(['profile' => $profileData], 'Profile updated successfully');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Update profile failed: " . $e->getMessage());
        sendError('An error occurred while updating your profile.', 500);
    }
}

// Method not allowed
sendError('Method not allowed. Use GET, PUT, or POST.', 405);
