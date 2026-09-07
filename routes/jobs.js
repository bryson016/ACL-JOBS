/**
 * Jobs routes: list, get, create, update, delete.
 *
 * Express paths mirror the PHP `.php` filenames so the frontend keeps
 * calling the same URLs.
 *
 * Uses Prisma ORM for all database access.
 */

const express = require('express');
const {
  sendSuccess,
  sendError,
  getInput,
  parseJsonField,
} = require('../config/functions');
const {
  requireAuth,
  requireRole,
  authenticate,
} = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

const VALID_STATUSES = ['active', 'closed', 'draft', 'archived'];

function formatJob(row, savedIds = new Set()) {
  return {
    id: Number(row.id),
    company_id: Number(row.company_id),
    company_name: row.company_name || null,
    company_logo: row.company_logo || null,
    company_industry: row.company_industry || null,
    title: row.title,
    description: row.description,
    location: row.location,
    category: row.category || null,
    employment_type: row.employment_type,
    salary: row.salary || null,
    requirements: parseJsonField(row.requirements) || [],
    status: row.status,
    is_featured: Boolean(row.is_featured),
    views_count: Number(row.views_count || 0),
    is_saved: savedIds.has(Number(row.id)),
    created_at: row.created_at,
    updated_at: row.updated_at,
  };
}

/**
 * GET /api/jobs/get_jobs
 * Query params: page, limit, search, category, employment_type, location, status, company_id, featured
 */
router.get(['/get_jobs', '/get_jobs.php'], async (req, res) => {
  const user = await authenticate(req);
  const page = Math.max(1, parseInt(req.query.page || '1', 10));
  const limit = Math.min(100, Math.max(1, parseInt(req.query.limit || '20', 10)));
  const offset = (page - 1) * limit;

  const search = String(req.query.search || '').trim();
  const category = String(req.query.category || '').trim();
  const employmentType = String(req.query.employment_type || '').trim();
  const location = String(req.query.location || '').trim();
  const status = String(req.query.status || 'active').trim();
  const companyId = parseInt(req.query.company_id || '0', 10);
  const featuredRaw = req.query.featured;
  const featured =
    featuredRaw === undefined ? null : parseInt(featuredRaw, 10);

  try {
    // Build dynamic WHERE clause
    const where = { AND: [] };

    // Status filter: default to 'active' unless employer/admin pass 'all'
    if (user && ['employer', 'admin'].includes(user.role)) {
      if (status !== 'all') where.AND.push({ status });
    } else {
      where.AND.push({ status });
    }

    if (search) {
      where.AND.push({
        OR: [
          { title: { contains: search } },
          { description: { contains: search } },
          { requirements: { contains: search } },
        ],
      });
    }
    if (category) where.AND.push({ category });
    if (employmentType) where.AND.push({ employment_type: employmentType });
    if (location) where.AND.push({ location: { contains: location } });
    if (companyId > 0) where.AND.push({ company_id: companyId });
    if (featured !== null && featured !== undefined) {
      where.AND.push({ is_featured: featured });
    }

    const [total, rows] = await Promise.all([
      prisma.jobs.count({ where }),
      prisma.jobs.findMany({
        where,
        include: {
          company: { select: { name: true, logo: true, industry: true } },
        },
        orderBy: [{ is_featured: 'desc' }, { created_at: 'desc' }],
        take: limit,
        skip: offset,
      }),
    ]);

    let savedIds = new Set();
    if (user) {
      const savedRows = await prisma.saved_jobs.findMany({
        where: { user_id: Number(user.user_id) },
        select: { job_id: true },
      });
      savedIds = new Set(savedRows.map((r) => Number(r.job_id)));
    }

    const formatted = rows.map((job) =>
      formatJob(
        {
          ...job,
          company_name: job.company?.name || null,
          company_logo: job.company?.logo || null,
          company_industry: job.company?.industry || null,
        },
        savedIds
      )
    );

    const totalPages = Math.ceil(total / limit);
    return sendSuccess(
      res,
      {
        jobs: formatted,
        pagination: { page, limit, total, total_pages: totalPages },
      },
      'Jobs retrieved successfully'
    );
  } catch (err) {
    console.error('Get jobs failed:', err.message);
    return sendError(res, 'An error occurred while retrieving jobs.', 500);
  }
});

/**
 * GET /api/jobs/get_job?id=...
 */
router.get(['/get_job', '/get_job.php'], async (req, res) => {
  const jobId = parseInt(req.query.id || '0', 10);
  if (jobId <= 0) {
    return sendError(res, 'Job ID is required.', 400);
  }
  const user = await authenticate(req);

  try {
    const job = await prisma.jobs.findUnique({
      where: { id: jobId },
      include: {
        company: {
          select: {
            name: true,
            logo: true,
            industry: true,
            description: true,
            website: true,
            email: true,
            phone: true,
            address: true,
          },
        },
      },
    });
    if (!job) return sendError(res, 'Job not found.', 404);

    // Increment view count (best-effort)
    prisma.jobs
      .update({
        where: { id: jobId },
        data: { views_count: { increment: 1 } },
      })
      .catch(() => {});

    let isSaved = false;
    let hasApplied = false;
    let applicationStatus = null;
    if (user) {
      const savedRow = await prisma.saved_jobs.findFirst({
        where: { user_id: Number(user.user_id), job_id: jobId },
        select: { id: true },
      });
      isSaved = Boolean(savedRow);

      const appRow = await prisma.applications.findFirst({
        where: { user_id: Number(user.user_id), job_id: jobId },
        select: { id: true, status: true },
      });
      if (appRow) {
        hasApplied = true;
        applicationStatus = appRow.status;
      }
    }

    const jobData = {
      id: Number(job.id),
      company_id: Number(job.company_id),
      company_name: job.company?.name || null,
      company_logo: job.company?.logo || null,
      company_industry: job.company?.industry || null,
      company_description: job.company?.description || null,
      company_website: job.company?.website || null,
      company_email: job.company?.email || null,
      company_phone: job.company?.phone || null,
      company_address: job.company?.address || null,
      title: job.title,
      description: job.description,
      location: job.location,
      category: job.category || null,
      employment_type: job.employment_type,
      salary: job.salary || null,
      requirements: parseJsonField(job.requirements) || [],
      status: job.status,
      is_featured: Boolean(job.is_featured),
      views_count: Number(job.views_count || 0),
      is_saved: isSaved,
      has_applied: hasApplied,
      application_status: applicationStatus,
      created_at: job.created_at,
      updated_at: job.updated_at,
    };

    return sendSuccess(res, { job: jobData }, 'Job retrieved successfully');
  } catch (err) {
    console.error('Get job failed:', err.message);
    return sendError(res, 'An error occurred while retrieving the job.', 500);
  }
});

/**
 * POST /api/jobs/create_job (employer/admin only)
 */
router.post(['/create_job', '/create_job.php'], async (req, res) => {
  const user = await requireRole(req, res, ['employer', 'admin']);
  if (!user) return;

  const input = getInput(req);
  const errors = {};
  if (!input.title) errors.title = 'Job title is required.';
  if (!input.description) errors.description = 'Job description is required.';
  if (!input.location) errors.location = 'Location is required.';
  if (!input.employment_type) errors.employment_type = 'Employment type is required.';
  if (Object.keys(errors).length > 0) {
    return sendError(res, 'Validation failed.', 422, errors);
  }

  try {
    let companyId = null;
    if (user.role === 'employer') {
      const company = await prisma.companies.findFirst({
        where: { user_id: Number(user.user_id) },
        select: { id: true },
      });
      if (!company) {
        return sendError(
          res,
          'You must create a company profile before posting jobs.',
          400
        );
      }
      companyId = company.id;
    } else {
      companyId = parseInt(input.company_id || '0', 10);
      if (companyId <= 0) {
        return sendError(res, 'Company ID is required for admin job creation.', 422);
      }
    }

    const company = await prisma.companies.findUnique({
      where: { id: companyId },
      select: { id: true },
    });
    if (!company) return sendError(res, 'Company not found.', 404);

    const statusValue = VALID_STATUSES.includes(input.status)
      ? input.status
      : 'active';

    let requirements = input.requirements || [];
    if (Array.isArray(requirements)) requirements = JSON.stringify(requirements);

    const created = await prisma.jobs.create({
      data: {
        company_id: companyId,
        title: input.title,
        description: input.description,
        location: input.location,
        category: input.category || null,
        employment_type: input.employment_type,
        salary: input.salary || null,
        requirements,
        status: statusValue,
        is_featured: input.is_featured ? parseInt(input.is_featured, 10) : 0,
      },
    });

    const job = await prisma.jobs.findUnique({
      where: { id: created.id },
      include: { company: { select: { name: true, logo: true } } },
    });

    const jobData = formatJob({
      ...job,
      company_name: job.company?.name || null,
      company_logo: job.company?.logo || null,
      company_industry: null,
    });
    return sendSuccess(res, { job: jobData }, 'Job created successfully', 201);
  } catch (err) {
    console.error('Create job failed:', err.message);
    return sendError(res, 'An error occurred while creating the job.', 500);
  }
});

/**
 * PUT/POST /api/jobs/update_job
 */
router.put(['/update_job', '/update_job.php'], async (req, res) => updateJobHandler(req, res));
router.post(['/update_job', '/update_job.php'], async (req, res) => updateJobHandler(req, res));

async function updateJobHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  const input = getInput(req);
  const jobId = parseInt(input.id || '0', 10);
  if (jobId <= 0) return sendError(res, 'Job ID is required.', 400);

  try {
    const job = await prisma.jobs.findUnique({
      where: { id: jobId },
      select: { id: true, company_id: true },
    });
    if (!job) return sendError(res, 'Job not found.', 404);

    if (user.role === 'employer') {
      const company = await prisma.companies.findUnique({
        where: { id: job.company_id },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(res, 'You do not have permission to modify this job.', 403);
      }
    } else if (user.role !== 'admin') {
      return sendError(res, 'You do not have permission to modify jobs.', 403);
    }

    const updatable = [
      'title',
      'description',
      'location',
      'category',
      'employment_type',
      'salary',
      'status',
      'is_featured',
    ];
    const data = {};
    for (const field of updatable) {
      if (Object.prototype.hasOwnProperty.call(input, field)) {
        data[field] = input[field];
      }
    }
    if (Object.prototype.hasOwnProperty.call(input, 'requirements')) {
      let r = input.requirements;
      if (Array.isArray(r)) r = JSON.stringify(r);
      data.requirements = r;
    }
    if (Object.keys(data).length === 0) {
      return sendError(res, 'No fields to update.', 400);
    }

    await prisma.jobs.update({ where: { id: jobId }, data });

    const fresh = await prisma.jobs.findUnique({
      where: { id: jobId },
      include: { company: { select: { name: true, logo: true } } },
    });

    return sendSuccess(
      res,
      {
        job: formatJob({
          ...fresh,
          company_name: fresh.company?.name || null,
          company_logo: fresh.company?.logo || null,
          company_industry: null,
        }),
      },
      'Job updated successfully'
    );
  } catch (err) {
    console.error('Update job failed:', err.message);
    return sendError(res, 'An error occurred while updating the job.', 500);
  }
}

/**
 * DELETE /api/jobs/delete_job
 */
router.delete(['/delete_job', '/delete_job.php'], async (req, res) => deleteJobHandler(req, res));
router.post(['/delete_job', '/delete_job.php'], async (req, res) => deleteJobHandler(req, res));

async function deleteJobHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  let jobId = parseInt(req.query.id || '0', 10);
  if (jobId <= 0) {
    const input = getInput(req);
    jobId = parseInt(input.id || '0', 10);
  }
  if (jobId <= 0) return sendError(res, 'Job ID is required.', 400);

  try {
    const job = await prisma.jobs.findUnique({
      where: { id: jobId },
      select: { id: true, company_id: true },
    });
    if (!job) return sendError(res, 'Job not found.', 404);

    if (user.role === 'employer') {
      const company = await prisma.companies.findUnique({
        where: { id: job.company_id },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(res, 'You do not have permission to delete this job.', 403);
      }
    } else if (user.role !== 'admin') {
      return sendError(res, 'You do not have permission to delete jobs.', 403);
    }

    await prisma.jobs.delete({ where: { id: jobId } });
    return sendSuccess(res, null, 'Job deleted successfully');
  } catch (err) {
    console.error('Delete job failed:', err.message);
    return sendError(res, 'An error occurred while deleting the job.', 500);
  }
}

module.exports = router;
