import { Effect } from 'effect';
import * as HttpClient from 'effect/http/HttpClient';
import { resolve } from 'node:path';
import { command } from './lib/command.ts';
import { main, ToolingError } from './lib/runtime.ts';

const root = resolve(import.meta.dirname, '..');
const modes = {
  main: { directory: 'tests/wp-env', project: 'blockstudio', theme: 'theme' },
  empty: {
    directory: 'tests/wp-env-empty',
    project: 'blockstudio-empty',
    theme: 'theme-empty',
  },
  plugin: {
    directory: 'tests/implementations/plugin',
    project: 'blockstudio-plugin',
    theme: 'theme',
  },
  'mu-plugin': {
    directory: 'tests/implementations/mu-plugin',
    project: 'blockstudio-mu-plugin',
    theme: 'theme',
  },
  'composer-theme': {
    directory: 'tests/implementations/composer-theme',
    project: 'blockstudio-composer-theme',
    theme: 'theme',
  },
  'composer-plugin': {
    directory: 'tests/implementations/composer-plugin',
    project: 'blockstudio-composer-plugin',
    theme: 'theme',
  },
} as const;

export function environment(name: string) {
  if (!(name in modes))
    throw new ToolingError({
      message: `Unknown wp-env environment: ${name}`,
      exitCode: 64,
    });
  return modes[name as keyof typeof modes];
}

export const wpEnv = (mode: string, action: string, args: string[] = []) =>
  Effect.gen(function* () {
    if (
      !(mode in modes) ||
      !['start', 'stop', 'reset', 'verify', 'unit'].includes(action)
    ) {
      return yield* Effect.fail(
        new ToolingError({
          message: `Unknown wp-env operation: ${mode} ${action}`,
          exitCode: 64,
        }),
      );
    }
    const config = environment(mode);
    const cwd = resolve(root, config.directory);
    const options = {
      cwd,
      env: { COMPOSE_PROJECT_NAME: config.project },
      inherit: true,
    };
    const run = (...args: string[]) =>
      command(resolve(root, 'node_modules/.bin/wp-env'), args, options);

    if (action === 'stop') {
      yield* run('stop');
      return;
    }
    if (action === 'unit') {
      yield* run(
        'run',
        'cli',
        '--env-cwd=/var/www/html/wp-content/plugins/blockstudio7',
        'vendor/bin/phpunit',
        ...args,
      );
      return;
    }
    if (action === 'verify') {
      yield* run(
        'run',
        'cli',
        'wp',
        'eval',
        'if (!defined("BLOCKSTUDIO_VERSION") || get_option("blockstudio_version") !== BLOCKSTUDIO_VERSION) { exit(1); } echo BLOCKSTUDIO_VERSION;',
      );
      if (mode === 'composer-theme')
        yield* run(
          'run',
          'cli',
          'wp',
          'eval-file',
          '/var/www/html/wp-content/themes/theme/runtime-probe.php',
        );
      return;
    }
    if (action === 'reset') {
      yield* run('stop');
      yield* run('clean', 'all');
    }
    if (mode === 'composer-theme' || mode === 'composer-plugin') {
      yield* command('composer', ['install', '--no-dev', '--no-interaction'], {
        ...options,
        cwd: resolve(cwd, mode === 'composer-theme' ? 'theme' : 'plugin'),
      });
    }
    yield* run('start');
    yield* run('run', 'cli', 'wp', 'theme', 'activate', config.theme);
    if (mode === 'plugin') {
      yield* run('run', 'cli', 'wp', 'plugin', 'activate', 'blockstudio').pipe(
        Effect.catch(() =>
          run('run', 'cli', 'wp', 'plugin', 'activate', 'blockstudio7'),
        ),
      );
    }
    if (mode === 'composer-plugin')
      yield* run('run', 'cli', 'wp', 'plugin', 'activate', 'plugin');
    yield* run(
      'run',
      'cli',
      'wp',
      'rewrite',
      'structure',
      '/%postname%/',
      '--hard',
    );
    if (mode === 'main') {
      const client = (yield* HttpClient.HttpClient).pipe(
        HttpClient.filterStatusOk,
      );
      const response = yield* client.post(
        'http://localhost:8888/wp-json/blockstudio-test/v1/e2e/setup',
      );
      console.log(yield* response.text);
    }
  });

if (import.meta.main)
  main(
    wpEnv(
      process.argv[2] ?? 'main',
      process.argv[3] ?? 'start',
      process.argv.slice(4),
    ),
  );
