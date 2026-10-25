import { Effect, FileSystem } from 'effect';
import { resolve, relative } from 'node:path';
import { command } from './lib/command.ts';
import { main, ToolingError } from './lib/runtime.ts';

const root = resolve(import.meta.dirname, '..');
const excludedDirectories = new Set([
  '.git',
  'node_modules',
  'tests',
  'docs',
  'evals',
  'src',
  '.github',
  '_reference',
  '.claude',
  'build',
  'packages',
  'prds',
  'benchmarks',
  'scripts',
  'vendor',
]);
const excludedFiles = new Set([
  '.babelrc',
  'package.json',
  'package-lock.json',
  '.npmrc',
  '.nvmrc',
  '.oxlintrc.json',
  '.gitignore',
  '.gitattributes',
  'scoper.inc.php',
  'build.sh',
  'AGENTS.md',
  'blockstudio.zip',
]);

export const ships = (path: string) =>
  !excludedDirectories.has(path.split('/')[0]) &&
  !excludedFiles.has(path) &&
  !/(^|\/)\.env|\.config\.|(^|\/)tsconfig|(^|\/)\.prettierrc|(^|\/)eslint|(^|\/)phpcs\.xml/.test(
    path,
  );

export const buildRelease = Effect.scoped(
  Effect.gen(function* () {
    const fs = yield* FileSystem.FileSystem;
    const run = (executable: string, args: string[], cwd = root) =>
      command(executable, args, { cwd, inherit: true });
    yield* run('composer', ['scope', '--', '--check']);
    const directory = resolve(root, 'build/blockstudio');
    yield* fs.remove(directory, { recursive: true, force: true });
    yield* fs.makeDirectory(directory, { recursive: true });
    const files = (yield* command(
      'git',
      ['ls-files', '--cached', '--others', '--exclude-standard', '-z'],
      { cwd: root },
    )).stdout
      .split('\0')
      .filter(Boolean);
    for (const path of new Set(files.filter(ships))) {
      if (!(yield* fs.exists(resolve(root, path)))) continue;
      const destination = resolve(directory, path);
      yield* fs.makeDirectory(resolve(destination, '..'), { recursive: true });
      yield* fs.copyFile(resolve(root, path), destination);
    }
    yield* run(
      'composer',
      ['install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
      directory,
    );
    const outputs = yield* fs.readDirectory(directory, { recursive: true });
    const maps = outputs.filter((file) => file.endsWith('.map'));
    if (maps.length)
      return yield* Effect.fail(
        new ToolingError({
          message: `Source maps leaked into release: ${maps.join(', ')}`,
        }),
      );
    yield* run('php', [
      'tests/distribution/dependencies.php',
      relative(root, directory),
    ]);
    yield* fs.remove(resolve(root, 'blockstudio.zip'), { force: true });
    yield* run(
      'zip',
      ['-qr', '../blockstudio.zip', 'blockstudio/'],
      resolve(root, 'build'),
    );
  }),
);

if (import.meta.main) main(buildRelease);
