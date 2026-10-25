import { test } from 'node:test';
import assert from 'node:assert/strict';
import { globSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';
import { parseShard, partitionFiles } from '../ci/e2e-shards.ts';

test('shard selection rejects invalid or empty selections', () => {
  assert.deepEqual(parseShard('2/4'), { current: 2, total: 4 });
  for (const value of ['0/4', '5/4', '1/0', '1.5/4', '1', '1/4/2'])
    assert.throws(() => parseShard(value));
  assert.throws(() => partitionFiles(['a'], {}, 2));
  assert.throws(() => partitionFiles(['a'], {}, 0));
  assert.throws(() => partitionFiles(['a', 'a'], {}, 1));
});

test('time-weighted shards keep every file exactly once and leave input unchanged', () => {
  const files = ['a', 'b', 'c', 'd', 'e', 'f'];
  const weights = { a: 12, b: 11, c: 10, d: 9, e: 8, f: 7 };
  const shards = partitionFiles(files, weights, 3);
  assert.deepEqual(
    shards.map((shard) => shard.seconds),
    [19, 19, 19],
  );
  assert.deepEqual(shards.flatMap((shard) => shard.files).sort(), files);
  assert.equal(new Set(shards.flatMap((shard) => shard.files)).size, 6);
  assert.deepEqual(files, ['a', 'b', 'c', 'd', 'e', 'f']);
  assert.deepEqual(partitionFiles([...files].reverse(), weights, 3), shards);
});

test('new test files are included automatically without trusting invalid historical timings', () => {
  const shards = partitionFiles(
    ['new.ts', 'known.ts', 'invalid.ts'],
    { 'known.ts': 10, 'invalid.ts': -1, removed: Number.NaN },
    2,
  );
  assert.deepEqual(shards.flatMap((shard) => shard.files).sort(), [
    'invalid.ts',
    'known.ts',
    'new.ts',
  ]);
  assert.equal(
    shards.reduce((sum, shard) => sum + shard.seconds, 0),
    30,
  );
  assert.equal(partitionFiles(['b', 'a'], {}, 2)[0].files[0], 'a');
});

test('the configured CI shards partition the complete existing file selection', () => {
  const root = resolve(import.meta.dirname, '../..');
  const config = ts.createSourceFile(
    'playwright.wp-env.config.ts',
    readFileSync(resolve(root, 'playwright.wp-env.config.ts'), 'utf8'),
    ts.ScriptTarget.Latest,
    true,
  );
  const declaration = config.statements
    .filter(ts.isVariableStatement)
    .flatMap((statement) => statement.declarationList.declarations)
    .find((item) => item.name.getText(config) === 'patterns');
  assert.ok(declaration?.initializer);
  assert.ok(ts.isArrayLiteralExpression(declaration.initializer));
  const patterns = declaration.initializer.elements.map((element) => {
    assert.ok(ts.isStringLiteral(element));
    return element.text;
  });
  const files = globSync(patterns, { cwd: resolve(root, 'tests/e2e') }).sort();
  assert.ok(files.length > 0);
  const { seconds } = JSON.parse(
    readFileSync(resolve(root, 'scripts/ci/e2e-file-durations.json'), 'utf8'),
  );
  const assigned = partitionFiles(files, seconds, 4).flatMap(
    (shard) => shard.files,
  );
  assert.deepEqual(assigned.sort(), files);
  assert.equal(new Set(assigned).size, files.length);
});
