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

Results and the final recommendation will be added here after CI measurement.
Raw generated reports are uploaded as artifacts and ignored in Git.
