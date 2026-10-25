import { Effect, FileSystem, Schedule } from 'effect';
import * as HttpClient from 'effect/http/HttpClient';
import { existsSync, statSync } from 'node:fs';
import { createServer } from 'node:net';
import { randomUUID } from 'node:crypto';
import { resolve, relative, isAbsolute } from 'node:path';
import { command } from './lib/command.ts';
import { runCLI } from './lib/playground.ts';
import { attempt, attemptPromise, main, ToolingError } from './lib/runtime.ts';

const root = resolve(import.meta.dirname, '..');
const php = process.env.PLAYGROUND_PHP ?? '8.4';
const wordpress = process.env.PLAYGROUND_WP ?? 'latest';

export const runtimeOptions = () => ({ php, wp: wordpress, workers: 6 });

// Playground ignores --php and --wp as soon as a Blueprint is passed, so the versions live in the Blueprint.
export const blueprint = (
  constants: Record<string, unknown>,
  session: string,
) => ({
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  preferredVersions: { php, wp: wordpress },
  extraLibraries: ['wp-cli'],
  steps: [
    {
      step: 'defineWpConfigConsts',
      consts: { ...constants, BLOCKSTUDIO_PLAYGROUND_SESSION: session },
    },
    { step: 'activateTheme', themeFolderName: 'theme' },
    { step: 'activatePlugin', pluginPath: 'blockstudio/blockstudio.php' },
    {
      step: 'setSiteOptions',
      options: { permalink_structure: '/%postname%/' },
    },
    { step: 'mkdir', path: '/wordpress/wp-content/mu-plugins' },
    {
      step: 'writeFile',
      path: '/wordpress/wp-content/mu-plugins/blockstudio-playground-session.php',
      data: "<?php header('X-Blockstudio-Playground-Session: ' . BLOCKSTUDIO_PLAYGROUND_SESSION);",
    },
  ],
});

export const requireSession = (
  actual: string | undefined,
  expected: string,
): void => {
  if (!expected || actual !== expected)
    throw new ToolingError({
      message:
        'The server is not this Playground instance; refusing to seed it.',
    });
};

export const checkPort = (port: number): Promise<void> =>
  new Promise((resolvePort, reject) => {
    const server = createServer();
    server.once('error', reject);
    server.listen({ port, host: '127.0.0.1' }, () =>
      server.close((error) => (error ? reject(error) : resolvePort())),
    );
  });

export const validateSpecs = (args: string[]): string[] => {
  if (!args.length)
    throw new ToolingError({
      message: 'Pass explicit test spec filenames. Full suites stay in CI.',
      exitCode: 64,
    });
  for (const arg of args) {
    const file = resolve(root, arg);
    const path = relative(resolve(root, 'tests/e2e'), file).replaceAll(
      '\\',
      '/',
    );
    if (
      path.startsWith('../') ||
      isAbsolute(path) ||
      !path.endsWith('.ts') ||
      !existsSync(file) ||
      !statSync(file).isFile() ||
      path.startsWith('utils/') ||
      ['cli.ts', 'seo.ts'].includes(path)
    )
      throw new ToolingError({
        message: `Unsupported Playground spec: ${arg}. Pass exact supported spec filenames.`,
        exitCode: 64,
      });
  }
  return args;
};

// The plugin loads vendor/autoload.php and the theme needs Timber, as in the wp-env CI jobs.
const dependencies = Effect.gen(function* () {
  for (const [directory, flags] of [
    ['.', ['--no-dev']],
    ['tests/theme', []],
  ] as const) {
    if (!existsSync(resolve(root, directory, 'vendor/autoload.php')))
      yield* command('composer', ['install', '--no-interaction', ...flags], {
        cwd: resolve(root, directory),
        inherit: true,
      });
  }
});

const playground = <A, E, R>(work: Effect.Effect<A, E, R>) =>
  Effect.scoped(
    Effect.gen(function* () {
      const fs = yield* FileSystem.FileSystem;
      // Port and constants come from the wp-env config so both runtimes stay in step.
      const config = JSON.parse(
        yield* fs.readFileString(resolve(root, 'tests/wp-env/.wp-env.json')),
      );
      if (
        !Number.isInteger(config.port) ||
        config.port < 1 ||
        config.port > 65535
      )
        return yield* Effect.fail(
          new ToolingError({ message: 'Invalid wp-env port.' }),
        );
      yield* attemptPromise('Playground port must be free', () =>
        checkPort(config.port),
      );
      yield* dependencies;
      const session = randomUUID();
      const diagnostics = process.env.CI
        ? resolve(root, 'benchmarks/results')
        : undefined;
      if (diagnostics)
        yield* fs.makeDirectory(diagnostics, { recursive: true });
      const site = `http://localhost:${config.port}`;
      const mounts = [
        {
          hostPath: root,
          vfsPath: '/wordpress/wp-content/plugins/blockstudio',
        },
        {
          hostPath: resolve(root, 'tests/theme'),
          vfsPath: '/wordpress/wp-content/themes/theme',
        },
        ...(diagnostics
          ? [{ hostPath: diagnostics, vfsPath: '/playground-diagnostics' }]
          : []),
      ];
      yield* Effect.acquireRelease(
        attemptPromise('Boot Playground', () =>
          runCLI({
            command: 'server',
            ...runtimeOptions(),
            port: config.port,
            'site-url': site,
            mount: mounts,
            blueprint: blueprint(
              diagnostics
                ? {
                    ...config.config,
                    WP_DEBUG_LOG:
                      '/playground-diagnostics/php-playground-smoke.log',
                  }
                : config.config,
              session,
            ),
          }),
        ),
        (server) =>
          attemptPromise('Stop Playground', () =>
            server[Symbol.asyncDispose](),
          ).pipe(Effect.orDie),
      );

      const client = (yield* HttpClient.HttpClient).pipe(
        HttpClient.filterStatusOk,
      );
      const ready = Effect.gen(function* () {
        yield* Effect.gen(function* () {
          const response = yield* client
            .get(`${site}/wp-login.php`)
            .pipe(Effect.timeout('10 seconds'));
          yield* attempt('Verify Playground session', () =>
            requireSession(
              response.headers['x-blockstudio-playground-session'],
              session,
            ),
          );
          yield* response.text;
        }).pipe(
          Effect.retry({ schedule: Schedule.spaced('2 seconds'), times: 90 }),
          Effect.timeout('3 minutes'),
        );
        const setup = yield* client
          .post(`${site}/wp-json/blockstudio-test/v1/e2e/setup`, {
            headers: { 'X-Blockstudio-Playground-Session': session },
          })
          .pipe(Effect.timeout('5 minutes'));
        const result = yield* setup.json;
        yield* attempt('Verify E2E seed result', () => {
          if (
            !result ||
            typeof result !== 'object' ||
            !('success' in result) ||
            result.success !== true
          )
            throw new ToolingError({
              message: 'Playground did not seed successfully.',
            });
        });
        console.log(`Playground ready at ${site}`);
      });

      yield* ready;
      return yield* work;
    }),
  );

const test = (args: string[]) =>
  Effect.gen(function* () {
    const specs = yield* attempt('Validate Playground specs', () =>
      validateSpecs(args),
    );
    return yield* playground(
      Effect.gen(function* () {
        const { exitCode } = yield* command(
          resolve(root, 'node_modules/.bin/playwright'),
          ['test', '--config=playwright.wp-env.config.ts', ...specs],
          { cwd: root, inherit: true, check: false },
        );
        if (exitCode !== 0)
          return yield* Effect.fail(
            new ToolingError({ message: 'Playwright failed.', exitCode }),
          );
      }),
    );
  });

if (import.meta.main) {
  const [action = 'start', ...args] = process.argv.slice(2);
  main(
    action === 'start' && args.length === 0
      ? playground(Effect.never)
      : action === 'test'
        ? test(args)
        : Effect.fail(
            new ToolingError({
              message: `Unknown playground operation: ${action}`,
              exitCode: 64,
            }),
          ),
  );
}
