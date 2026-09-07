/**
 * Saved jobs routes. Uses Prisma ORM.
 */

const express = require('express');
const { sendSuccess, sendError, getInput, parseJsonField } = require('../config/functions');
const { requireAuth } = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

/**
 * POST /api/saved_jobs/save_job
 */
router.post(['/save_job', '/save_job.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;
  const input = getInput(req);
  const jobId = parseInt(input.job_id || '0', 10);
  if (jobId <= 0) return sendError(res, 'Job ID is required.', 400);

  try {
    const job = await prisma.jobs.findUnique({
      where: { id: jobId },
      select: { id: true },
    });
    if (!job) return sendError(res, 'Job not found.', 404);

    const dupe = await prisma.saved_jobs.findFirst({
      where: { user_id: Number(user.user_id), job_id: jobId },
      select: { id: true },
    });
    if (dupe) return sendError(res, 'Job is already saved.', 409);

    await prisma.saved_jobs.create({
      data: { user_id: Number(user.user_id), job_id: jobId },
    });
    return sendSuccess(res, { saved: true }, 'Job saved successfully', 201);
  } catch (err) {
    console.error('Save job failed:', err.message);
    return sendError(res, 'An error occurred while saving the job.', 500);
  }
});

/**
 * DELETE /api/saved_jobs/unsave_job
 */
router.delete(['/unsave_job', '/unsave_job.php'], async (req, res) =>
  unsaveHandler(req, res)
);
router.post(['/unsave_job', '/unsave_job.php'], async (req, res) =>
  unsaveHandler(req, res)
);

async function unsaveHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  let jobId = parseInt(req.query.job_id || '0', 10);
  if (jobId <= 0) {
    const input = getInput(req);
    jobId = parseInt(input.job_id || '0', 10);
  }
  if (jobId <= 0) return sendError(res, 'Job ID is required.', 400);

  try {
    const existing = await prisma.saved_jobs.findFirst({
      where: { user_id: Number(user.user_id), job_id: jobId },
      select: { id: true },
    });
    if (!existing) {
      return sendError(res, 'Job is not in your saved list.', 404);
    }
    await prisma.saved_jobs.delete({
      where: { id: existing.id },
    });
    return sendSuccess(res, { saved: false }, 'Job removed from saved jobs');
  } catch (err) {
    console.error('Unsave job failed:', err.message);
    return sendError(
      res,
      'An error occurred while removing the job from saved list.',
      500
    );
  }
}

/**
 * GET /api/saved_jobs/my_saved_jobs
 */
router.get(['/my_saved_jobs', '/my_saved_jobs.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;
  const page = Math.max(1, parseInt(req.query.page || '1', 10));
  const limit = Math.min(100, Math.max(1, parseInt(req.query.limit || '20', 10)));
  const offset = (page - 1) * limit;

  try {
    const where = { user_id: Number(user.user_id) };
    const [total, rows] = await Promise.all([
      prisma.saved_jobs.count({ where }),
      prisma.saved_jobs.findMany({
        where,
        orderBy: { saved_at: 'desc' },
        take: limit,
        skip: offset,
        include: {
          job: {
            select: {
              title: true,
              company_id: true,
              location: true,
              employment_type: true,
              salary: true,
              description: true,
              requirements: true,
              is_featured: true,
              created_at: true,
              company: { select: { name: true, logo: true } },
            },
          },
        },
      }),
    ]);

    const formatted = rows.map((r) => ({
      id: Number(r.id),
      job_id: Number(r.job_id),
      job_title: r.job?.title || null,
      company_name: r.job?.company?.name || null,
      company_logo: r.job?.company?.logo || null,
      location: r.job?.location || null,
      employment_type: r.job?.employment_type || null,
      salary: r.job?.salary || null,
      description: r.job?.description || null,
      requirements: parseJsonField(r.job?.requirements) || [],
      is_featured: Boolean(r.job?.is_featured),
      saved_at: r.saved_at,
      job_created_at: r.job?.created_at || null,
    }));

    const totalPages = Math.ceil(total / limit);
    return sendSuccess(
      res,
      {
        saved_jobs: formatted,
        pagination: { page, limit, total, total_pages: totalPages },
      },
      'Saved jobs retrieved successfully'
    );
  } catch (err) {
    console.error('Get saved jobs failed:', err.message);
    return sendError(res, 'An error occurred while retrieving saved jobs.', 500);
  }
});

module.exports = router;
