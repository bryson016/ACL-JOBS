/**
 * Dashboard stats route. Uses Prisma ORM.
 *
 * Returns a different set of counts depending on the user's role.
 */

const express = require('express');
const { sendSuccess, sendError } = require('../config/functions');
const { requireAuth } = require('../config/auth');
const { prisma } = require('../config/database');

const router = express.Router();

router.get(['/stats', '/stats.php'], async (req, res) => {
  const user = await requireAuth(req, res);
  if (!user) return;

  try {
    const stats = {};
    const uid = Number(user.user_id);

    if (user.role === 'job_seeker') {
      const [applications, interviews, offers, savedJobs, unread] =
        await Promise.all([
          prisma.applications.count({ where: { user_id: uid } }),
          prisma.interviews.count({
            where: {
              user_id: uid,
              status: 'scheduled',
              scheduled_at: { gt: new Date() },
            },
          }),
          prisma.applications.count({
            where: { user_id: uid, status: 'accepted' },
          }),
          prisma.saved_jobs.count({ where: { user_id: uid } }),
          prisma.notifications.count({
            where: { user_id: uid, is_read: 0 },
          }),
        ]);
      stats.applications = Number(applications);
      stats.interviews = Number(interviews);
      stats.offers = Number(offers);
      stats.saved_jobs = Number(savedJobs);
      stats.unread_notifications = Number(unread);
    } else if (user.role === 'employer') {
      const myCompanies = await prisma.companies.findMany({
        where: { user_id: uid },
        select: { id: true },
      });
      const companyIds = myCompanies.map((c) => c.id);

      if (companyIds.length > 0) {
        const [jobsPosted, appsReceived, interviews, pending] =
          await Promise.all([
            prisma.jobs.count({ where: { company_id: { in: companyIds } } }),
            prisma.applications.count({
              where: { job: { company_id: { in: companyIds } } },
            }),
            prisma.interviews.count({
              where: { company_id: { in: companyIds } },
            }),
            prisma.applications.count({
              where: {
                job: { company_id: { in: companyIds } },
                status: 'pending',
              },
            }),
          ]);
        stats.jobs_posted = Number(jobsPosted);
        stats.applications_received = Number(appsReceived);
        stats.interviews = Number(interviews);
        stats.pending_applications = Number(pending);
      } else {
        stats.jobs_posted = 0;
        stats.applications_received = 0;
        stats.interviews = 0;
        stats.pending_applications = 0;
      }
      const unread = await prisma.notifications.count({
        where: { user_id: uid, is_read: 0 },
      });
      stats.unread_notifications = Number(unread);
    } else if (user.role === 'admin') {
      const [jobSeekers, employers, jobs, apps, upcoming, unread] =
        await Promise.all([
          prisma.users.count({ where: { role: 'job_seeker' } }),
          prisma.users.count({ where: { role: 'employer' } }),
          prisma.jobs.count(),
          prisma.applications.count(),
          prisma.interviews.count({ where: { status: 'scheduled' } }),
          prisma.notifications.count({
            where: { user_id: uid, is_read: 0 },
          }),
        ]);
      stats.total_job_seekers = Number(jobSeekers);
      stats.total_employers = Number(employers);
      stats.total_jobs = Number(jobs);
      stats.total_applications = Number(apps);
      stats.upcoming_interviews = Number(upcoming);
      stats.unread_notifications = Number(unread);
    }

    return sendSuccess(res, { stats }, 'Dashboard statistics retrieved successfully');
  } catch (err) {
    console.error('Get dashboard stats failed:', err.message);
    return sendError(res, 'An error occurred while retrieving dashboard statistics.', 500);
  }
});

module.exports = router;
