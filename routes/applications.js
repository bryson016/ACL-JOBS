/**
 * Applications routes: apply, list mine, details, update status.
 * Uses Prisma ORM.
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
  createNotification,
} = require('../config/auth');
const { prisma, transaction } = require('../config/database');

const router = express.Router();

const VALID_STATUSES = [
  'pending',
  'reviewing',
  'shortlisted',
  'interview',
  'accepted',
  'rejected',
  'withdrawn',
];

function formatApplication(row, interview = null) {
  const out = {
    id: Number(row.id),
    user_id: Number(row.user_id),
    job_id: Number(row.job_id),
    job_title: row.job_title || null,
    company_name: row.company_name || null,
    company_logo: row.company_logo || null,
    company_industry: row.company_industry || null,
    company_website: row.company_website || null,
    company_email: row.company_email || null,
    company_phone: row.company_phone || null,
    location: row.location || null,
    employment_type: row.employment_type || null,
    salary: row.salary || null,
    job_description: row.job_description || null,
    job_requirements: parseJsonField(row.job_requirements) || [],
    status: row.status,
    cover_letter: row.cover_letter || null,
    cv_url: row.cv_url || null,
    applied_at: row.applied_at,
    updated_at: row.updated_at,
  };
  if (interview) {
    out.interview = {
      id: Number(interview.id),
      title: interview.title,
      description: interview.description,
      scheduled_at: interview.scheduled_at,
      duration: Number(interview.duration || 0),
      location: interview.location,
      meeting_link: interview.meeting_link,
      status: interview.status,
      notes: interview.notes,
    };
  }
  return out;
}

const APP_SELECT = {
  id: true,
  user_id: true,
  job_id: true,
  status: true,
  cover_letter: true,
  cv_url: true,
  applied_at: true,
  updated_at: true,
  job: {
    select: {
      title: true,
      company_id: true,
      location: true,
      employment_type: true,
      salary: true,
      description: true,
      requirements: true,
      company: {
        select: {
          name: true,
          logo: true,
          industry: true,
          website: true,
          email: true,
          phone: true,
        },
      },
    },
  },
};

function shapeApplication(app) {
  return {
    id: app.id,
    user_id: app.user_id,
    job_id: app.job_id,
    job_title: app.job?.title || null,
    company_name: app.job?.company?.name || null,
    company_logo: app.job?.company?.logo || null,
    company_industry: app.job?.company?.industry || null,
    company_website: app.job?.company?.website || null,
    company_email: app.job?.company?.email || null,
    company_phone: app.job?.company?.phone || null,
    location: app.job?.location || null,
    employment_type: app.job?.employment_type || null,
    salary: app.job?.salary || null,
    job_description: app.job?.description || null,
    job_requirements: parseJsonField(app.job?.requirements) || [],
    status: app.status,
    cover_letter: app.cover_letter || null,
    cv_url: app.cv_url || null,
    applied_at: app.applied_at,
    updated_at: app.updated_at,
  };
}

/**
 * POST /api/applications/apply
 */
router.post(['/apply', '/apply.php'], async (req, res) => {
  const user = await requireRole(req, res, 'job_seeker');
  if (!user) return;

  const input = getInput(req);
  const jobId = parseInt(input.job_id || '0', 10);
  if (jobId <= 0) return sendError(res, 'Job ID is required.', 400);

  try {
    const job = await prisma.jobs.findFirst({
      where: { id: jobId, status: 'active' },
      select: { id: true, title: true, company_id: true },
    });
    if (!job) {
      return sendError(
        res,
        'Job not found or is no longer accepting applications.',
        404
      );
    }

    const dupe = await prisma.applications.findFirst({
      where: { user_id: Number(user.user_id), job_id: jobId },
      select: { id: true },
    });
    if (dupe) {
      return sendError(res, 'You have already applied for this job.', 409);
    }

    const created = await prisma.applications.create({
      data: {
        user_id: Number(user.user_id),
        job_id: jobId,
        status: 'pending',
        cover_letter: input.cover_letter || null,
        cv_url: input.cv_url || null,
      },
    });

    await createNotification(
      user.user_id,
      'application_submitted',
      'Application Submitted',
      `Your application for "${job.title}" has been submitted successfully.`,
      { job_id: jobId, application_id: created.id }
    );

    const company = await prisma.companies.findUnique({
      where: { id: job.company_id },
      select: { user_id: true },
    });
    if (company) {
      await createNotification(
        company.user_id,
        'new_application',
        'New Application Received',
        `A new application has been received for "${job.title}".`,
        {
          job_id: jobId,
          application_id: created.id,
          applicant_id: user.user_id,
        }
      );
    }

    const fresh = await prisma.applications.findUnique({
      where: { id: created.id },
      select: APP_SELECT,
    });

    return sendSuccess(
      res,
      { application: formatApplication(shapeApplication(fresh)) },
      'Application submitted successfully',
      201
    );
  } catch (err) {
    console.error('Apply for job failed:', err.message);
    return sendError(
      res,
      'An error occurred while submitting your application.',
      500
    );
  }
});

/**
 * GET /api/applications/my_applications
 */
router.get(['/my_applications', '/my_applications.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  const page = Math.max(1, parseInt(req.query.page || '1', 10));
  const limit = Math.min(100, Math.max(1, parseInt(req.query.limit || '20', 10)));
  const offset = (page - 1) * limit;
  const statusFilter = String(req.query.status || '').trim();

  try {
    const where = { user_id: Number(user.user_id) };
    if (statusFilter) where.status = statusFilter;

    const [total, rows] = await Promise.all([
      prisma.applications.count({ where }),
      prisma.applications.findMany({
        where,
        select: APP_SELECT,
        orderBy: { applied_at: 'desc' },
        take: limit,
        skip: offset,
      }),
    ]);

    const totalPages = Math.ceil(total / limit);
    return sendSuccess(
      res,
      {
        applications: rows.map((r) => formatApplication(shapeApplication(r))),
        pagination: { page, limit, total, total_pages: totalPages },
      },
      'Applications retrieved successfully'
    );
  } catch (err) {
    console.error('Get applications failed:', err.message);
    return sendError(res, 'An error occurred while retrieving applications.', 500);
  }
});

/**
 * GET /api/applications/application_details?id=...
 */
router.get(['/application_details', '/application_details.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  const applicationId = parseInt(req.query.id || '0', 10);
  if (applicationId <= 0) {
    return sendError(res, 'Application ID is required.', 400);
  }

  try {
    const app = await prisma.applications.findUnique({
      where: { id: applicationId },
      select: APP_SELECT,
    });
    if (!app) return sendError(res, 'Application not found.', 404);

    if (user.role === 'job_seeker' && Number(app.user_id) !== Number(user.user_id)) {
      return sendError(res, 'You do not have permission to view this application.', 403);
    }
    if (user.role === 'employer') {
      const companyId = app.job?.company_id;
      if (!companyId) {
        return sendError(res, 'You do not have permission to view this application.', 403);
      }
      const company = await prisma.companies.findUnique({
        where: { id: companyId },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(res, 'You do not have permission to view this application.', 403);
      }
    }

    const interview = await prisma.interviews.findFirst({
      where: { application_id: applicationId },
      orderBy: { scheduled_at: 'desc' },
    });

    return sendSuccess(
      res,
      { application: formatApplication(shapeApplication(app), interview) },
      'Application details retrieved successfully'
    );
  } catch (err) {
    console.error('Get application details failed:', err.message);
    return sendError(res, 'An error occurred while retrieving application details.', 500);
  }
});

/**
 * PUT/POST /api/applications/update_status
 */
router.put(['/update_status', '/update_status.php'], async (req, res) => updateStatusHandler(req, res));
router.post(['/update_status', '/update_status.php'], async (req, res) => updateStatusHandler(req, res));

async function updateStatusHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  const input = getInput(req);
  const applicationId = parseInt(input.id || '0', 10);
  if (applicationId <= 0) return sendError(res, 'Application ID is required.', 400);

  const newStatus = input.status || '';
  if (!VALID_STATUSES.includes(newStatus)) {
    return sendError(
      res,
      `Invalid status. Must be one of: ${VALID_STATUSES.join(', ')}`,
      422
    );
  }

  try {
    const app = await prisma.applications.findUnique({
      where: { id: applicationId },
      select: {
        id: true,
        user_id: true,
        job_id: true,
        status: true,
        job: { select: { title: true, company_id: true } },
      },
    });
    if (!app) return sendError(res, 'Application not found.', 404);

    if (user.role === 'job_seeker') {
      if (Number(app.user_id) !== Number(user.user_id)) {
        return sendError(res, 'You do not have permission to modify this application.', 403);
      }
      if (newStatus !== 'withdrawn') {
        return sendError(res, 'You can only withdraw your own application.', 403);
      }
    } else if (user.role === 'employer') {
      const company = await prisma.companies.findUnique({
        where: { id: app.job?.company_id || 0 },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(res, 'You do not have permission to modify this application.', 403);
      }
    }

    const data = { status: newStatus };
    if (
      user.role === 'job_seeker' &&
      Object.prototype.hasOwnProperty.call(input, 'cover_letter')
    ) {
      data.cover_letter = input.cover_letter;
    }
    await prisma.applications.update({ where: { id: applicationId }, data });

    // Notifications
    if (newStatus === 'interview') {
      await createNotification(
        app.user_id,
        'interview_scheduled',
        'Interview Scheduled',
        `Your application for "${app.job?.title}" has been moved to the interview stage.`,
        { application_id: applicationId, job_id: app.job_id }
      );
    } else if (newStatus === 'accepted') {
      await createNotification(
        app.user_id,
        'application_accepted',
        'Application Accepted',
        `Congratulations! Your application for "${app.job?.title}" has been accepted.`,
        { application_id: applicationId, job_id: app.job_id }
      );
    } else if (newStatus === 'rejected') {
      await createNotification(
        app.user_id,
        'application_rejected',
        'Application Update',
        `Your application for "${app.job?.title}" has been rejected.`,
        { application_id: applicationId, job_id: app.job_id }
      );
    } else if (newStatus === 'withdrawn') {
      await createNotification(
        app.user_id,
        'application_withdrawn',
        'Application Withdrawn',
        `You have withdrawn your application for "${app.job?.title}".`,
        { application_id: applicationId, job_id: app.job_id }
      );
    }

    const fresh = await prisma.applications.findUnique({
      where: { id: applicationId },
      select: APP_SELECT,
    });

    return sendSuccess(
      res,
      { application: formatApplication(shapeApplication(fresh)) },
      'Application status updated successfully'
    );
  } catch (err) {
    console.error('Update application status failed:', err.message);
    return sendError(res, 'An error occurred while updating the application.', 500);
  }
}

module.exports = router;
