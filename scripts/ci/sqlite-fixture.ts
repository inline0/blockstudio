import { existsSync } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';

export function keepSqliteFixtureOpen(path: string) {
  if (!existsSync(path))
    throw new Error(`SQLite fixture was not seeded: ${path}`);
  const database = new DatabaseSync(path);
  try {
    database.exec('PRAGMA busy_timeout=5000');
    const mode = database.prepare('PRAGMA journal_mode').get();
    if (mode?.journal_mode !== 'wal')
      throw new Error('The SQLite fixture must retain its normal WAL mode.');
    // Keep real WAL sidecars alive; request shutdown must not churn watched filenames.
    database.prepare('PRAGMA schema_version').get();
    return database;
  } catch (error) {
    database.close();
    throw error;
  }
}
