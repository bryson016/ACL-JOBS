/* Lists users in the local database to diagnose login issues. */
const { prisma } = require('./config/database');

prisma.users
  .findMany({
    select: { id: true, email: true, username: true, role: true, created_at: true },
    orderBy: { id: 'asc' },
  })
  .then((users) => {
    console.log(`Found ${users.length} user(s):`);
    console.log(JSON.stringify(users, null, 1));
    process.exit(0);
  })
  .catch((e) => {
    console.error('DB error:', e.message);
    process.exit(1);
  });