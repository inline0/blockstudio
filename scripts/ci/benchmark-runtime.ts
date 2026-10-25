import { Effect, FileSystem } from 'effect';
import * as PlaygroundCli from '@wp-playground/cli';
import { createServer, type Server } from 'node:http';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { blueprint, checkPort, requireSession } from '../playground.ts';
import { command } from '../lib/command.ts';
import { attemptPromise, main, ToolingError } from '../lib/runtime.ts';
import { wpEnv } from '../wp-env.ts';
import {
  benchmarkOptions,
  playgroundArguments,
  probeValue,
} from './benchmark-options.ts';

const root = resolve(import.meta.dirname, '../..');
interface RunCLIServer {
  serverUrl: string;
  playground: {
    cli(
      argv: string[],
      options?: { env?: Record<string, string> },
    ): Promise<{
      stdout: ReadableStream<Uint8Array>;
      stderr: ReadableStream<Uint8Array>;
      exitCode: Promise<number>;
    }>;
  };
  [Symbol.asyncDispose](): Promise<void>;
}
// 3.1.60's declarations use extensionless ESM exports, which NodeNext cannot resolve.
const { runCLI } = PlaygroundCli as unknown as {
  runCLI(args: Record<string, unknown>): Promise<RunCLIServer>;
};
const { runtime, suite } = benchmarkOptions(process.argv.slice(2));
const mainSuite = suite.startsWith('e2e-') || suite === 'unit';
const mode = mainSuite ? 'main' : suite;
const directory =
  mode === 'main'
    ? 'tests/wp-env'
    : mode === 'ui'
      ? 'tests/ui'
      : mode === 'empty'
        ? 'tests/wp-env-empty'
        : `tests/implementations/${mode}`;
const config = JSON.parse(
  readFileSync(resolve(root, directory, '.wp-env.json'), 'utf8'),
);
const report: Record<string, unknown> = {
  runtime,
  suite,
  commit: process.env.GITHUB_SHA,
  run: process.env.GITHUB_RUN_ID,
  runner: process.env.RUNNER_NAME,
  startedAt: new Date().toISOString(),
  phases: [] as { name: string; seconds: number }[],
};
const phases = report.phases as { name: string; seconds: number }[];
const started = performance.now();
const phase = <A, E, R>(name: string, work: Effect.Effect<A, E, R>) =>
  Effect.suspend(() => {
    const start = performance.now();
    console.log(`BENCHMARK_START ${name}`);
    return work.pipe(
      Effect.ensuring(
        Effect.sync(() => {
          const entry = { name, seconds: (performance.now() - start) / 1000 };
          phases.push(entry);
          console.log(`BENCHMARK_PHASE ${JSON.stringify(entry)}`);
        }),
      ),
    );
  });
const run = (
  executable: string,
  args: string[],
  env: Record<string, string> = {},
  check = true,
) => command(executable, args, { cwd: root, env, inherit: true, check });

async function cli(
  server: RunCLIServer,
  args: string[],
  env: Record<string, string> = {},
  tee = false,
) {
  const response = await server.playground.cli(
    ['/internal/shared/bin/php', ...args],
    { env },
  );
  const consume = async (
    stream: ReadableStream<Uint8Array>,
    destination: NodeJS.WriteStream,
  ) => {
    const chunks: Uint8Array[] = [];
    const reader = stream.getReader();
    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        chunks.push(value);
        if (tee) destination.write(value);
      }
    } finally {
      reader.releaseLock();
    }
    return Buffer.concat(chunks).toString('utf8');
  };
  const [stdout, stderr, status] = await Promise.all([
    consume(response.stdout, process.stdout),
    consume(response.stderr, process.stderr),
    response.exitCode,
  ]);
  return { stdout, stderr, status };
}

function cliBridge(
  server: RunCLIServer,
  token: string,
): Promise<{ server: Server; url: string }> {
  return new Promise((resolveBridge, reject) => {
    const bridge = createServer(async (request, response) => {
      if (
        request.method !== 'POST' ||
        request.headers.authorization !== `Bearer ${token}`
      ) {
        response.writeHead(403).end();
        return;
      }
      try {
        const chunks: Buffer[] = [];
        let length = 0;
        for await (const chunk of request) {
          length += chunk.length;
          if (length > 1024 * 1024) throw new Error('CLI request too large.');
          chunks.push(chunk);
        }
        const { command: value } = JSON.parse(
          Buffer.concat(chunks).toString('utf8'),
        );
        if (
          typeof value !== 'string' &&
          !(Array.isArray(value) && value.every((x) => typeof x === 'string'))
        )
          throw new Error('Invalid CLI arguments.');
        const result = await cli(server, [
          '/tmp/wp-cli.phar',
          '--path=/wordpress',
          ...playgroundArguments(value),
        ]);
        response
          .writeHead(200, { 'content-type': 'application/json' })
          .end(JSON.stringify(result));
      } catch (error) {
        response.writeHead(500).end(String(error));
      }
    });
    bridge.once('error', reject);
    bridge.listen(0, '127.0.0.1', () => {
      const address = bridge.address();
      if (!address || typeof address === 'string')
        return reject(new Error('Bridge did not bind.'));
      resolveBridge({
        server: bridge,
        url: `http://127.0.0.1:${address.port}`,
      });
    });
  });
}

const prepareComposerMode = Effect.gen(function* () {
  if (mode === 'composer-theme' || mode === 'composer-plugin')
    yield* run('composer', [
      'install',
      '--no-dev',
      '--no-interaction',
      `--working-dir=${directory}/${mode === 'composer-theme' ? 'theme' : 'plugin'}`,
    ]);
});

const playground = Effect.gen(function* () {
  yield* attemptPromise('Check benchmark port', () => checkPort(config.port));
  yield* prepareComposerMode;
  const session = randomUUID();
  const theme = mainSuite
    ? 'tests/theme'
    : mode === 'empty'
      ? 'tests/theme-empty'
      : `${directory}/theme`;
  const pluginPath = '/wordpress/wp-content/plugins/blockstudio';
  const mounts = [
    {
      hostPath: resolve(root, theme),
      vfsPath: '/wordpress/wp-content/themes/theme',
    },
  ];
  if (mode === 'mu-plugin') {
    mounts.push({
      hostPath: resolve(root, directory, 'mu-plugins'),
      vfsPath: '/wordpress/wp-content/mu-plugins',
    });
    mounts.push({
      hostPath: root,
      vfsPath: '/wordpress/wp-content/mu-plugins/blockstudio',
    });
  } else if (mode === 'composer-plugin') {
    mounts.push({
      hostPath: resolve(root, directory, 'plugin'),
      vfsPath: '/wordpress/wp-content/plugins/plugin',
    });
  } else if (mode !== 'composer-theme')
    mounts.push({ hostPath: root, vfsPath: pluginPath });
  const declaration = blueprint(config.config, session);
  const steps = (declaration.steps ?? []).filter(
    (step) =>
      step && typeof step === 'object' && step.step !== 'activatePlugin',
  );
  if (mode !== 'mu-plugin' && mode !== 'composer-theme')
    steps.splice(2, 0, {
      step: 'activatePlugin',
      pluginPath:
        mode === 'composer-plugin'
          ? 'plugin/test-plugin.php'
          : 'blockstudio/blockstudio.php',
    });
  const server = yield* Effect.acquireRelease(
    phase(
      'boot',
      attemptPromise('Boot Playground', () =>
        runCLI({
          command: 'server',
          php: '8.3',
          wp: 'latest',
          port: config.port,
          'site-url': `http://localhost:${config.port}`,
          workers: Number(process.env.PLAYGROUND_WORKERS || 1),
          mount: mounts,
          blueprint: {
            ...declaration,
            preferredVersions: { php: '8.3', wp: 'latest' },
            extraLibraries: ['wp-cli'],
            steps,
          },
        }),
      ),
    ),
    (instance) =>
      phase(
        'stop',
        attemptPromise('Stop Playground', () =>
          instance[Symbol.asyncDispose](),
        ),
      ).pipe(Effect.ignore),
  );
  const bridge = yield* Effect.acquireRelease(
    attemptPromise('Create CLI bridge', () => cliBridge(server, session)),
    (instance) =>
      Effect.sync(() => {
        instance.server.closeAllConnections();
        instance.server.close();
      }),
  );
  const env = {
    BLOCKSTUDIO_PLAYGROUND_BRIDGE: bridge.url,
    BLOCKSTUDIO_PLAYGROUND_TOKEN: session,
  };
  const wp = (args: string[]) =>
    attemptPromise('Playground WP-CLI', async () => {
      const result = await cli(
        server,
        ['/tmp/wp-cli.phar', '--path=/wordpress', ...args],
        {},
        true,
      );
      if (result.status)
        throw new Error(`WP-CLI exited ${result.status}: ${result.stderr}`);
      return result.stdout;
    });
  yield* phase(
    'environment',
    Effect.gen(function* () {
      report.environment = JSON.parse(
        probeValue(
          yield* wp([
            'eval',
            'global $wpdb; echo "\nBLOCKSTUDIO_BENCHMARK_ENV=" . wp_json_encode(array("php"=>PHP_VERSION,"wordpress"=>get_bloginfo("version"),"dbClass"=>get_class($wpdb),"mysqli"=>extension_loaded("mysqli"),"sqlite"=>extension_loaded("pdo_sqlite"))) . "\n";',
          ]),
          'ENV',
        ),
      );
    }),
  );
  if (mainSuite)
    yield* phase(
      'seed',
      attemptPromise('Seed Playground', async () => {
        const ready = await fetch(`${server.serverUrl}/wp-login.php`);
        requireSession(
          ready.headers.get('x-blockstudio-playground-session') ?? undefined,
          session,
        );
        await ready.arrayBuffer();
        const response = await fetch(
          `${server.serverUrl}/wp-json/blockstudio-test/v1/e2e/setup`,
          {
            method: 'POST',
            headers: { 'X-Blockstudio-Playground-Session': session },
            signal: AbortSignal.timeout(300_000),
          },
        );
        if (
          !response.ok ||
          !((await response.json()) as { success?: boolean }).success
        )
          throw new Error('Seed failed.');
      }),
    );
  if (suite === 'ui') yield* phase('seed', wp(['bs', 'pages', 'sync']));
  if (suite.startsWith('e2e-'))
    yield* phase(
      'seo-install',
      wp(['plugin', 'install', 'wordpress-seo', 'seo-by-rank-math']),
    );
  if (suite === 'unit') {
    const result = yield* phase(
      'tests',
      attemptPromise('Playground PHPUnit', () =>
        cli(
          server,
          [
            '-d',
            'memory_limit=512M',
            `${pluginPath}/vendor/bin/phpunit`,
            '--configuration',
            `${pluginPath}/phpunit.xml`,
            '--testdox',
            '--display-errors',
            '--display-warnings',
            '--log-junit',
            `${pluginPath}/benchmarks/results/unit-playground.xml`,
          ],
          { BLOCKSTUDIO_WP_LOAD: '/wordpress/wp-load.php' },
          true,
        ),
      ),
    );
    if (result.status)
      return yield* Effect.fail(
        new ToolingError({
          message: 'Playground PHPUnit failed.',
          exitCode: result.status,
        }),
      );
  } else if (suite.startsWith('e2e-') || suite === 'ui' || suite === 'empty') {
    yield* browserTests(env);
  } else {
    yield* phase(
      'verify',
      wp([
        'eval',
        'if (!defined("BLOCKSTUDIO_VERSION")) { exit(1); } echo BLOCKSTUDIO_VERSION;',
      ]),
    );
    if (suite === 'composer-theme')
      yield* phase(
        'probe',
        wp([
          'eval-file',
          '/wordpress/wp-content/themes/theme/runtime-probe.php',
        ]),
      );
  }
});

const browserTests = (env: Record<string, string> = {}) =>
  Effect.gen(function* () {
    const args =
      suite === 'ui'
        ? [
            '--config=tests/ui/playwright.config.ts',
            'ui/(select[^/]*|card-assets|showcase|controlled-bindings|number-field|toggle|collapsible|toolbar|catalog)\\.spec\\.ts$',
          ]
        : suite === 'empty'
          ? ['--config=playwright.wp-env.empty.config.ts']
          : [
              '--config=playwright.wp-env.config.ts',
              `--shard=${suite.slice(4)}/4`,
            ];
    const result = yield* phase(
      'tests',
      run(
        resolve(root, 'node_modules/.bin/playwright'),
        ['test', ...args, '--reporter=list,json'],
        {
          ...env,
          PLAYWRIGHT_JSON_OUTPUT_NAME: resolve(
            root,
            `benchmarks/results/${runtime}-${suite}-playwright.json`,
          ),
        },
        false,
      ),
    );
    if (result.exitCode)
      return yield* Effect.fail(
        new ToolingError({
          message: 'Benchmark browser tests failed.',
          exitCode: result.exitCode,
        }),
      );
  });

const native = Effect.gen(function* () {
  yield* Effect.addFinalizer(() =>
    phase(
      'stop',
      suite === 'ui'
        ? run('npm', ['--prefix', 'tests/ui', 'run', 'wp-env:stop'], {
            COMPOSE_PROJECT_NAME: 'blockstudio-ui',
          })
        : wpEnv(mode, 'stop'),
    ).pipe(Effect.ignore),
  );
  yield* phase(
    'boot-and-seed',
    suite === 'ui'
      ? run('npm', ['--prefix', 'tests/ui', 'run', 'wp-env:start'], {
          COMPOSE_PROJECT_NAME: 'blockstudio-ui',
        })
      : wpEnv(mode, 'start'),
  );
  const cwd = resolve(root, directory);
  const wp = (args: string[], inherit = true) =>
    command(
      resolve(root, 'node_modules/.bin/wp-env'),
      ['run', 'cli', '--', 'wp', ...args],
      {
        cwd,
        inherit,
        env: {
          COMPOSE_PROJECT_NAME:
            mode === 'main' ? 'blockstudio' : `blockstudio-${mode}`,
        },
      },
    );
  const info = yield* phase(
    'environment',
    wp(
      [
        'eval',
        'global $wpdb; echo "\nBLOCKSTUDIO_BENCHMARK_ENV=" . wp_json_encode(array("php"=>PHP_VERSION,"wordpress"=>get_bloginfo("version"),"dbClass"=>get_class($wpdb),"mysqli"=>extension_loaded("mysqli"),"sqlite"=>extension_loaded("pdo_sqlite"))) . "\n";',
      ],
      false,
    ),
  );
  report.environment = JSON.parse(probeValue(info.stdout, 'ENV'));
  if (suite.startsWith('e2e-'))
    yield* phase(
      'seo-install',
      wp(['plugin', 'install', 'wordpress-seo', 'seo-by-rank-math']),
    );
  if (suite === 'ui')
    yield* phase(
      'seed',
      Effect.gen(function* () {
        yield* wp(['rewrite', 'structure', '/%postname%/', '--hard']);
        yield* wp(['bs', 'pages', 'sync']);
      }),
    );
  if (suite === 'unit') {
    const result = yield* wp(
      [
        'eval',
        'echo "\nBLOCKSTUDIO_BENCHMARK_PLUGIN=" . BLOCKSTUDIO_DIR . "\n";',
      ],
      false,
    );
    const plugin = probeValue(result.stdout, 'PLUGIN');
    yield* phase(
      'tests',
      command(
        resolve(root, 'node_modules/.bin/wp-env'),
        [
          'run',
          'cli',
          `--env-cwd=${plugin}`,
          'vendor/bin/phpunit',
          '--testdox',
          '--display-errors',
          '--display-warnings',
          '--log-junit',
          `${plugin}/benchmarks/results/unit-wp-env.xml`,
        ],
        { cwd, inherit: true, env: { COMPOSE_PROJECT_NAME: 'blockstudio' } },
      ),
    );
  } else if (suite.startsWith('e2e-') || suite === 'ui' || suite === 'empty')
    yield* browserTests();
  else yield* phase('verify', wpEnv(mode, 'verify'));
});

if (import.meta.main)
  main(
    Effect.scoped(
      Effect.gen(function* () {
        if (!process.env.CI)
          return yield* Effect.fail(
            new ToolingError({ message: 'Runtime benchmarks are CI-only.' }),
          );
        const fs = yield* FileSystem.FileSystem;
        yield* fs.makeDirectory(resolve(root, 'benchmarks/results'), {
          recursive: true,
        });
        yield* runtime === 'playground' ? playground : native;
        report.success = true;
      }),
    ).pipe(
      Effect.tapError((error) =>
        Effect.sync(() => {
          report.success = false;
          report.error = String(error);
        }),
      ),
      Effect.ensuring(
        Effect.gen(function* () {
          const fs = yield* FileSystem.FileSystem;
          report.totalSeconds = (performance.now() - started) / 1000;
          report.completedAt = new Date().toISOString();
          yield* fs.writeFileString(
            resolve(root, `benchmarks/results/${runtime}-${suite}.json`),
            `${JSON.stringify(report, null, 2)}\n`,
          );
          console.log(`BENCHMARK_RESULT ${JSON.stringify(report)}`);
        }).pipe(Effect.orDie),
      ),
    ),
  );
