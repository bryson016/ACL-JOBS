/**
 * Authentication routes: login, register, logout, me.
 *
 * The Express routes are mounted under `/api/auth/*` and the path names
 * include the original `.php` suffix so the existing frontend code
 * (which calls e.g. `auth/login.php`) keeps working without changes.
 *
 * Uses Prisma ORM for all database access.
 */

const express = require('express');
const bcrypt = require('bcrypt');
const {
  sendSuccess,
  sendError,
  getInput,
  isValidEmail,
  isValidPassword,
  isValidUsername,
  isValidRole,
} = require('../config/functions');
const {
  generateToken,
  storeToken,
  revokeToken,
  requireAuth,
  getUserWithProfile,
} = require('../config/auth');
const { prisma, transaction } = require('../config/database');

const router = express.Router();

/**
 * POST /api/auth/login
 * (frontend calls: auth/login.php)
 */
router.post(['/', '/login', '/login.php'], async (req, res) => {
  const input = getInput(req);

  if (!input.email) {
    return sendError(res, 'Email is required.', 422, { email: 'Email is required.' });
  }
  if (!input.password) {
    return sendError(res, 'Password is required.', 422, { password: 'Password is required.' });
  }

  try {
    const email = String(input.email).toLowerCase().trim();
    const user = await prisma.users.findFirst({
      where: { email },
      include: { profile: true },
    });

    if (!user) {
      return sendError(res, 'Invalid email or password.', 401);
    }
    if (!user.is_active) {
      return sendError(
        res,
        'Your account has been deactivated. Please contact support.',
        403
      );
    }

    // bcrypt.compare handles passwords hashed with PHP password_hash() because
    // both produce a standard $2y$ / $2b$ bcrypt hash.
    const ok = await bcrypt.compare(String(input.password), user.password);
    if (!ok) {
      return sendError(res, 'Invalid email or password.', 401);
    }

    const token = generateToken(user.id, user.role);
    await storeToken(user.id, token);

    const profile = user.profile;
    const userData = {
      id: Number(user.id),
      username: user.username,
      email: user.email,
      full_name: profile?.full_name || null,
      phone: profile?.phone || null,
      location: profile?.location || null,
      bio: profile?.bio || null,
      profile_photo: profile?.profile_photo || null,
      cv_url: profile?.cv_url || null,
      role: user.role,
      created_at: user.created_at,
    };

    return sendSuccess(res, { user: userData, token }, 'Login successful');
  } catch (err) {
    console.error('Login failed:', err.message);
    return sendError(
      res,
      'An error occurred during login. Please try again.',
      500
    );
  }
});

/**
 * POST /api/auth/register
 */
router.post(['/register', '/register.php'], async (req, res) => {
  const input = getInput(req);
  const errors = {};

  if (!input.full_name) errors.full_name = 'Full name is required.';
  if (!input.username) {
    errors.username = 'Username is required.';
  } else if (!isValidUsername(input.username)) {
    errors.username =
      'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
  }
  if (!input.email) {
    errors.email = 'Email is required.';
  } else if (!isValidEmail(input.email)) {
    errors.email = 'Please enter a valid email address.';
  }
  if (!input.password) {
    errors.password = 'Password is required.';
  } else if (!isValidPassword(input.password)) {
    errors.password = 'Password must be at least 6 characters.';
  }

  const role = input.role || 'job_seeker';
  if (!isValidRole(role)) {
    errors.role = 'Invalid role. Must be job_seeker, employer, or admin.';
  }

  if (Object.keys(errors).length > 0) {
    return sendError(res, 'Validation failed.', 422, errors);
  }

  const email = String(input.email).toLowerCase().trim();

  try {
    // Check duplicates
    const dupeEmail = await prisma.users.findFirst({
      where: { email },
      select: { id: true },
    });
    if (dupeEmail) {
      return sendError(res, 'An account with this email already exists.', 409);
    }

    const dupeUsername = await prisma.users.findFirst({
      where: { username: String(input.username) },
      select: { id: true },
    });
    if (dupeUsername) {
      return sendError(res, 'This username is already taken.', 409);
    }

    const passwordHash = await bcrypt.hash(String(input.password), 10);

    const userId = await transaction(async (tx) => {
      const created = await tx.users.create({
        data: {
          username: input.username,
          email,
          password: passwordHash,
          role,
          is_active: 1,
        },
      });

      await tx.profiles.create({
        data: {
          user_id: created.id,
          full_name: input.full_name,
          username: input.username,
          email,
          phone: input.phone || null,
        },
      });

      if (role === 'employer') {
        const companyName = input.company_name || input.full_name;
        const slug =
          String(companyName)
            .toLowerCase()
            .replace(/[^a-zA-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') +
          '-' +
          created.id;

        await tx.companies.create({
          data: {
            user_id: created.id,
            name: companyName,
            slug,
            description: input.company_description || null,
            email,
            phone: input.phone || null,
            is_active: 1,
          },
        });
      }

      return created.id;
    });

    const token = generateToken(userId, role);
    await storeToken(userId, token);

    const userData = {
      id: userId,
      username: input.username,
      email,
      full_name: input.full_name,
      phone: input.phone || null,
      role,
      created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    };

    return sendSuccess(
      res,
      { user: userData, token },
      'User registered successfully',
      201
    );
  } catch (err) {
    console.error('Register failed:', err.message);
    return sendError(
      res,
      'An error occurred during registration. Please try again.',
      500
    );
  }
});

/**
 * POST /api/auth/logout
 */
router.post(['/logout', '/logout.php'], async (req, res) => {
  const { extractToken } = require('../config/auth');
  const token = extractToken(req);
  if (token) {
    await revokeToken(token);
  }
  return sendSuccess(res, null, 'Logout successful');
});

/**
 * GET /api/auth/me
 */
router.get(['/me', '/me.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  const full = await getUserWithProfile(user.user_id);
  if (!full) {
    return sendError(res, 'User not found.', 404);
  }

  const profile = full.profile;
  const data = {
    id: Number(full.id),
    username: full.username,
    email: full.email,
    full_name: profile?.full_name || null,
    phone: profile?.phone || null,
    location: profile?.location || null,
    bio: profile?.bio || null,
    profile_photo: profile?.profile_photo || null,
    cv_url: profile?.cv_url || null,
    role: full.role,
    is_active: Boolean(full.is_active),
    created_at: full.created_at,
    updated_at: full.updated_at,
  };

  return sendSuccess(res, { user: data }, 'User information retrieved successfully');
});

module.exports = router;
