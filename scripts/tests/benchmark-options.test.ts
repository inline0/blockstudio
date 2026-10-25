import { test } from 'node:test';
import assert from 'node:assert/strict';
import { globSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import {
  benchmarkOptions,
  playgroundArguments,
  probeValue,
} from '../ci/benchmark-options.ts';

test('benchmark options require an explicit runtime and complete suite', () => {
  assert.deepEqual(benchmarkOptions(['playground', 'e2e-4']), {
    runtime: 'playground',
    suite: 'e2e-4',
  });
  assert.throws(() => benchmarkOptions(['unknown', 'unit']));
  assert.throws(() => benchmarkOptions(['wp-env', 'unknown']));
  assert.deepEqual(benchmarkOptions(['playground', 'smoke']), {
    runtime: 'playground',
    suite: 'smoke',
  });
  assert.throws(() => benchmarkOptions(['wp-env', 'smoke']));
});

test('WordPress suites use the development instance without an unused second database and site', () => {
  const root = resolve(import.meta.dirname, '../..');
  const configs = globSync(
    ['tests/*/.wp-env.json', 'tests/implementations/*/.wp-env.json'],
    { cwd: root },
  );
  assert.ok(configs.length > 0);
  for (const config of configs)
    assert.equal(
      JSON.parse(readFileSync(resolve(root, config), 'utf8')).testsEnvironment,
      false,
      config,
    );
});

test('benchmark probes ignore CLI banners and PHP notices', () => {
  assert.equal(
    probeValue(
      'Notice: fixture\nBLOCKSTUDIO_BENCHMARK_ENV={"php":"8.3"}\nDone\n',
      'ENV',
    ),
    '{"php":"8.3"}',
  );
  assert.throws(() => probeValue('Notice: no result', 'ENV'));
});

test('the CLI transport preserves quoted PHP and maps only the WordPress root', () => {
  assert.deepEqual(playgroundArguments(`eval 'echo "hello world";'`), [
    'eval',
    'echo "hello world";',
  ]);
  assert.deepEqual(
    playgroundArguments([
      'bs',
      'blocks',
      'render',
      '--attributes=@/var/www/html/test.json',
    ]),
    ['bs', 'blocks', 'render', '--attributes=@/wordpress/test.json'],
  );
  assert.deepEqual(
    playgroundArguments(
      `bs blocks render demo/test --attributes='{"text":"Hello world"}'`,
    ),
    [
      'bs',
      'blocks',
      'render',
      'demo/test',
      '--attributes={"text":"Hello world"}',
    ],
  );
  assert.throws(() => playgroundArguments('option get home; rm file'));
});
