import { Effect, FileSystem, Schedule } from 'effect';
import * as HttpClient from 'effect/http/HttpClient';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { command } from './lib/command.ts';
import { main, ToolingError } from './lib/runtime.ts';

const root = resolve(import.meta.dirname, '..');
const php = process.env.PLAYGROUND_PHP ?? '8.4';
const wordpress = process.env.PLAYGROUND_WP ?? 'latest';

// Playground ignores --php and --wp as soon as a Blueprint is passed, so the versions live in the Blueprint.
export const blueprint = (constants: Record<string, unknown>) => ({
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  preferredVersions: { php, wp: wordpress },
  steps: [
    { step: 'defineWpConfigConsts', consts: constants },
    { step: 'activateTheme', themeFolderName: 'theme' },
    { step: 'activatePlugin', pluginPath: 'blockstudio/blockstudio.php' },
    {
      step: 'setSiteOptions',
      options: { permalink_structure: '/%postname%/' },
    },
  ],
});

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

const playground = <A, E, R>(inherit: boolean, work: Effect.Effect<A, E, R>) =>
  Effect.scoped(
    Effect.gen(function* () {
      yield* dependencies;
      const fs = yield* FileSystem.FileSystem;
      // Port and constants come from the wp-env config so both runtimes stay in step.
      const config = JSON.parse(
        yield* fs.readFileString(resolve(root, 'tests/wp-env/.wp-env.json')),
      );
      const directory = yield* fs.makeTempDirectoryScoped({
        prefix: 'blockstudio-playground-',
      });
      const file = resolve(directory, 'blueprint.json');
      yield* fs.writeFileString(file, JSON.stringify(blueprint(config.config)));

      const site = `http://localhost:${config.port}`;
      const server = command(
        resolve(root, 'node_modules/.bin/wp-playground-cli'),
        [
          'server',
          `--port=${config.port}`,
          `--site-url=${site}`,
          `--blueprint=${file}`,
          `--mount=${root}:/wordpress/wp-content/plugins/blockstudio`,
          `--mount=${resolve(root, 'tests/theme')}:/wordpress/wp-content/themes/theme`,
        ],
        { cwd: root, inherit },
      ).pipe(
        Effect.andThen(
          Effect.fail(new ToolingError({ message: 'Playground stopped.' })),
        ),
      );

      const client = (yield* HttpClient.HttpClient).pipe(
        HttpClient.filterStatusOk,
      );
      const ready = Effect.gen(function* () {
        yield* client
          .get(`${site}/wp-login.php`)
          .pipe(
            Effect.retry({ schedule: Schedule.spaced('2 seconds'), times: 90 }),
          );
        // The first request after boot can fail while Blockstudio builds its caches.
        yield* client
          .post(`${site}/wp-json/blockstudio-test/v1/e2e/setup`)
          .pipe(
            Effect.timeout('5 minutes'),
            Effect.retry({ schedule: Schedule.spaced('5 seconds'), times: 5 }),
          );
        console.log(`Playground ready at ${site}`);
      });

      return yield* Effect.raceFirst(server, ready.pipe(Effect.andThen(work)));
    }),
  );

const test = (args: string[]) =>
  playground(
    false,
    Effect.gen(function* () {
      const { exitCode } = yield* command(
        resolve(root, 'node_modules/.bin/playwright'),
        ['test', '--config=playwright.wp-env.config.ts', ...args],
        { cwd: root, inherit: true, check: false },
      );
      if (exitCode !== 0)
        return yield* Effect.fail(
          new ToolingError({ message: 'Playwright failed.', exitCode }),
        );
    }),
  );

if (import.meta.main) {
  const [action = 'start', ...args] = process.argv.slice(2);
  main(
    action === 'start'
      ? playground(true, Effect.never)
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
