import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { keepSqliteFixtureOpen } from '../ci/sqlite-fixture.ts';

test('real SQLite request connections can write without churning fixture-directory mtimes', (t) => {
  const directory = mkdtempSync(join(tmpdir(), 'blockstudio-sqlite-fixture-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const path = join(directory, 'default.sqlite');
  const seed = new DatabaseSync(path);
  seed.exec('PRAGMA journal_mode=WAL; CREATE TABLE records (value INTEGER)');
  seed.close();
  const keeper = keepSqliteFixtureOpen(path);
  let closed = false;
  t.after(() => {
    if (!closed) keeper.close();
  });
  assert.equal(existsSync(`${path}-wal`), true);
  assert.equal(existsSync(`${path}-shm`), true);
  const before = statSync(directory, { bigint: true }).mtimeNs;
  for (let i = 0; i < 3; i++) {
    const request = new DatabaseSync(path);
    request.exec('PRAGMA journal_mode=WAL; INSERT INTO records VALUES (1)');
    request.close();
    assert.equal(existsSync(`${path}-wal`), true);
    assert.equal(statSync(directory, { bigint: true }).mtimeNs, before);
  }
  assert.equal(
    keeper.prepare('SELECT COUNT(*) AS count FROM records').get()?.count,
    3,
  );
  keeper.close();
  closed = true;
  assert.equal(existsSync(`${path}-wal`), false);
});

test('missing or non-WAL fixtures are rejected instead of changing database behavior', (t) => {
  const directory = mkdtempSync(join(tmpdir(), 'blockstudio-sqlite-fixture-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const path = join(directory, 'default.sqlite');
  assert.throws(() => keepSqliteFixtureOpen(path), /not seeded/);
  const seed = new DatabaseSync(path);
  seed.exec('CREATE TABLE records (value INTEGER)');
  seed.close();
  assert.throws(() => keepSqliteFixtureOpen(path), /normal WAL mode/);
});
