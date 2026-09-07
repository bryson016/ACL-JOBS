/**
 * Interviews routes. Uses Prisma ORM.
 */

const express = require('express');
const { sendSuccess, sendError, getInput } = require('../config/functions');
const { requireAuth, requireRole, createNotification } = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

function formatInterview(row) {
  return {
    id: Number(row.id),
    application_id: Number(row.application_id),
    job_id: Number(row.job_id),
    job_title: row.job_title || null,
    user_id: Number(row.user_id),
    applicant_name: row.applicant_name || row.applicant_username || null,
    company_id: Number(row.company_id),
    company_name: row.company_name || null,
    company_logo: row.company_logo || null,
    title: row.title,
    description: row.description || null,
    scheduled_at: row.scheduled_at,
    duration: Number(row.duration || 0),
    location: row.location || null,
    meeting_link: row.meeting_link || null,
    status: row.status,
    notes: row.notes || null,
    created_at: row.created_at,
    updated_at: row.updated_at,
  };
}

const INTERVIEW_SELECT = {
  id: true,
  application_id: true,
  job_id: true,
  user_id: true,
  company_id: true,
  title: true,
  description: true,
  scheduled_at: true,
  duration: true,
  location: true,
  meeting_link: true,
  status: true,
  notes: true,
  created_at: true,
  updated_at: true,
  job: {
    select: {
      title: true,
      company: { select: { name: true, logo: true } },
    },
  },
};

function shapeInterview(i) {
  return {
    ...i,
    job_title: i.job?.title || null,
    company_name: i.job?.company?.name || null,
    company_logo: i.job?.company?.logo || null,
  };
}

/**
 * GET /api/interviews/list
 */
router.get(['/list', '/list.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  const page = Math.max(1, parseInt(req.query.page || '1', 10));
  const limit = Math.min(100, Math.max(1, parseInt(req.query.limit || '20', 10)));
  const offset = (page - 1) * limit;
  const statusFilter = String(req.query.status || '').trim();

  try {
    const where = {};
    if (user.role === 'job_seeker') {
      where.user_id = Number(user.user_id);
    } else if (user.role === 'employer') {
      // interviews where company.user_id = me
      where.company = { user_id: Number(user.user_id) };
    }
    if (statusFilter) where.status = statusFilter;

    const [total, rows] = await Promise.all([
      prisma.interviews.count({ where }),
      prisma.interviews.findMany({
        where,
        orderBy: { scheduled_at: 'asc' },
        take: limit,
        skip: offset,
        select: {
          ...INTERVIEW_SELECT,
          user: { select: { username: true, profile: { select: { full_name: true } } } },
        },
      }),
    ]);

    const formatted = rows.map((i) =>
      formatInterview({
        ...shapeInterview(i),
        applicant_username: i.user?.username || null,
        applicant_name: i.user?.profile?.full_name || null,
      })
    );

    const totalPages = Math.ceil(total / limit);
    return sendSuccess(
      res,
      {
        interviews: formatted,
        pagination: { page, limit, total, total_pages: totalPages },
      },
      'Interviews retrieved successfully'
    );
  } catch (err) {
    console.error('List interviews failed:', err.message);
    return sendError(res, 'An error occurred while retrieving interviews.', 500);
  }
});

/**
 * POST /api/interviews/create
 */
router.post(['/create', '/create.php'], async (req, res) => {
  const user = await requireRole(req, res, ['employer', 'admin']);
  if (!user) return;

  const input = getInput(req);
  const applicationId = parseInt(input.application_id || '0', 10);
  if (applicationId <= 0) return sendError(res, 'Application ID is required.', 400);
  if (!input.title) return sendError(res, 'Interview title is required.', 422);
  if (!input.scheduled_at) {
    return sendError(res, 'Scheduled date and time is required.', 422);
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

    if (user.role === 'employer') {
      const company = await prisma.companies.findUnique({
        where: { id: app.job?.company_id || 0 },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(
          res,
          'You do not have permission to schedule interviews for this application.',
          403
        );
      }
    }

    const created = await prisma.interviews.create({
      data: {
        application_id: applicationId,
        job_id: app.job_id,
        user_id: app.user_id,
        company_id: app.job?.company_id,
        title: input.title,
        description: input.description || null,
        scheduled_at: new Date(input.scheduled_at),
        duration: parseInt(input.duration || '60', 10),
        location: input.location || null,
        meeting_link: input.meeting_link || null,
        status: 'scheduled',
      },
    });

    await prisma.applications.update({
      where: { id: applicationId },
      data: { status: 'interview' },
    });

    await createNotification(
      app.user_id,
      'interview_scheduled',
      'Interview Scheduled',
      `An interview has been scheduled for your application to "${app.job?.title}".`,
      {
        interview_id: created.id,
        application_id: applicationId,
        job_id: app.job_id,
      }
    );

    const interview = await prisma.interviews.findUnique({
      where: { id: created.id },
      select: INTERVIEW_SELECT,
    });
    return sendSuccess(
      res,
      { interview: formatInterview(shapeInterview(interview)) },
      'Interview scheduled successfully',
      201
    );
  } catch (err) {
    console.error('Create interview failed:', err.message);
    return sendError(res, 'An error occurred while scheduling the interview.', 500);
  }
});

/**
 * PUT/POST /api/interviews/update
 */
router.put(['/update', '/update.php'], async (req, res) => updateInterviewHandler(req, res));
router.post(['/update', '/update.php'], async (req, res) => updateInterviewHandler(req, res));

async function updateInterviewHandler(req, res) {
  const user = await requireAuth(req, res);
  if (!user) return;

  const input = getInput(req);
  const interviewId = parseInt(input.id || '0', 10);
  if (interviewId <= 0) return sendError(res, 'Interview ID is required.', 400);

  try {
    const interview = await prisma.interviews.findUnique({
      where: { id: interviewId },
      select: {
        id: true,
        application_id: true,
        job_id: true,
        user_id: true,
        company_id: true,
        job: { select: { title: true } },
      },
    });
    if (!interview) return sendError(res, 'Interview not found.', 404);

    if (user.role === 'employer') {
      const company = await prisma.companies.findUnique({
        where: { id: interview.company_id },
        select: { user_id: true },
      });
      if (!company || Number(company.user_id) !== Number(user.user_id)) {
        return sendError(
          res,
          'You do not have permission to modify this interview.',
          403
        );
      }
    } else if (user.role === 'job_seeker') {
      return sendError(
        res,
        'You do not have permission to modify interviews.',
        403
      );
    }

    const updatable = [
      'title',
      'description',
      'scheduled_at',
      'duration',
      'location',
      'meeting_link',
      'status',
      'notes',
    ];
    const data = {};
    for (const field of updatable) {
      if (Object.prototype.hasOwnProperty.call(input, field)) {
        data[field] =
          field === 'scheduled_at' ? new Date(input[field]) : input[field];
      }
    }
    if (Object.keys(data).length === 0) {
      return sendError(res, 'No fields to update.', 400);
    }

    await prisma.interviews.update({ where: { id: interviewId }, data });

    if (input.status === 'completed') {
      await createNotification(
        interview.user_id,
        'interview_completed',
        'Interview Completed',
        `Your interview for "${interview.job?.title}" has been marked as completed.`,
        {
          interview_id: interviewId,
          application_id: interview.application_id,
        }
      );
    }

    const fresh = await prisma.interviews.findUnique({
      where: { id: interviewId },
      select: INTERVIEW_SELECT,
    });
    return sendSuccess(
      res,
      { interview: formatInterview(shapeInterview(fresh)) },
      'Interview updated successfully'
    );
  } catch (err) {
    console.error('Update interview failed:', err.message);
    return sendError(res, 'An error occurred while updating the interview.', 500);
  }
}

module.exports = router;
