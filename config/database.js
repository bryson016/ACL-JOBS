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
//
// We deliberately set `log: []` so Prisma does not write its own
// "Can't reach database server" warnings to the console. The boot
// `testConnection()` produces a single, friendly message of our own
// instead, and route-level errors are surfaced as JSON responses.
const prisma = new PrismaClient({ log: [] });

/**
 * Test the database connection.
 *
 * Never throws and never writes the raw Prisma error to the console —
 * the caller (`server.js`) prints a single friendly line on failure so
 * the boot log stays clean.
 *
 * @returns {Promise<boolean>}
 */
async function testConnection() {
  try {
    await prisma.$queryRaw`SELECT 1`;
    return true;
  } catch (_err) {
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
