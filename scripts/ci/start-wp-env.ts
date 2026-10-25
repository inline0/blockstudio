import { Effect } from 'effect';
import { fileURLToPath } from 'node:url';
import { command, CommandFailed } from '../lib/command.ts';
import { main, ToolingError } from '../lib/runtime.ts';

const commands: Record<string, string[]> = {
  main: ['run', 'wp-env:start'],
  ui: ['--prefix', 'tests/ui', 'run', 'wp-env:start'],
  empty: ['run', 'wp-env:empty:start'],
  ...Object.fromEntries(
    ['plugin', 'mu-plugin', 'composer-theme', 'composer-plugin'].map((name) => [
      name,
      ['run', `wp-env:implementations:${name}:start`],
    ]),
  ),
};

export const setup = (environment: string) =>
  Effect.gen(function* () {
    const args = commands[environment];
    if (!args)
      return yield* Effect.fail(
        new ToolingError({
          message: `Unknown wp-env environment: ${environment}`,
          exitCode: 64,
        }),
      );
    const start = command('npm', args, {
      cwd: fileURLToPath(new URL('../..', import.meta.url)),
      tee: true,
    });
    // Retry only the known database initialization race, not general setup failures.
    yield* start.pipe(
      Effect.catch((error) => {
        if (
          !(error instanceof CommandFailed) ||
          !`${error.stdout}\n${error.stderr}`.includes(
            'Error establishing a database connection',
          )
        ) {
          return Effect.fail(error);
        }
        return Effect.gen(function* () {
          console.error(
            'Retrying wp-env setup once after database initialization.',
          );
          return yield* start;
        });
      }),
    );
  });

if (import.meta.main) main(setup(process.argv[2] ?? 'main'));
