import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import config from '../../webpack.config.ts';
import { ships } from '../build-release.ts';
import { compileRuntime, runtimeRoots } from '../build-runtime.ts';

test('CI npm projects have tracked lockfiles matching their manifests', () => {
  const root = resolve(import.meta.dirname, '../..');
  const tracked = new Set(
    execFileSync('git', ['ls-files', '-z'], {
      cwd: root,
      encoding: 'utf8',
    }).split('\0'),
  );

  for (const prefix of ['', 'packages/registry/']) {
    const lockfile = `${prefix}package-lock.json`;
    assert.ok(
      tracked.has(lockfile),
      `${lockfile} must be committed for npm ci`,
    );

    const manifest = JSON.parse(
      readFileSync(resolve(root, `${prefix}package.json`), 'utf8'),
    );
    const lock = JSON.parse(readFileSync(resolve(root, lockfile), 'utf8'));
    assert.ok(lock.lockfileVersion >= 2, `${lockfile} needs package metadata`);

    for (const key of [
      'dependencies',
      'devDependencies',
      'optionalDependencies',
    ]) {
      assert.deepEqual(
        lock.packages[''][key],
        manifest[key],
        `${lockfile} ${key} must match package.json`,
      );
    }
  }
});

test('runtime compilation erases types and preserves WordPress module imports', () => {
  const output = compileRuntime(
    `import { store } from '@wordpress/interactivity';
interface Context { value: string; }
const value: string = 'same';
store('bsui/test', { state: { value } });`,
    'src/ui/test.ts',
  );
  assert.match(output, /from ['"]@wordpress\/interactivity['"]/);
  assert.match(output, /const value = ['"]same['"]/);
  assert.doesNotMatch(output, /interface Context|require\(|sourceMappingURL/);
});

test('classic browser scripts stay classic, without an export marker or loader', () => {
  const output = compileRuntime(
    '(() => { const count: number = 1; window.name = String(count); })();',
    'src/runtime/example.ts',
  );
  assert.doesNotMatch(output, /\b(?:import|export|require)\b/);
  assert.equal(runtimeRoots[0][1], 'includes/ui/blocks');
  assert.equal(runtimeRoots[1][1], 'includes/assets/runtime');
});

test('WordPress entry filenames, dependency extraction, and async vendor chunks remain stable', () => {
  assert.deepEqual(Object.keys(config.entry), [
    'blocks/index.tsx',
    'pages/index',
    'admin/index',
    'devtools/index',
    'canvas/index',
  ]);
  assert.ok(config.output.path.endsWith('includes/admin/assets'));
  assert.equal(
    config.plugins.filter(
      (plugin) =>
        plugin &&
        plugin.constructor.name === 'DependencyExtractionWebpackPlugin',
    ).length,
    1,
  );
  assert.equal(
    config.optimization.splitChunks.cacheGroups.codemirror.chunks,
    'async',
  );
});

test('release packaging excludes tooling and fixtures but includes runtime assets and PHP schemas', () => {
  for (const file of [
    'AGENTS.md',
    'tests/ui/AGENTS.md',
    '.nvmrc',
    '.oxlintrc.json',
    'oxfmt.config.ts',
    'webpack.config.ts',
    'tsconfig.browser.json',
    'scripts/lib/runtime.ts',
    'src/ui/global-script.ts',
    'tests/theme/block/script.js',
    'docs/docs/pages.md',
    'benchmarks/README.md',
    'benchmarks/results/wp-env-unit.json',
    'packages/registry/src/index.ts',
  ])
    assert.equal(ships(file), false, file);
  for (const file of [
    'blockstudio.php',
    'composer.json',
    'lib/runtime-autoload.php',
    'includes/ui/blocks/global-script.js',
    'includes/admin/assets/blocks/index.tsx.js',
    'schemas/block.json',
  ])
    assert.equal(ships(file), true, file);
});
