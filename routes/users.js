/**
 * Profile / users routes. Uses Prisma ORM.
 */

const express = require('express');
const {
  sendSuccess,
  sendError,
  getInput,
  parseJsonField,
} = require('../config/functions');
const { requireAuth } = require('../config/auth');
const { prisma, transaction } = require('../config/database');

const router = express.Router();

const PROFILE_FIELDS = [
  'full_name',
  'phone',
  'location',
  'bio',
  'professional_title',
  'years_of_experience',
  'employment_type',
  'work_preference',
  'expected_salary',
  'career_objective',
  'profile_photo',
  'cv_url',
];

function formatProfile(user, profile) {
  return {
    id: Number(user.id),
    username: user.username,
    email: user.email,
    role: user.role,
    full_name: profile?.full_name || null,
    phone: profile?.phone || null,
    location: profile?.location || null,
    bio: profile?.bio || null,
    skills: parseJsonField(profile?.skills) || [],
    education: parseJsonField(profile?.education) || [],
    experience: parseJsonField(profile?.experience) || [],
    professional_title: profile?.professional_title || null,
    years_of_experience: profile?.years_of_experience || null,
    employment_type: profile?.employment_type || null,
    work_preference: profile?.work_preference || null,
    expected_salary: profile?.expected_salary || null,
    career_objective: profile?.career_objective || null,
    profile_photo: profile?.profile_photo || null,
    cv_url: profile?.cv_url || null,
    profile_completion: Number(profile?.profile_completion || 0),
    created_at: user.created_at,
    updated_at: user.updated_at,
  };
}

async function fetchProfile(userId) {
  return prisma.users.findUnique({
    where: { id: Number(userId) },
    include: { profile: true },
  });
}

/**
 * GET /api/users/profile
 */
router.get(['/profile', '/profile.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  try {
    const found = await fetchProfile(user.user_id);
    if (!found) return sendError(res, 'Profile not found.', 404);
    return sendSuccess(
      res,
      { profile: formatProfile(found, found.profile) },
      'Profile retrieved successfully'
    );
  } catch (err) {
    console.error('Get profile failed:', err.message);
    return sendError(res, 'An error occurred while retrieving your profile.', 500);
  }
});

/**
 * PUT/POST /api/users/profile
 */
router.put(['/profile', '/profile.php'], async (req, res) => updateProfileHandler(req, res));
router.post(['/profile', '/profile.php'], async (req, res) => updateProfileHandler(req, res));

async function updateProfileHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  const input = getInput(req);

  try {
    const userData = {};
    if (Object.prototype.hasOwnProperty.call(input, 'username')) {
      userData.username = input.username;
    }
    if (Object.prototype.hasOwnProperty.call(input, 'email')) {
      userData.email = String(input.email).toLowerCase().trim();
    }

    const profileData = {};
    for (const field of PROFILE_FIELDS) {
      if (Object.prototype.hasOwnProperty.call(input, field)) {
        profileData[field] = input[field];
      }
    }
    const jsonFields = ['skills', 'education', 'experience'];
    for (const field of jsonFields) {
      if (Object.prototype.hasOwnProperty.call(input, field)) {
        const v = input[field];
        profileData[field] = Array.isArray(v) ? JSON.stringify(v) : v;
      }
    }
    if (Object.prototype.hasOwnProperty.call(input, 'profile_completion')) {
      profileData.profile_completion = parseInt(input.profile_completion, 10) || 0;
    }

    await transaction(async (tx) => {
      if (Object.keys(userData).length > 0) {
        await tx.users.update({
          where: { id: Number(user.user_id) },
          data: userData,
        });
      }
      if (Object.keys(profileData).length > 0) {
        // upsert so missing profile rows are created instead of failing
        await tx.profiles.upsert({
          where: { user_id: Number(user.user_id) },
          update: profileData,
          create: {
            user_id: Number(user.user_id),
            full_name: profileData.full_name || input.full_name || '',
            ...profileData,
          },
        });
      }
    });

    const found = await fetchProfile(user.user_id);
    return sendSuccess(
      res,
      { profile: formatProfile(found, found.profile) },
      'Profile updated successfully'
    );
  } catch (err) {
    console.error('Update profile failed:', err.message);
    return sendError(res, 'An error occurred while updating your profile.', 500);
  }
}

module.exports = router;
