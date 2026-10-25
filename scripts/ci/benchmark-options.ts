import { parse } from 'shell-quote';

export const runtimes = ['wp-env', 'playground'] as const;
export const suites = [
  'e2e-1',
  'e2e-2',
  'e2e-3',
  'e2e-4',
  'unit',
  'ui',
  'empty',
  'plugin',
  'mu-plugin',
  'composer-theme',
  'composer-plugin',
] as const;
export type BenchmarkRuntime = (typeof runtimes)[number];
export type BenchmarkSuite = (typeof suites)[number];

export function benchmarkOptions(args: string[]) {
  if (
    !runtimes.includes(args[0] as BenchmarkRuntime) ||
    !suites.includes(args[1] as BenchmarkSuite)
  )
    throw new Error('Expected a benchmark runtime and suite.');
  return {
    runtime: args[0] as BenchmarkRuntime,
    suite: args[1] as BenchmarkSuite,
  };
}

export function playgroundArguments(command: string | string[]) {
  const args = typeof command === 'string' ? parse(command, {}) : command;
  if (!args.every((arg): arg is string => typeof arg === 'string'))
    throw new Error(
      'WP-CLI benchmark commands cannot contain shell operators.',
    );
  return args.map((arg) => arg.replaceAll('/var/www/html', '/wordpress'));
}

export function probeValue(output: string, key: string) {
  const marker = `BLOCKSTUDIO_BENCHMARK_${key}=`;
  const line = output.split('\n').find((entry) => entry.startsWith(marker));
  if (!line) throw new Error(`Missing benchmark probe: ${key}`);
  return line.slice(marker.length).trim();
}
