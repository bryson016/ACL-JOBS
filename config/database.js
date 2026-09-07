/**
 * Database access for ACL Jobs API.
 *
 * Uses Prisma ORM (@prisma/client) generated from
 * `prisma/schema.prisma`, which mirrors the existing MySQL tables
 * (`users`, `companies`, `profiles`, `jobs`, `applications`,
 * `saved_jobs`, `interviews`, `notifications`, `auth_tokens`).
 *
 * The schema is read-only against the live DB: we never run
 * `prisma migrate` / `prisma db push`, so existing data is preserved.
 */

const { PrismaClient } = require('@prisma/client');

// Single shared Prisma client. Prisma manages its own connection pool.
const prisma = new PrismaClient({
  log:
    process.env.PRISMA_LOG === 'true'
      ? ['query', 'info', 'warn', 'error']
      : ['warn', 'error'],
});

/**
 * Test the database connection.
 * @returns {Promise<boolean>}
 */
async function testConnection() {
  try {
    await prisma.$queryRaw`SELECT 1`;
    return true;
  } catch (err) {
    console.error('Database connection failed:', err.message);
    return false;
  }
}

/**
 * Run a callback inside a Prisma transaction.
 * The callback receives a transactional Prisma client so every
 * statement inside it commits atomically.
 *
 * @template T
 * @param {(tx: typeof prisma) => Promise<T>} callback
 * @returns {Promise<T>}
 */
async function transaction(callback) {
  return prisma.$transaction(async (tx) => callback(tx));
}

module.exports = {
  prisma,
  testConnection,
  transaction,
};
