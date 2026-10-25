import { Cause, Effect } from 'effect';

export const recordSuiteOutcome = <A, E, R>(
  work: Effect.Effect<A, E, R>,
  report: Record<string, unknown>,
) =>
  Effect.scoped(work).pipe(
    Effect.tap(() =>
      Effect.sync(() => {
        report.success = true;
      }),
    ),
    Effect.tapCause((cause) =>
      Effect.sync(() => {
        report.success = false;
        report.error = Cause.pretty(cause);
      }),
    ),
  );
