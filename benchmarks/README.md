# CI Runtime Benchmark

Branch baseline: `7.7.9` at `533a9a57d93e9ac89f774c887293f047ea2fa808`.

This experiment compares the complete WordPress-dependent CI coverage on
wp-env and WordPress Playground. It does not change plugin behavior or remove
test assertions. All runtime and browser execution happens in GitHub CI on the
existing self-hosted Blockstudio runners. Runner capacity is unchanged.

The first comparison runs both runtimes against the same commit, main E2E
shards, unit suite, UI regressions, empty theme and four installation modes.
The existing Playwright configuration, timeouts and retry policy are retained.
The Playground CLI/SEO transport runs real WP-CLI commands against the same
disposable database; CLI exit codes and stdout remain observable to assertions.
The PHPUnit bootstrap accepts an alternate WordPress root for the WASM runtime.

Evidence includes actual PHP/WordPress versions, per-phase wall time, complete
Playwright JSON results, PHPUnit JUnit results, logs and failure artifacts.
Setup and teardown are recorded, not excluded from the runtime comparison.
GitHub job setup, queue time, aggregate runner time and complete-workflow time
will be reported separately. Cold/warm download effects and retries will be
identified rather than presented as runtime speedups.

The workflow is isolated to this branch. It is not a replacement release gate
until coverage, failures and timings have been compared. Later measurements
will validate the selected all-Playground, hybrid or native configuration.

## Completed Measurements

These are first paired samples, not final medians. All samples below use the
same product/test commit (`074a898d`), WordPress 7.1.3, PHP 8.3 and the existing
two-slot self-hosted runner pool. Native PHP is 8.3.36; Playground's PHP build
is 8.3.33. Playground uses six PHP workers, not six GitHub runners.

Times are seconds, rounded to the nearest second. The cycle includes all
runtime preparation, probes, SEO installation where applicable, tests and
shutdown. GitHub action setup and queue time are not included in this table.

| Suite | Runtime | Boot/Seed | Test Phase | Full Cycle | Result |
| --- | --- | ---: | ---: | ---: | --- |
| Unit | wp-env | 271 | 152 | 445 | 2,459 tests; no failures |
| Unit | Playground | 27 | 125 | 155 | 6 errors, 26 failures, 1 skipped |
| E2E shard 3 | wp-env | 210 | 307 | 540 | 774 passed, no retries |
| E2E shard 3 | Playground | 27 | 421 | 463 | Same 774 test IDs passed, no retries |
| E2E shard 2 | wp-env | 420 | 693 | 1,141 | 1,001 passed, no retries |
| E2E shard 2 | Playground | 25 | 1,011 | 1,049 | 1,001 passed, no retries |

Sources: [unit and shard 3 comparison](https://github.com/inline0/blockstudio/actions/runs/40190726710),
[remaining suite comparison](https://github.com/inline0/blockstudio/actions/runs/40192644770).
Raw JSON/JUnit reports, logs and failure artifacts are attached to those runs.
Generated local copies are ignored in Git.

The unit timing is not an acceptable replacement result: the Playground run
has fewer assertions because failures short-circuit tests. Native-process,
OPcache, locking, timestamp and filesystem tests must remain native. No test
assertions were weakened or skipped to make the Playground result green.

Playground's empty-theme run also fails live filesystem/SSE update coverage
(one failure, one flaky test, two tests not reached). Its CI screenshot shows
an unsupported block and a page failing to refresh after source changes.
That case is not eligible for migration without equivalent native coverage.

One-worker Playground boot failed with SCSS logger stderr errors on both PHP
8.3 and 8.4. The six-worker configuration boots successfully. Those startup
diagnostics are excluded from performance comparisons.

The remaining measurements, repeat samples, setup/cache comparison and final
adopted recommendation are still in progress.
