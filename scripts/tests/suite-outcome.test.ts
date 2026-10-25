import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Cause, Effect } from 'effect';
import { recordSuiteOutcome } from '../ci/suite-outcome.ts';

test('suite success is recorded only after successful resource cleanup', async () => {
  const report: Record<string, unknown> = {};
  let closed = false;
  const work = Effect.acquireRelease(Effect.succeed('site'), () =>
    Effect.sync(() => {
      assert.equal(report.success, undefined);
      closed = true;
    }),
  );
  await Effect.runPromise(recordSuiteOutcome(work, report));
  assert.equal(closed, true);
  assert.equal(report.success, true);
});

for (const [testFailure, cleanupFailure] of [
  [true, false],
  [false, true],
  [true, true],
])
  test(`failed tests=${testFailure}, failed cleanup=${cleanupFailure} remain failures`, async () => {
    const report: Record<string, unknown> = {};
    const work = Effect.acquireRelease(Effect.succeed('site'), () =>
      cleanupFailure
        ? Effect.fail(new Error('Stop WordPress failed')).pipe(Effect.orDie)
        : Effect.void,
    ).pipe(
      Effect.andThen(
        testFailure ? Effect.fail(new Error('Tests failed')) : Effect.void,
      ),
    );
    const exit = await Effect.runPromiseExit(recordSuiteOutcome(work, report));
    assert.equal(exit._tag, 'Failure');
    if (exit._tag !== 'Failure') throw new Error('Expected a failing suite.');
    assert.equal(report.success, false);
    if (testFailure) assert.match(Cause.pretty(exit.cause), /Tests failed/);
    if (cleanupFailure)
      assert.match(String(report.error), /Stop WordPress failed/);
  });
