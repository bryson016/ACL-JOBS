/**
 * Notifications routes. Uses Prisma ORM.
 */

const express = require('express');
const { sendSuccess, sendError } = require('../config/functions');
const { requireAuth } = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

router.get(['/list', '/list.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  const page = Math.max(1, parseInt(req.query.page || '1', 10));
  const limit = Math.min(100, Math.max(1, parseInt(req.query.limit || '20', 10)));
  const offset = (page - 1) * limit;
  const unreadOnly = parseInt(req.query.unread_only || '0', 10) === 1;

  try {
    const where = { user_id: Number(user.user_id) };
    if (unreadOnly) where.is_read = 0;

    const [total, unreadCount, rows] = await Promise.all([
      prisma.notifications.count({ where }),
      prisma.notifications.count({
        where: { user_id: Number(user.user_id), is_read: 0 },
      }),
      prisma.notifications.findMany({
        where,
        orderBy: { created_at: 'desc' },
        take: limit,
        skip: offset,
      }),
    ]);

    const formatted = rows.map((n) => ({
      id: Number(n.id),
      type: n.type,
      title: n.title,
      message: n.message,
      data: n.data || null,
      is_read: Boolean(n.is_read),
      created_at: n.created_at,
      updated_at: n.updated_at,
    }));

    const totalPages = Math.ceil(total / limit);
    return sendSuccess(
      res,
      {
        notifications: formatted,
        unread_count: Number(unreadCount),
        pagination: { page, limit, total, total_pages: totalPages },
      },
      'Notifications retrieved successfully'
    );
  } catch (err) {
    console.error('Get notifications failed:', err.message);
    return sendError(res, 'An error occurred while retrieving notifications.', 500);
  }
});

router.post(['/mark_read', '/mark_read.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;
  const input = req.body || {};
  try {
    if (input.id && parseInt(input.id, 10) > 0) {
      const result = await prisma.notifications.updateMany({
        where: { id: parseInt(input.id, 10), user_id: Number(user.user_id) },
        data: { is_read: 1 },
      });
      if (result.count === 0) {
        return sendError(res, 'Notification not found.', 404);
      }
      return sendSuccess(res, { marked_count: 1 }, 'Notification marked as read');
    }
    const result = await prisma.notifications.updateMany({
      where: { user_id: Number(user.user_id), is_read: 0 },
      data: { is_read: 1 },
    });
    return sendSuccess(
      res,
      { marked_count: result.count },
      'All notifications marked as read'
    );
  } catch (err) {
    console.error('Mark notification read failed:', err.message);
    return sendError(res, 'An error occurred while marking notifications as read.', 500);
  }
});

router.post(['/mark_all_read', '/mark_all_read.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;
  try {
    const result = await prisma.notifications.updateMany({
      where: { user_id: Number(user.user_id), is_read: 0 },
      data: { is_read: 1 },
    });
    return sendSuccess(
      res,
      { updated_count: result.count },
      'All notifications marked as read'
    );
  } catch (err) {
    console.error('mark_all_read failed:', err.message);
    return sendError(res, 'An error occurred while marking notifications as read.', 500);
  }
});

module.exports = router;
