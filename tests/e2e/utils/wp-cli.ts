import { execFileSync, execSync } from 'node:child_process';
import { resolve } from 'node:path';

export function wpCommand(
  command: string | string[],
  timeout = 30_000,
): string {
  if (process.env.BLOCKSTUDIO_PLAYGROUND_BRIDGE) {
    return execFileSync(
      process.execPath,
      [resolve('scripts/ci/playground-cli.ts'), JSON.stringify(command)],
      { encoding: 'utf8', timeout },
    );
  }
  if (typeof command === 'string')
    return execSync(`cd tests/wp-env && npx wp-env run cli -- wp ${command}`, {
      encoding: 'utf8',
      timeout,
    });
  return execFileSync(
    process.execPath,
    [
      resolve('node_modules/@wordpress/env/bin/wp-env'),
      'run',
      'cli',
      '--',
      'wp',
      ...command,
    ],
    { cwd: resolve('tests/wp-env'), encoding: 'utf8', timeout },
  );
}
