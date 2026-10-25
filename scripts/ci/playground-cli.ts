import { Effect } from 'effect';
import { attemptPromise, main, ToolingError } from '../lib/runtime.ts';

if (import.meta.main)
  main(
    attemptPromise('Playground CLI transport', async () => {
      const url = process.env.BLOCKSTUDIO_PLAYGROUND_BRIDGE;
      const token = process.env.BLOCKSTUDIO_PLAYGROUND_TOKEN;
      if (!url || !token) throw new Error('Missing private Playground bridge.');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          authorization: `Bearer ${token}`,
          'content-type': 'application/json',
        },
        body: JSON.stringify({ command: JSON.parse(process.argv[2]) }),
        signal: AbortSignal.timeout(120_000),
      });
      if (!response.ok) throw new Error(await response.text());
      const result = (await response.json()) as {
        stdout: string;
        stderr: string;
        status: number;
      };
      process.stdout.write(result.stdout);
      process.stderr.write(result.stderr);
      return result.status;
    }).pipe(
      Effect.flatMap((status) =>
        status === 0
          ? Effect.void
          : Effect.fail(
              new ToolingError({ message: 'WP-CLI failed.', exitCode: status }),
            ),
      ),
    ),
  );
