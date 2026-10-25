# CI Runtime Benchmark

Baseline: `7.7.9` (`533a9a57d93e9ac89f774c887293f047ea2fa808`).
Experiment branch: `ci-playground-benchmark`. No merge or release is included.

## Decision

Reject a complete Playground migration. Adopt a limited hybrid candidate:

- Native wp-env for PHPUnit, all four main E2E shards, UI regressions and
  empty-theme live-filesystem coverage.
- Playground for the four loading-mode probes and the existing optional
  contributor smoke. Native distribution installation tests remain unchanged.
- The CI contributor smoke explicitly uses the same PHP 8.3 baseline as the
  native suites. Its CLI flags and blueprint agree on versions, six workers are
  explicit, and PHP failure logs persist as artifacts. The developer default
  remains configurable; native PHP 8.4 distribution checks are retained.
- Pin wp-env 11.3.0 and explicitly set `testsEnvironment: false`.
  Docker still enables the extra instance by default. All suites use
  `cli`, not `tests-cli`; this removes no unit/E2E tests.
- Balance whole E2E files by measured duration. Discover new files
  automatically; preserve serial groups, assertions, retries and timeouts.
- Use fresh per-job npm download caches without archive restoration.
  Share Composer dependencies through a same-run artifact. Reuse suitable
  preinstalled PHP/Composer with version/extension checks and an installer fallback.

Self-hosted labels and runner capacity are unchanged. All runtime/browser
execution happens in GitHub CI, not locally. No product code, additional
test exclusions, runner infrastructure or release policy is changed.

## Paired Runtime Results

Initial samples use the same product/test commit `074a898d`, WordPress 7.1.3,
PHP 8.3, the existing two-slot pool, wp-env 10.39.0 and Playground CLI 3.1.60.
Native PHP is 8.3.36 with MariaDB/`wpdb`; Playground is 8.3.33 with
`WP_SQLite_DB` and six PHP workers. PHP workers are not GitHub runners.

Seconds, rounded. Cycle includes runtime preparation, Composer-mode copying,
boot, seed, probes, SEO installation when applicable, tests and shutdown.
GitHub toolchain/dependency setup and queue time are excluded here and measured
separately below. These are individual samples, not medians.

| Suite | Native Cycle | Playground Cycle | Outcome |
| --- | ---: | ---: | --- |
| PHP unit | 445 | 155 | Native: 2,459 tests, 9,671 assertions, one existing skip. Playground: 6 errors, 26 failures, one skip. |
| E2E shard 1 | 2,287 | Incomplete | Native: 903 passed. Playground hit the 60-minute job limit after more than 52 minutes in its test phase. |
| E2E shard 2 | 1,141 | 1,049 | Same 1,001 tests passed without retries. |
| E2E shard 3 | 540 | 463 | Same 774 tests passed without retries. |
| E2E shard 4 | 1,403 | 1,807 | Same 846 tests, including one flaky test on each runtime. |
| UI | 381 | 282 | Native: 232 passed, one screenshot-capture protocol error. Playground: 233 passed. See clean native repeat below. |
| Empty theme | 292 | 394 | Native: six passed. Playground: two passed, one failed, one flaky, two not reached. |
| Plugin loading | 244 | 16 | Both passed. |
| MU-plugin loading | 141 | 14 | Both passed. |
| Composer-theme loading | 266 | 28 | Both passed, including the existing runtime probe. |
| Composer-plugin loading | 357 | 37 | Both passed. |

Sources: [unit and shard 3](https://github.com/inline0/blockstudio/actions/runs/40190726710),
[remaining nine suite pairs](https://github.com/inline0/blockstudio/actions/runs/40192644770).
JSON/JUnit reports, logs and failure artifacts are attached. The timed-out job's
annotation confirms the one-hour limit; it did not produce a final JSON report.

Browser execution is slower on Playground: shard 2 takes 1,011s versus 693s;
shard 3 takes 421s versus 307s; shard 4 takes 1,769s versus 1,056s. Startup
savings produce only 8.0% and 14.4% complete-cycle gains in shards 2 and 3,
and a loss in shard 4. Shard 2's native boot includes the existing cold-database
retry against `tests-mysql`, so its startup delta is not a clean backend-only
comparison. That unused second database is removed in the candidate.

The four loading-mode cycles total 95s instead of 1,007s, a 90.6% reduction
against the original native baseline. This is the useful Playground result,
not evidence that the complete suite should move to WASM.

### Coverage And Failures

The native main baseline covers 3,524 unique test IDs in 193 files. Completed
paired reports have identical test IDs for shards 2, 3 and 4, UI and empty-theme
suites. No full-main parity or speedup is claimed for the incomplete shard 1.

WASM unit failures involve child PHP processes, OPcache, locking, timestamp
and source-watch semantics, filesystem invalidation and related native coverage.
Its failures reduce assertions to 9,582, so the 155s result is not a valid
replacement. Empty-theme live filesystem/SSE updates also fail.

The long Playground shard additionally encounters a registry fixture whose
internal HTTP URLs assume Docker's `localhost:80`, and later frontend/attribute
failures. These are not proof of production plugin regressions, nor a clean
full-shard performance measurement. They are additional migration work. The
native-only unit coverage already rules out complete replacement.

The inline-limit cache-payload test retries on both runtimes in shard 4. The
initial native UI failure is Chromium's screenshot-capture protocol error, not
a visual assertion failure. It is retained in the evidence, not counted as a
successful speed sample.

One-worker Playground boot fails with SCSS logger stderr errors on PHP 8.3
and 8.4; six workers boot successfully. Initial CLI-stream collection and
cold-database harness faults were fixed before the paired run. Diagnostic
runs are excluded. No tests were skipped or weakened, and no asset compilers
were disabled to make Playground pass.

## Native Driver Controls

The [11.3.0 control](https://github.com/inline0/blockstudio/actions/runs/40198030374)
retains the default extra test environment. The [explicit single-environment
control](https://github.com/inline0/blockstudio/actions/runs/40206308363) disables it.

| Configuration | Suite | Boot/Seed | Tests | Stop | Cycle |
| --- | --- | ---: | ---: | ---: | ---: |
| 10.39.0, default | Unit | 271 | 152 | 19 | 445 |
| 11.3.0, default | Unit | 131 | 77 | 19 | 229 |
| 10.39.0, default | Shard 3 | 210 | 307 | 13 | 540 |
| 11.3.0, default | Shard 3 | 98 | 278 | 117 | 502 |
| 11.3.0, single environment | Unit | 156 | 54 | 14 | 228 |
| 11.3.0, single environment | UI | 122 | 84 | 16 | 231 |

All native unit controls preserve 2,459 tests, 9,671 assertions and the same
existing skip. The shard-3 control preserves all 774 IDs. The clean UI repeat
passes all 233 tests with no retries, versus Playground's 282s cycle/262s tests.

Startup, execution and shutdown variance is substantial; the 117s shutdown
does not recur in the later controls. Do not attribute all observed improvement
to a single driver change. The full candidate gate validates the combined setup.

## Dependency Setup

[First and repeat samples](https://github.com/inline0/blockstudio/actions/runs/40196292338)
use the same lockfile. The [fresh-cache probe](https://github.com/inline0/blockstudio/actions/runs/40203290878)
explicitly checks out the same `074a898d` revision, not newer dependencies.

Seconds below sum tool/cache setup, `npm ci` and cache saving. Checkout,
inventory, cache-size measurement and queue time are not included.

| Configuration | Sample | Setup/Restore | npm ci | Save | Total |
| --- | --- | ---: | ---: | ---: | ---: |
| Legacy npm archive | First | 123 | 45 | 0 | 168 |
| Legacy npm archive | Repeat | 87 | 25 | 0 | 112 |
| No archive, existing runner cache | First | 1 | 72 | 0 | 73 |
| No archive, existing runner cache | Repeat | 0 | 52 | 0 | 52 |
| Lockfile-scoped archive | Cold | 0 | 35 | 11 | 46 |
| Lockfile-scoped archive | Warm | 45 | 54 | 0 | 99 |
| Fresh isolated cache, no archive | First | 0 | 49 | 0 | 49 |

Legacy archive: about 1,350 MiB. Scoped archive: 389 MiB. Existing runner npm
cache: about 3.2 GiB. Fresh install: about 435 MiB. Even the smaller archive
costs more to restore than its measured installation benefit. A per-job fresh
cache still lets installation retries reuse already downloaded packages.

The runner already has PHP 8.4.27, Composer 2.10.3 and cached Node binaries.
Node needs PATH setup. PHP is executable before setup. The candidate validates
PHP versions/extensions before avoiding the redundant installer, rather than
assuming every runner has the same image.

## Sharding And Final Validation

Original native main test phases are 2,030s / 693s / 307s / 1,056s. Count-based
sharding puts five parameterized files in shard 3 and 114 files in shard 1.
Recorded file spans include setup between tests, not only assertion durations.

`scripts/ci/e2e-file-durations.json` records source commit/run IDs. Each of the
four candidate groups contains 48-49 files with about 1,004s of recorded work.
This is a scheduling estimate, not an achieved full-CI duration. Focused unit
tests verify deterministic assignment, automatic inclusion of new files,
complete/disjoint coverage and rejection of empty shards.

The complete candidate gate retains all 16 main-CI jobs and the unchanged five
distribution checks. Reports include runtime phases, JUnit, browser JSON and
rerun-safe artifacts. Final validation compares the actual main-E2E union to
all 3,524 baseline IDs, not just counts or smoke tests. The final handoff links
the full gate and its measured duration.

Suite success is recorded after teardown. Focused tests prove that test errors,
cleanup errors and both together remain failures rather than false green runs.

Separate workflow wall time, aggregate runner time, queue contention, setup
and runtime savings. The historical full 7.7.9 run contains a retried unit job
and is not a clean before/after workflow-wall benchmark. Do not present a
scheduling estimate or a failed/incomplete WASM run as a measured full-CI win.
