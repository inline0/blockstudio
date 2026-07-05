# 010: storh File Datastore Adoption (bs.db record backend)

## Summary

Blockstudio ships a developer-facing record store, **`bs.db`** (`Blockstudio\Db`
/ `Blockstudio\Database`), with five backends: `table` (MySQL), `meta` (post
meta), `post_type` (WP posts), and two *file* backends, `jsonc` and `sqlite`.
The `jsonc` backend is a **hand-rolled newline-delimited-JSON document store**:
one `db/<schema>.jsonc` file per collection, one JSON object per line.

It works, but it is the weakest link. Every create/update/delete does a
**full-file read-modify-write** under a single `LOCK_EX` truncating write
(`database.php:1608-1859`). That has a real **lost-update / truncation race**:
two concurrent writers each read the whole file, mutate in memory, and rewrite,
so one writer's records vanish. Every `list/get/filter/paginate/count` loads the
**entire file into memory**. There are no indexes, so all filtering is a full
scan. It does not scale and it is not concurrency-safe.

`storh` (`storh/storh` on Packagist, MIT, PHP ^8.2, **zero runtime deps** beyond
`ext-json`, authored under the same `inline0` org) is a file-first datastore
built for exactly this shape: a JSONC **`DocStore`** (one file per record, UUID-
tail sharded, atomic replace per record, secondary indexes, fluent query
builder, schema validation), an append-only **`SegmentedLog`**, and a durable
**`Queue`**. It publishes writes with temp-file + fsync + atomic rename and
recovers torn writes.

**This PRD proposes adopting `storh` as a new, first-class `bs.db` file backend
(`Storage_Type::Storh`)** that fixes the race, removes the whole-file memory
cost, and adds indexed queries, while **leaving the existing backends intact**.
The existing `jsonc` backend is kept for its one property `storh` deliberately
does not provide: a single, human-readable, **git-committable** file in the block
source tree (seed data, fixtures). `storh` serves the other half of `bs.db`'s
job, **runtime-mutable, concurrent, growing record sets** (form entries,
subscribers, user-generated data), which is where the current design is unsafe.

**Feasibility verdict: yes, with conditions.** Packaging, PHP version, the
php-scoper pipeline, and WordPress filesystem constraints all check out (see
[Feasibility](#feasibility)). The main conditions are: pin `storh` exactly (it
is pre-1.0), hide it behind a Blockstudio adapter seam, store data in the
writable uploads dir (not the source tree), and preserve `bs.db`'s public API and
record ids. **V1 is a deliberately narrow slice** (see [V1 Scope](#v1-scope));
build cache, asset caches, and content-sync are explicit non-goals.

## Background: what `bs.db` file storage does today

Public API `Blockstudio\Db` (`db.php:25`) and JS `bs.db(...)` expose fluent
`create / list / get / update / delete / paginate / count`. A schema
(`db/schema.php`) picks a backend via `Schema(storage: ...)`, resolved in
`Database::storage_type()` (`database.php:586-588`, default `table`). The two
file backends:

### `jsonc` (the target)

- **Path:** `jsonc_path()` (`database.php:702-707`) →
  `dirname($block_path) . '/db/' . $schema_name . '.jsonc'`, i.e. inside the
  block's own folder in the source tree (e.g.
  `.../blocks/newsletter/db/subscribers.jsonc`).
- **Format:** NDJSON, one `wp_json_encode` object per line; `//` comment lines
  and blanks skipped on read; integer ids assigned by line order when absent.
- **Read:** `jsonc_read()` (`:1608-1638`) `file_get_contents` + `explode("\n")`
  + per-line `json_decode`.
- **Write:** `jsonc_write()` (`:1648-1663`)
  `file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX)`.
- **Every mutation** (`jsonc_create` `:1785`, `jsonc_update` `:1812`,
  `jsonc_delete` `:1843`) is `jsonc_read()` → mutate array → `jsonc_write()`.
  Not append-only. Whole file rewritten each mutation, whole file loaded each
  read.
- **Concurrency:** only the advisory `LOCK_EX` flag on the truncating write; no
  read lock, no temp-file+rename. **Lost-update race under concurrent writers.**

### `sqlite`

- **Path:** `sqlite_path()` (`:1879-1884`) `dirname($block_path) . '/db/' .
  $schema_name . '.sqlite'`. Real SQLite via PDO, `journal_mode=WAL`.
- Requires the **`pdo_sqlite` extension**, which is not present on every host.
  This is a portability gap `storh` does not have (it needs only `ext-json`).

Neither file backend has an index; `sqlite` gets concurrency safety from
SQLite/WAL, `jsonc` gets none.

## What `storh` provides

Three engines, one record shape (a UUIDv7 id + an array payload), three disk
layouts (from the official docs):

| Engine | On disk | Writing | Reading | Sweet spot |
| --- | --- | --- | --- | --- |
| `DocStore` | one JSONC file per record, UUID-tail sharded | atomic file replace per record | `get()` by id, indexed queries | point reads + indexed lookups at any size |
| `SegmentedLog` | append-only NDJSON segments + manifest | checksummed appends; compaction reclaims space | cursor + time-range scans | append-heavy streams to ~1M records |
| `Queue` | one append-only event log | checksummed append per event | `claim()` in order, multi-process safe | durable work dispatch between processes |

Relevant capabilities:

- **Atomicity:** `AtomicFilesystem::write_atomic()` = temp file → fflush/fsync →
  rename over target → fsync parent dir. Reopen/`repair()` clears abandoned temp
  files; corrupt records quarantined under `.storh/corrupt/`.
- **Indexes** (`DocStore`): equality, unique, and range indexes stored under
  `.storh/indexes`, rebuildable from records; `explain()` reports `index_scan`
  vs `full_scan`.
- **Query builder** (`DocStore` + `SegmentedLog`): `where()->eq/neq/in/notIn/
  gt/gte/lt/lte/between/exists/missing/prefix`, `andWhere/orWhere`, `orderBy/
  limit/cursor/page`, terminals `get/first/count/explain`.
- **Schema** (optional): validates writes + declares indexes.
- **Caching:** read-through `Cache::memory()` / `Cache::apcu()` (APCu no-ops
  when absent), validation modes `STAT` / `HASH` / `TRUST`.
- **Ids:** `put(array $data, ?string $id = null)` accepts a caller-supplied id,
  so existing `bs.db` ids can be preserved rather than forced to UUIDv7.
- **Bulk:** `importJsonl()` / `exportJsonl()` for migration and git export.
- **Maintenance:** `stats/health/verify/repair/compact/reindex`.
- **Root:** `StorageRoot::resolve($dir, $namespace)`: the caller supplies the
  directory; `storh` never discovers app paths or reads globals.

## Feasibility

| Dimension | Finding | Verdict |
| --- | --- | --- |
| PHP version | `storh` requires `^8.2`; Blockstudio requires `>=8.2` | Match |
| Runtime deps | `storh` needs only `ext-json` (always present in WP); APCu optional/no-op | Clean |
| Distribution | `storh/storh` **is published on Packagist** (`v0.0.2`) | `composer require storh/storh`, no VCS repo |
| Packaging | Blockstudio scopes all Composer deps via **php-scoper** (`scoper.inc.php`, prefix `BlockstudioVendor`, output `lib/`, classmap autoload) | `storh` flows through the same `composer scope` step as CommonMark/Symfony-YAML/scssphp/tailwindphp; `Storh\` → `BlockstudioVendor\Storh\`. Scoping also isolates it from any other plugin shipping `storh`. |
| Autoload | `blockstudio.php:45` custom autoloader for `Blockstudio\`; scoped vendors via `lib/` classmap | No change needed; adapter references the scoped FQN |
| Atomic writes on hosts | `storh` uses temp-file + rename inside one dir; requires same-filesystem rename | **Build cache already relies on `rename()`** (`build-cache.php:324-331`), so this is an accepted constraint. Weaker on some NFS mounts; uploads dir is local on typical hosts. |
| Concurrency | `DocStore` atomic per-record replace; `Queue` lock-guarded claims | Strictly better than the current `jsonc` race |
| Filesystem API | `storh` uses direct `fopen/rename/fsync`, not `WP_Filesystem` | Consistent with Blockstudio's existing cache code (all direct fs); accept and document |

**Risk: `storh` is `v0.0.2`, pre-1.0, released 2026-07-05.** The API can change.
Mitigations: (1) Blockstudio and `storh` share the `inline0` org, so the schedule
is controllable; (2) pin an exact version in `composer.json`; (3) the scoped copy
in `lib/` is frozen per release regardless; (4) depend on `storh` only through a
Blockstudio adapter interface (below), so an API change touches one class.

## Engine-to-subsystem mapping

| Blockstudio subsystem | Best `storh` engine | In this PRD? |
| --- | --- | --- |
| `bs.db` runtime-mutable records (`jsonc` backend replacement for form entries, subscribers, UGC) | `DocStore` | **V1** |
| `bs.db` small committable seed data (single git file in source tree) | keep existing `jsonc` | Unchanged |
| Build cache (`build-cache.php`) | `DocStore` blob store | Non-goal (already atomic + versioned + watch-invalidated + PHP-`include` fast path; high risk, low reward) |
| Compiled asset caches (`tailwind.php`, `_dist` in `assets.php`, `esmodules*.php`) | content-addressed blob store | Non-goal (immutable, hash-named, benign races) |
| Content sync files (`content-sync.php`) | `DocStore` | Non-goal (git-diffable readable files are the point; `storh` sharding breaks that) |
| New durable async (deferred rebuilds, image/CSS processing now on WP-Cron) | `Queue` | Future opportunity, not V1 |

## V1 Scope

V1 ships exactly this:

- A new **`Storage_Type::Storh`** file backend for `bs.db`, selected with
  `Schema(storage: 'storh')` (name TBD, see open questions), implemented with
  `storh` `DocStore`.
- Full parity with the existing file backends' behavior through the public
  `Blockstudio\Db` API: `create / get / list / update / delete / paginate /
  count`, plus the existing filter semantics, mapped onto `DocStore` `put / get
  / delete / query()`.
- **Ids preserved.** Whatever `bs.db` assigns today (integer sequence) is passed
  to `DocStore::put($data, $id)` and kept as the record's id, so existing
  consumers and JS callers see no id-shape change.
- **Data stored in the writable uploads dir**, not the block source tree:
  `uploads/blockstudio/db/<scope>/<schema>/` via `StorageRoot::resolve()`. This
  is a behavior change from `jsonc` (which writes into the block folder) and is
  correct for runtime-mutable data (writable in production, not clobbered on
  deploy, not committed to git).
- **Indexes** declared from the existing `bs.db` schema field definitions
  (`db/schema.php`): equality index for filterable fields, unique where the
  schema marks uniqueness. Range indexes only where a numeric/date field is
  declared filterable.
- A **migration command** (`wp bs db migrate --to=storh`, or equivalent) that
  reads an existing `<schema>.jsonc` and `importJsonl`s it into the `storh`
  store, preserving ids.
- The `storh` dependency added to `composer.json`, scoped into `lib/`, and
  wrapped by a Blockstudio adapter.

V1 does **not**: touch build cache, asset caches, or content-sync; change the
default backend; remove or deprecate the `jsonc`/`sqlite` backends; expose
`SegmentedLog` or `Queue`; or change any public `bs.db` method signature.

## Architecture

Introduce a thin seam so the plugin depends on an internal interface, never on
`storh` directly.

```
includes/classes/db/
  record-store-interface.php     // Blockstudio\Db\Record_Store_Interface
  storh-record-store.php         // Blockstudio\Db\Storh_Record_Store (adapter)
```

- `Record_Store_Interface` declares the operations `Database` needs from a file
  backend: `put($id, array $data)`, `get($id)`, `delete($id)`, `query($criteria,
  $paging)`, `count($criteria)`. It mirrors what `jsonc_*` and `sqlite_*` already
  do internally, extracted as a contract.
- `Storh_Record_Store` is the **only** class that names the scoped
  `\BlockstudioVendor\Storh\DocStore`, `\BlockstudioVendor\Storh\StorageRoot`,
  `\BlockstudioVendor\Storh\Schema`, and the query builder. It translates
  `bs.db` schema + criteria into `DocStore` schema + `query()` chains.
- `Database` gains a `Storage_Type::Storh` branch (`storage_type()`
  `:586-588`) that constructs a `Storh_Record_Store` and routes CRUD to it,
  exactly parallel to the existing `jsonc_*` / `sqlite_*` dispatch.
- **Storage root:** one `StorageRoot` per `(scope, schema)` under
  `uploads/blockstudio/db/`. Reuse the uploads-dir resolution already in
  `build-cache.php:95-105` so both caches and records live under
  `uploads/blockstudio/`.
- **Caching:** construct `DocStore` with `Cache::memory()` sized modestly (per-
  request memory cache is enough; validation `STAT`). Consider `Cache::apcu()`
  when APCu is available and the workload is read-heavy; it no-ops otherwise.

### Scoping notes (php-scoper)

- Run `composer require storh/storh` then `composer scope`; confirm `storh`
  lands in `lib/storh/` with namespace `BlockstudioVendor\Storh\`.
- `storh` is pure PSR-4 PHP with no global functions, constants, or namespace-
  reflection tricks flagged in the docs, so it should scope cleanly. **Add a
  scoping smoke test** (below) to prove the scoped class instantiates and round-
  trips a record; php-scoper edge cases (dynamic class strings) must be caught
  here, not in production.

## Migration

1. Existing `jsonc` collections are untouched and keep working. Migration is
   **opt-in per schema**: a developer sets `storage: 'storh'` and runs the
   migrate command.
2. `wp bs db migrate --schema=<name> --to=storh`:
   - read `<schema>.jsonc` with the existing `jsonc_read()`,
   - write each record via `Storh_Record_Store::put($record['id'], $record)`
     (or convert to JSONL and `DocStore::importJsonl()`), preserving ids,
   - run `DocStore::reindex()` and `verify()`, report counts,
   - leave the original `.jsonc` in place (never delete source data; the
     developer removes it after verifying).
3. Rollback = switch `storage` back to `jsonc`; the original file is still there.

## Non-goals

- Replacing the mature **build cache** (atomic, versioned, watch-invalidated,
  PHP-`include` opcache fast path). A general doc store would regress the fast
  path; out of scope.
- Consolidating the **asset caches** (`tailwind`, `_dist`, `esmodules`). Content-
  hashed immutable blobs with benign races; a later, separate cleanup.
- Changing **content-sync**. Its readable, git-diffable per-record files are the
  feature; `storh` sharding is the wrong shape.
- Migrating **transient/object-cache** fragment caches (islands, pages, admin).
  Not file storage.
- Exposing `SegmentedLog` / `Queue` publicly. Tracked as future opportunities.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| `storh` pre-1.0 (`v0.0.2`) API churn | Pin exact version; adapter seam; scoped copy frozen in `lib/`; shared org controls cadence |
| On-disk layout change (sharded tree vs single file) | Only for the new runtime-mutable backend; keep `jsonc` for committable single-file seed data; document clearly |
| Data location moves to uploads | Intentional and correct for mutable data; document; migration command handles existing files |
| Id shape | Preserve `bs.db` ids via `put($data, $id)`; do not force UUIDv7 on existing collections |
| Atomic rename on network filesystems | Same constraint the build cache already accepts; uploads is local on typical hosts; `verify()/repair()` recover torn writes |
| php-scoper mis-scoping dynamic references | Scoping smoke test in CI before any feature test |
| Direct fs vs `WP_Filesystem` | Consistent with existing Blockstudio cache code; documented decision |

## Testing

- **Scoping smoke test** (runs after `composer scope`): instantiate
  `\BlockstudioVendor\Storh\DocStore` under a temp root, `put`/`get`/`query`/
  `delete`, assert round-trip. Guards the packaging path.
- **Adapter unit tests** (`tests/unit/db/`): `Storh_Record_Store` create/get/
  update/delete/list/paginate/count parity against the same assertions the
  `jsonc` backend satisfies; id preservation; index-backed filter returns
  `index_scan` via `explain()`.
- **Concurrency test (the point of this PRD):** spawn concurrent writers against
  one collection and assert **no lost updates** on the `storh` backend, with the
  same test demonstrating the loss on `jsonc` (documents the fix).
- **Migration test:** seed a `.jsonc`, run migrate, assert record count + ids +
  field values match, and `verify()` is clean.
- **E2E** (`tests/e2e/`, gated `[e2e]`/`[all]`): a `bs.db` block using
  `storage: 'storh'` performs create/list/delete through the JS `bs.db` API on
  the front end and editor, proving the public surface is unchanged.
- CI: unit + scoping smoke on `[all]`; keep `composer cs` / `tsc` green.

## Rollout / phasing

- **Phase 1 (V1):** dependency + scoping + adapter + `Storage_Type::Storh` +
  index mapping + migration command + tests. Ship behind the explicit
  `storage: 'storh'` opt-in. Default stays `table`; file default stays `jsonc`.
- **Phase 2:** make `storh` the recommended file backend in docs; consider
  defaulting new file schemas to `storh`; keep `jsonc` for committable seed
  data; consider deprecating the `sqlite` backend (superseded, and `storh`
  works without `pdo_sqlite`).
- **Phase 3 (separate PRDs):** evaluate `Queue` for durable async (deferred
  builds, media processing) and `SegmentedLog` for an audit/event trail; revisit
  folding the asset caches into a shared `storh` blob store.

## Open questions (need a decision before Phase 1)

1. **Backend name:** `Storage_Type::Storh` / `storage: 'storh'`, or a neutral
   name like `records` / `file` that hides the vendor? (Recommend a neutral
   public name, `storh` internal.)
2. **Default file backend:** keep `jsonc` as the file default in V1 (recommended,
   conservative), or make `storh` the default for new file schemas immediately?
3. **Keep vs deprecate `sqlite`:** does `storh` fully cover the `sqlite`
   backend's use case (it does, without the `pdo_sqlite` requirement), and if so,
   should `sqlite` be soft-deprecated in Phase 2?
4. **Data location:** confirm `uploads/blockstudio/db/` (recommended) vs a
   configurable path; and whether any existing `jsonc` consumers rely on the
   file living next to the block.
