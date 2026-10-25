import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { resolve } from 'node:path';

test('the private CLI transport preserves output, argument boundaries and nonzero exit codes', async () => {
  const received: unknown[] = [];
  const server = createServer(async (request, response) => {
    assert.equal(request.headers.authorization, 'Bearer test-session');
    const chunks: Buffer[] = [];
    for await (const chunk of request) chunks.push(chunk);
    received.push(JSON.parse(Buffer.concat(chunks).toString('utf8')));
    response.writeHead(200, { 'content-type': 'application/json' }).end(
      JSON.stringify({
        stdout: '{"warning":true}\n',
        stderr: 'diagnostic\n',
        status: 7,
      }),
    );
  });
  await new Promise<void>((done) => server.listen(0, '127.0.0.1', done));
  try {
    const address = server.address();
    assert.ok(address && typeof address !== 'string');
    const args = [
      'bs',
      'blocks',
      'render',
      '--attributes={"text":"Hello world"}',
    ];
    await assert.rejects(
      promisify(execFile)(
        process.execPath,
        [resolve('scripts/ci/playground-cli.ts'), JSON.stringify(args)],
        {
          encoding: 'utf8',
          timeout: 10_000,
          env: {
            ...process.env,
            BLOCKSTUDIO_PLAYGROUND_BRIDGE: `http://127.0.0.1:${address.port}`,
            BLOCKSTUDIO_PLAYGROUND_TOKEN: 'test-session',
          },
        },
      ),
      (error: unknown) => {
        const result = error as {
          code: number;
          stdout: string;
          stderr: string;
        };
        assert.equal(result.code, 7);
        assert.equal(result.stdout, '{"warning":true}\n');
        assert.match(result.stderr, /diagnostic/);
        return true;
      },
    );
    assert.deepEqual(received, [{ command: args }]);
  } finally {
    server.closeAllConnections();
    await new Promise<void>((done) => server.close(() => done()));
  }
});
