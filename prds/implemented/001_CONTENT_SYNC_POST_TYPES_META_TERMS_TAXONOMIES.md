# 001: Content Sync (Post Types + Postmeta, Terms, Taxonomies)

## Summary

Blockstudio already turns *page templates* into files via **Page Sync**: a one-way
files to database projection with stable identity, SHA256 fingerprinting, orphan
pruning, and keyed-block merge. What it does not do is version **content**: the
posts, postmeta, terms, and taxonomies that make up a site's actual data.

This PRD adds **Content Sync**: a bidirectional, git-friendly projection of
WordPress content to and from human-diffable files, with **portable identity** so
the same content round-trips across environments (local to staging to production)
and across git branches without auto-increment ID collisions.

The content surface (full vision) is the WordPress content core:

- `wp_posts` (allowlisted post types) and `wp_postmeta`
- `wp_terms`, `wp_term_taxonomy`, `wp_termmeta`
- `wp_term_relationships` (the post-to-term join: the connective tissue)

Two developer commands frame the workflow:

- `wp bs content pull` (database to files): capture current content into files.
- `wp bs content push` (files to database): apply the file content to this
  environment.

Files are the source of truth; the database is a projection. This is the same
stance Page Sync already takes, generalized from page templates to content data.

This document is the full design. **v1 is deliberately a narrow, conservative
slice** (see "V1 Scope"): one post type, declared meta references only, portable
identity, with safe-by-default behavior everywhere a choice could lose or corrupt
data. Terms, relationships, multiple post types, and the shared core extraction
are deliberate follow-up phases. The ambitious surface is the target, not the
first commit.

This is a Blockstudio-owned feature. It sits natively next to `Page_Sync`,
`Storage_Sync`, and `Database`, and reuses their proven internals via a shared
core extracted *after* the first slice proves the boundary (see "Architecture").
Downstream products (for example a git-workspace product that wants content
variants per worktree) consume the same commands and files; none of that coupling
lives here.

## V1 Scope (the conservative slice)

v1 ships exactly this, and nothing wider:

- **One allowlisted post type** plus its declared meta. No implicit multi-type
  sweep.
- **Portable identity** (`_blockstudio_content_uid`) and a **content-set
  namespace** (`content.id`) so one sync config can never prune another's content.
- **Declared references only.** References are rewritten solely at configured
  meta-key + path. Nothing is inferred. An undeclared integer is never treated as
  an ID.
- **Page Sync managed posts excluded by default.** Two systems never write the
  same `post_content`.
- **Attachments are export-and-preflight, not resolve.** v1 does not ship
  binaries or fabricate attachments. A declared attachment reference must resolve
  to an existing local attachment on push, or preflight blocks the apply with a
  clear report.
- **Terms, term relationships, and termmeta are phase 2.** The full design
  includes them, but the first v1 slice proves post identity and declared meta
  references before adding the relationship graph.
- **Preflight then safe apply.** No destructive write (prune/delete) happens until
  the additive apply has fully succeeded. There is no claim of database-wide
  rollback.
- **Content Sync services are private in v1.** The shared-core extraction and the
  Page Sync migration happen later, once this slice proves the boundary.

Everything below describes the full feature; the "Phasing" section maps what lands
when.

## Goals

- Project allowlisted content to files that are diffable, reviewable, and
  mergeable in git.
- Round-trip content across environments and a fresh database with **no broken
  references** for everything in scope (parents and declared references in v1,
  term assignments from phase 2 onward), and **clear reporting** for anything out
  of scope.
- Bidirectional and idempotent: `pull` then `push` then `pull` yields identical
  files; a no-change `push` performs no database writes.
- Allowlist-scoped, with a safe-by-default exclusion of users, secrets, Page Sync
  managed posts, and core internal state.
- Reuse Blockstudio's existing identity, fingerprint, orphan, meta, and
  file-projection machinery. The end state is a single shared, entity-agnostic
  sync core consumed by Page Sync, Content Sync, and database record sync. That
  core is a committed deliverable, extracted after the first Content Sync slice
  proves the boundary, not a speculative up-front refactor.

## Non Goals (v1)

- Media binaries. Attachment references are exported and validated. The binaries
  in `wp-content/uploads` are not copied or committed, and push does not
  fabricate attachments.
- `wp_options`, widgets, nav menus, comments, users, and post revisions. The
  allowlist is the boundary; git is the revision history.
- Full-database mirroring (VersionPress-style live change tracking). Content Sync
  is explicit and on-demand, not a write-time hook on every database mutation.
- Database-wide transactional rollback. WordPress fires hooks, primes caches, and
  may create revisions on write, so a literal all-or-nothing rollback is not
  guaranteed. Correctness rests on preflight validation and non-destructive
  ordering, not on rollback (see "Push semantics").
- A general migration or staging product. This is a content projection, not a
  deployment pipeline.

## Current State (grounded findings)

Blockstudio is further along than a blank slate. The identity, fingerprint,
orphan, meta read/write, and file-projection concerns already exist in mature
form. Content Sync builds on them.

### Page Sync (files to database, one way)

`includes/classes/page-sync.php`:

- `sync()` (~L46) inserts or updates one post per page file.
- `find_existing_post()` (L300-384): three-tier identity lookup. A pinned
  `postId` in `page.json`, then `_blockstudio_page_key` meta, then
  `_blockstudio_page_source` meta, then `_blockstudio_page_name` plus
  `_blockstudio_page_collection`. This is how a file maps back to the same row on
  every run, and how duplicates are avoided.
- `build_fingerprint()` (L834-874): SHA256 over page data plus full template and
  layout file contents, stored in `_blockstudio_page_fingerprint`. Drives
  skip-if-unchanged.
- `prune_duplicate_posts()` (L747-774) and `mark_stale_missing()` (L682-711):
  orphan detection and pruning, with the `blockstudio/pages/orphan_action` filter
  (L730) choosing trash, delete, or keep.
- Hooks: `create_post_data` (L461), `post_created` (L477), `update_post_data`
  (L526), `post_updated` (L542).
- `_blockstudio_page_locked` plus `Pages::lock()` / `Pages::unlock()`
  (`pages.php` L1468-1499) prevent sync from overwriting in-progress edits.
- Direction is one way. There is no database to file export anywhere in the
  plugin today.

`includes/classes/page-discovery.php` discovers files and topologically sorts
pages parent-first (L1044-1065, parent-child resolution L1005-1037).
`includes/classes/page-registry.php` is the in-memory registry.
`includes/classes/block-merger.php` preserves keyed-block client edits on update.

Pages managed by Page Sync are identifiable by their `_blockstudio_page_*` meta.
Content Sync uses that marker to exclude them by default.

### Postmeta read/write already exists

- `includes/classes/storage-sync.php`: on `save_post` (L42), extracts block field
  values and writes them to postmeta via `update_post_meta` / `delete_post_meta`
  (L244-249).
- `includes/classes/storage-handlers/post-meta-storage.php`: `register()`
  (L42-66) wires `register_post_meta` with `show_in_rest` and a field-type to
  meta-type mapping (L93-112), including array handling.

### A records-to-files precedent already exists

- `includes/classes/database.php` has a `db.php` schema system with multiple
  storage backends: `table`, `sqlite`, `jsonc`, `meta`, and `post_type`.
- `storage: post_type` registers a `bs_`-prefixed CPT and stores each record as a
  post with fields as postmeta (`register_post_types()` L2119-2144, `cpt_create()`
  L2308-2330, `cpt_to_record()` L2154-2183).
- `storage: jsonc` already writes records to JSONC files on disk with
  `wp_json_encode` (`jsonc_write()` ~L1604). This hand-rolled records-as-files
  projection is the third consumer the shared core should eventually absorb.

### Taxonomies are read-only today

- `includes/classes/populate.php` (L158-192) reads existing taxonomies via
  `get_terms()` for field population. Blockstudio does **not** call
  `register_taxonomy`, `wp_insert_term`, or `wp_set_object_terms` anywhere.
- Therefore term creation, termmeta, and term-relationship writes are genuinely
  new. Because Content Sync does not register taxonomies or post types, both must
  already be registered (by the active theme/plugins) for push to apply content.

### Config and command surfaces

- `includes/classes/settings.php`: config is `blockstudio.json` in the theme root
  (`json_path()` L306-310), merged defaults to `wp_options` to JSON to filters.
  `Settings::get('a/b/c')` reads nested paths (L420-440). Defaults at L75-170
  already include keys like `assets`, `tailwind`, `blockEditor`, `blockTags`,
  `users`. A new `content` key fits this model.
- `includes/classes/cli.php`: `Cli::register()` (L24-34) registers `wp bs blocks`,
  `wp bs db`, `wp bs settings`, and others. `wp bs db list --format=json`
  (L115-129) already exports records via `WP_CLI\Utils\format_items`. `wp bs
  content` is the natural home for the new commands.
- REST exists too (`includes/classes/admin-page.php` L547-600; `registry/import`
  already writes files), but v1 is CLI-first. REST is optional and later.

### What this means

- Reuse: identity lookup, fingerprinting, orphan pruning, locking, discovery and
  topological ordering, postmeta read/write with type mapping, and a
  records-to-files writer all already exist (and will be extracted after the
  first Content Sync slice proves the boundary).
- New: the database to file (pull) direction, term and taxonomy writes,
  term-relationship sync, and above all **portable cross-environment identity**
  with declared reference rewriting.

## The Core Problem: Portable Identity

Auto-increment IDs are not portable. Post 42 locally is post 99 in production.
Several things reference those IDs: `post_parent`, `_thumbnail_id`, term
relationships, `post_author`, and IDs embedded inside structured `meta_value`. If
files are keyed by ID, they do not survive a second environment and git merges
corrupt references.

Page Sync partially solved this for pages with `_blockstudio_page_key` (a stable
key) and optional `postId` pinning, but the key is path-derived and effectively
environment-local, and Page Sync never has to rewrite references because it does
not export.

Content Sync needs a stronger, explicit identity layer:

1. **A portable UID per entity.** On first pull, assign each synced post a GUID
   stored in postmeta (`_blockstudio_content_uid`). Phase 2 applies the same
   scheme to terms via termmeta. The file is keyed by this UID, never by the
   auto-increment ID. The UID travels with the file across environments and
   branches.
2. **Declared references stored as UIDs, not local IDs.** A reference is rewritten
   only where the config declares it (a meta key, optionally a path into a
   structured value), plus the structural references Content Sync owns
   (`post_parent`, term relationships from phase 2 onward, and author only when
   author sync is explicitly enabled). Undeclared values are never touched. There
   is no inference that an integer is an ID.
3. **Reference rewriting on both directions.** On pull, local IDs at declared
   locations are translated to UIDs. On push, UIDs are translated to this
   environment's local IDs, inserting missing in-scope entities first.
4. **Dependency-ordered, validate-then-apply.** Push resolves the dependency graph
   (posts parents-first in v1; terms, relationships, and taxonomy ordering from
   phase 2 onward), validates the whole plan before any write, applies
   additively, and only then prunes. See "Push semantics" for why this replaces
   transactional rollback.

### Sync state (stored meta)

Identity and conflict detection rely on a concrete, stored meta schema, written on
posts in v1 and on terms from phase 2 onward:

| Meta key | Purpose |
|---|---|
| `_blockstudio_content_uid` | Portable GUID identity. Issued on first pull, never changed. |
| `_blockstudio_content_set` | The `content.id` this entity belongs to. Prune and ownership namespace. |
| `_blockstudio_content_source` | Relative path of the file this entity maps to. |
| `_blockstudio_content_fingerprint` | Hash of the entity's synced representation as of the last successful sync (either direction). Drives skip-if-unchanged and drift detection. |
| `_blockstudio_content_locked` | Optional lock flag. When truthy, push skips the entity and reports it. |

Drift (the database changed under a file since last sync) is detected by comparing
the live entity's current hash against `_blockstudio_content_fingerprint`. A
distinct `_blockstudio_content_last_export_hash` (the hash at last pull) is an
optional refinement if file-origin versus db-origin change needs to be
distinguished; v1 uses the single fingerprint.

## Design

### Content surface and allowlist (blockstudio.json)

A new `content` settings key, read via `Settings::get('content/...')`:

```json
{
  "content": {
    "enabled": true,
    "id": "default",
    "path": "content",
    "includePageSyncManaged": false,
    "authors": "ignore",
    "postTypes": ["team_member"],
    "meta": {
      "include": ["_my_*"],
      "exclude": ["_edit_lock", "_edit_last", "_wp_old_slug"],
      "references": {
        "_thumbnail_id": { "kind": "attachment" },
        "_related_posts": { "kind": "post", "path": "*" },
        "_hero": { "kind": "attachment", "path": "image.id" }
      }
    },
    "taxonomies": ["category", "post_tag"],
    "media": "manifest"
  }
}
```

- `id`: the **content-set identity**, stored as `_blockstudio_content_set`. Prune
  and ownership are scoped to this id, so two sync configs cannot prune each
  other's content. Required; defaults to `default`.
- `includePageSyncManaged`: default `false`. When false, posts carrying
  `_blockstudio_page_*` meta are skipped entirely. When true, only their meta and
  from phase 2 onward their terms are synced, never their `post_content` (Page
  Sync owns the body).
- `authors`: v1 default `ignore`. Author data is not exported or applied, because
  users are not synced. A later `login` mode may export authors by login; in that
  mode a missing user is a blocking preflight error unless an explicit fallback
  option is added.
- `postTypes`: allowlist of post types to sync. Empty means none. There is no
  implicit "all". v1 supports one.
- `meta.include` / `meta.exclude`: glob-aware allowlist of meta keys. The default
  exclude list covers WordPress internal and edit-lock meta. Anything not matched
  by `include` is not written to files.
- `meta.references`: the **only** place reference rewriting is configured. Each
  entry declares a meta key, a `kind` (`attachment`, `post`, `term`), and an
  optional `path` into a structured value (dot path, `*` for "each element"). A
  meta key with no entry here is treated as opaque data and round-tripped
  byte-exact, never scanned for IDs. `term` references are recognized from phase
  2 onward.
- `taxonomies`: which taxonomies' terms and relationships to sync. The taxonomy
  must already be registered by the site (Blockstudio does not register
  taxonomies). Capturing `register_taxonomy` definitions is deferred (Open
  Questions). Term sync begins in phase 2.
- `media`: `manifest` (default) records attachment references and a content hash
  and validates declared attachment references on push; `none` drops attachment
  references entirely. Copying binaries is out of scope for v1.
- The allowlist is also the security boundary (see Security And Safety).

### File layout and serialization

The post body is raw block markup, not Markdown, so it is not stored in a `.md`
file. Structured metadata and the body are split into two diffable siblings. The
layout below shows the full feature; `terms/`, term relationship fields, and
`taxonomies/` begin in phase 2 or later as noted.

```
<theme>/content/
  posts/
    <post-type>/
      <slug>.<short-uid>.json   # metadata, terms, declared meta (references as UIDs)
      <slug>.<short-uid>.html   # post_content (block markup); omitted when empty
  terms/
    <taxonomy>/
      <slug>.<short-uid>.json   # one file per term (no body)
  media/
    manifest.json               # attachment uid -> {path, hash, mime, alt}
  taxonomies/                    # deferred: optional register_taxonomy capture
```

Post `.json`:

```json
{
  "uid": "9b1c0e6e-...",
  "type": "page",
  "status": "publish",
  "slug": "about",
  "title": "About Us",
  "date": "2026-01-02T10:00:00Z",
  "modified": "2026-01-04T08:30:00Z",
  "parent": "3f2a...",
  "menuOrder": 0,
  "meta": {
    "_my_subtitle": "We build things",
    "_thumbnail_id": "5e90...",
    "_hero": { "image": { "id": "5e90..." }, "align": "center" }
  },
  "metaEncoding": {
    "_hero": "json"
  }
}
```

- The body lives in the sibling `<slug>.<short-uid>.html` (same basename),
  present only when `post_content` is non-empty. The body is treated as opaque
  content; Content Sync does not parse blocks or rewrite IDs inside markup in v1
  (see Edge Cases).
- JSON keys are written in a stable order; lists are stable-ordered (UID-sorted
  where order is not semantic, preserved where it is, for example `menuOrder`).
  The goal is minimal, meaningful git diffs.
- Declared meta references are stored as UIDs at exactly their declared key and
  path. Meta values are written in decoded, human-readable form only when doing so
  is required for a declared reference or safe scalar value. `metaEncoding`
  records the original storage form (`scalar`, `json`, `php-serialized`,
  `base64`) so push can write the expected representation back to WordPress.
  Opaque PHP serialized, binary-ish, or unanalyzable values are stored as encoded
  payloads rather than lossy decoded structures. Byte-exact round-trip is required
  for opaque values; declared structured references preserve storage encoding but
  may normalize insignificant JSON or serialization formatting.

### Commands

```
wp bs content pull [--post-type=<type>] [--taxonomy=<tax>] [--dry-run]
wp bs content push [--dry-run] [--prune] [--yes]
wp bs content status
```

Direction is explicit and unambiguous:

| Command | Direction | Meaning |
|---|---|---|
| `pull` | database to files | Extract current content into files. Captures edits made in wp-admin. |
| `push` | files to database | Apply file content to this environment. The authoritative deploy direction. |
| `status` | read only | Show the diff between files and database (created, updated, orphaned, conflicted, unresolved). |

- Unlike Page Sync, Content Sync does **not** run on every admin `init`. Content
  is data; writing it must be deliberate. Sync is on-demand via CLI (and later
  optional REST for an admin UI). This avoids surprise database writes on page
  load.
- `push --dry-run` runs preflight only and prints the full plan (inserts,
  updates, prunes, reference resolutions, conflicts, unresolved references)
  without writing.
- `pull --dry-run` reports the UIDs it would assign and the files it would create,
  update, or leave stale, without writing database meta or files.
- `--prune` enables orphan handling on push (see below). Off by default so push
  is additive unless asked. Prune handling lands in phase 3.
- `--yes` skips the confirmation prompt for destructive operations.
- `--taxonomy` is accepted from phase 2 onward, when term sync lands.

### Push semantics (files to database)

Push is **preflight, then safe additive apply, then prune**. It does not rely on
database-wide rollback.

1. **Discover and plan.** Parse files, build the dependency graph, compute file
   hashes.
2. **Preflight (no writes).** Resolve every declared and structural reference to a
   local ID or a queued in-scope insert. Detect: unresolved attachment references,
   slug conflicts, locked entities, coarse or unanalyzable meta, and drift (live
   entity hash differs from `_blockstudio_content_fingerprint`). In v1, an
   unresolved declared attachment reference is a blocking error unless the config
   uses `media: "none"` and drops attachment references. If preflight finds any
   blocking error, abort before any write with a complete report. `--dry-run`
   stops here.
3. **Additive apply, in dependency order.** Posts are applied parents-first in v1.
   Phase 2 prepends terms (parents first), then applies term relationships
   (`wp_set_object_terms`) after posts and terms exist. Declared meta is applied
   with references rewritten only at the configured key and path.
   Skip-if-unchanged via fingerprint. Locked entities are skipped and reported.
   Referents are always written before referrers, so the database is never left
   with a dangling in-scope reference even on partial failure. Where the engine
   supports it, the additive apply is wrapped in a transaction, but ordering plus
   preflight, not rollback, is what guarantees referential safety.
4. **Prune (only with `--prune`, only after a fully successful additive apply).**
   Entities in the same `_blockstudio_content_set` carrying a
   `_blockstudio_content_uid` but absent from the files are orphaned via the
   shared orphan service and the `orphan_action` policy (trash, delete, keep).
   Prune never runs if the additive phase reported any error, and never touches
   another content set.
5. **Record state.** Write `_blockstudio_content_uid`,
   `_blockstudio_content_set`, `_blockstudio_content_source`, and the updated
   `_blockstudio_content_fingerprint` on every applied entity.

Slug behavior: WordPress enforces slug uniqueness per post type. The file slug is
authoritative in v1. If preflight detects that WordPress would mutate it on
insert or update (for example `about` to `about-2`), push treats that as a
blocking conflict rather than silently accepting it. The UID remains identity
regardless.

### Pull semantics (database to files)

1. Query allowlisted content (the configured post type in v1, plus taxonomies
   from phase 2 onward) honoring the meta allowlist and the Page Sync exclusion.
2. Ensure identity: any in-scope entity without `_blockstudio_content_uid` is
   assigned one and tagged with `_blockstudio_content_set` (the only writes pull
   performs, both idempotent).
3. Map local IDs to UIDs at declared and structural reference locations; serialize
   each entity to its file.
4. Write only changed files (fingerprint compare) so `pull` produces a minimal,
   reviewable diff, and update `_blockstudio_content_fingerprint`.
5. Build or update `media/manifest.json` for referenced attachments; report
   references with no resolvable attachment.
6. Report created, updated, and (with a flag) files whose database source no
   longer exists.

### Conflict, source of truth, idempotency

- Files are the source of truth. `push` is authoritative; `pull` captures.
- State lives in the meta schema above. `status` compares three things per entity:
  the file hash, the stored `_blockstudio_content_fingerprint`, and the live
  entity's current hash. File-changed drives an update; db-changed (live differs
  from fingerprint) is a conflict.
- Idempotency invariant: `pull; push; pull` leaves files byte-identical, and a
  second `push` with no file change writes nothing.
- Conflict: `status` flags it; `push` honors locks and the files-win rule but
  surfaces every overwrite in `--dry-run`; `pull` captures the database state into
  the file for git to resolve. `_blockstudio_content_locked` is the explicit
  "do not overwrite this" signal for Content Sync entities.

## Edge Cases

- Declared structured meta with embedded IDs: rewrite **only** at the declared
  `meta.references` path; never scan a structure for arbitrary integers. A value
  with no declared reference is stored through the `metaEncoding` strategy above
  and is never string-edited while serialized.
- Hierarchical posts are applied parents before children; a missing parent UID is
  an ordered in-scope insert, not an error. Hierarchical terms follow the same
  rule from phase 2 onward.
- Attachments: exported by reference via the manifest. In v1, a declared
  attachment reference must resolve to an existing attachment in the target, or
  push fails in preflight with a clear unresolved-reference report. Content Sync
  never fabricates an attachment and does not ship binaries.
- Authors: v1 ignores authors by default because users are not synced. Future
  author sync may support a portable login or stable user key, but a missing user
  must be a preflight decision, not an implicit silent fallback.
- Slug collisions: the file slug is authoritative in v1; a predicted WordPress
  slug mutation is a blocking preflight conflict. UID is identity, so a later
  policy can choose to accept and rewrite slugs, but v1 does not.
- Page Sync managed posts: excluded by default; with `includePageSyncManaged`,
  only meta syncs in v1. Terms join this behavior from phase 2 onward. Content
  Sync never writes their `post_content`.
- Multisite: scope to the current site; UIDs and content sets are per site.
  Cross-site is out of scope for v1.
- Large content sets: stream and batch; `pull` and `push` must not load the entire
  database into memory at once.
- Non-UTF8 or binary-ish meta: detect and either base64-encode in the file with a
  marker or exclude with a report. Never silently mangle.
- Block markup referencing IDs (a query block, an image block with a hardcoded
  attachment id): treated as opaque body; v1 does not rewrite IDs inside block
  markup, and this is logged in `status` so it is visible rather than a silent
  break. Rewriting block-embedded IDs is a defined later phase.
- Deleted in files vs deleted in database: deleted-in-files is the `--prune` path
  (content-set scoped); deleted-in-database surfaces on `pull` as a stale file
  report.

## Security And Safety

- The allowlist is the boundary. Nothing syncs unless a post type or taxonomy is
  explicitly listed, and meta requires an `include` match.
- Default exclusions protect against accidental leakage: no users, no
  `wp_options`, no transients, no edit-lock or session meta. Document a warning
  against adding meta keys that hold secrets, tokens, or PII to the allowlist.
- Files may end up in a git remote. Treat the allowlist as "this is safe to commit
  to a repository other people can read." `status` warns if an allowlisted meta
  key matches common secret patterns.
- Prune is content-set scoped and gated behind a fully successful additive apply,
  so a misconfiguration cannot delete unrelated content.
- All commands require `manage_options` (CLI runs as admin); a REST surface, if
  added later, uses the same capability check pattern as existing routes
  (`admin-page.php`).

## Portability Acceptance (the bar)

The single acceptance test that proves the identity layer:

0. **Precondition.** Environment B has the same theme and plugins active, with the
   synced post type already registered. From phase 2 onward, any synced taxonomies
   must also already be registered. Content Sync registers neither.
1. On environment A (seeded content), run `wp bs content pull`.
2. Commit the files. On environment B with a **fresh, empty database** (different
   auto-increment baseline), check out the files and run `wp bs content push`.
3. Assert environment B matches A for everything in v1 scope: every post present,
   every parent correct, every allowlisted meta value equal, every declared
   reference resolved, and no dangling in-scope references. If the fixture uses a
   declared attachment reference, environment B must already contain the matching
   attachment; otherwise preflight must block before any write. Phase 2 extends
   this acceptance test with term and term-relationship assertions.
4. Run `wp bs content pull` on B and assert the files are byte-identical to the
   committed files (round-trip stability).

If this passes on a database with a deliberately offset auto-increment, identity
is portable. This is the gate for v1.

## Verification And CI

Blockstudio CI (`.github/workflows/ci.yml`):

- Lint (TypeScript plus PHPCS) runs on every push. 100% WordPress Coding
  Standards, no exceptions.
- Unit tests run when the commit message contains `[unit]` or `[all]` (L43).
- E2E tests run when the commit message contains `[e2e]` or `[all]` (L78).

The **final conclusion gate for this feature is `[all]`**: a commit tagged
`[all]` runs lint, unit, and E2E together, and that combined run going green is
the acceptance signal. Use `[all]` for the landing commits of each phase.

Required coverage by phase:

- V1 unit: UID issue and idempotency, declared reference rewriting (including at a
  path inside structured meta, and proof that undeclared integers are untouched),
  `metaEncoding` preservation, allowlist enforcement (include and exclude),
  parent dependency ordering, preflight abort on unresolvable references, pull
  dry-run with no UID/file writes, slug-conflict blocking, Page Sync exclusion.
- V1 E2E (wp-env, real database): a full `pull` then `push` cycle against seeded
  v1 content and the portability test above run against the second (empty) wp-env
  that CI already starts.
- Phase 2: add term UID, term parent ordering, termmeta, term relationships,
  declared `term` references, and term-relationship E2E correctness.
- Phase 3: add content-set scoped prune (and proof one set cannot prune another),
  lock skip, `status` diff, and `push --dry-run` coverage for destructive plans.
- Extraction phase: the existing Page Sync unit and E2E suites must stay green
  across the shared-core extraction and migration.

Every landing phase must include a full local verification pass for the code it
touches before pushing: focused unit tests while developing, then the relevant
full unit and E2E suites for the phase. The release gate is a pushed commit with
`[all]` and a green combined GitHub CI run.

## Documentation, Schemas, And Changelog

Each phase that exposes user-facing behavior must land with the documentation and
release metadata needed to ship it, not as a follow-up cleanup task:

- Update `docs/src/schemas/` for the new `blockstudio.json` `content` settings,
  including `content.id`, `path`, `includePageSyncManaged`, `authors`,
  `postTypes`, `meta.include`, `meta.exclude`, `meta.references`, `taxonomies`,
  and `media`.
- Add or update TypeScript types for the content settings and CLI-facing status
  shapes where the frontend/docs tooling consumes them.
- Document the CLI workflow under `docs/content/docs/`: `wp bs content pull`,
  `push`, `status`, `--dry-run`, `--prune`, `--yes`, the v1 scope, Page Sync
  exclusion, attachment preflight behavior, slug conflicts, and portability.
- Document the file format: post JSON, sibling `.html` body file, `metaEncoding`,
  content-set ownership, stored meta keys, and phase boundaries for terms and
  taxonomies.
- Regenerate or update `includes/llm/blockstudio-llm.txt` when the docs source
  changes, so the static LLM documentation reflects the shipped behavior.
- Add a `readme.txt` changelog entry at the release boundary for the phase being
  shipped. Iterative implementation commits do not need separate changelog churn,
  but the final release commit does.

## Architecture: A Reusable Sync Core (extracted after the first slice)

The cross-cutting concerns (identity, fingerprint, reference rewriting, file
projection, orphan and prune, dependency ordering, dry-run) recur across every
file-to-database or database-to-file flow in the plugin. The end state is a
single, internal, entity-agnostic **sync core** (for example `Blockstudio\Sync\`)
that multiple consumers plug into via an entity-provider contract.

**Sequencing matters.** Refactoring Page Sync up front, before Content Sync proves
what the boundary actually needs, risks both a wrong abstraction and a regression
in shipping page behavior. So:

- v1 builds Content Sync with **private services**, written extraction-shaped but
  not yet shared.
- Once the first Content Sync slice works and the boundary is proven by a real
  second consumer, **extract the shared core and migrate Page Sync onto it**, with
  Page Sync behavior identical and its suites green as the regression gate.
- Then **converge `Database`'s hand-rolled `storage: jsonc` (and later
  `storage: post_type`) record sync** onto the same core as a third consumer.

Intended consumers of the finished core: Page Sync (page templates to posts),
Content Sync (this PRD), and database record sync. The core knows nothing about
pages, posts, terms, or records; each consumer supplies an entity provider
(discover, identity, declared references, read/write, fingerprint, apply/delete)
and the core supplies the mechanics.

What gets extracted, and from where:

| Core service | Extract from |
|---|---|
| Identity (UID issue and lookup, insert-vs-update) | `Page_Sync::find_existing_post` (L300-384) |
| Fingerprint (SHA256, mtime, skip-if-unchanged) | `Page_Sync::build_fingerprint` (L834-874) |
| Orphan and prune (stale, trash/delete/keep) | `prune_duplicate_posts` (L747-774), `mark_stale_missing` (L682-711), `orphan_action` (L730) |
| Lock | `_blockstudio_page_locked`, `Pages::lock/unlock` (L1468-1499) |
| Discovery and topological ordering | `Page_Discovery` (L1044-1065) |
| Meta projection (read/write, type map, arrays) | `Storage_Sync` (L244-249), `Post_Meta_Storage` (L42-112) |
| File projection (records to files) | `Database::jsonc_write` (~L1604) |

Discipline: extract only genuine reuse (Page Sync's keyed block merge, block
markup parsing, and template languages stay in its provider). If extracting any
one service threatens a Page Sync or Database regression, that service stays
duplicated with a tracking note. Reuse is the default, never at the cost of a
regression. The reference-rewriting layer and the database-to-file direction are
new and start in Content Sync; they graduate into the core during extraction.

## Phasing And Implementation Order

1. **Content Sync vertical slice (private services).** One allowlisted post type
   plus its declared meta, portable `_blockstudio_content_uid`, the content-set
   namespace, the reference resolver (declared key/path only), `pull` and `push`
   with preflight then safe apply, Page Sync exclusion, and the portability test
   against an offset-ID database. Services are private but written
   extraction-shaped. This is the make-or-break slice.
2. **Terms, taxonomies, relationships.** Term and termmeta read/write, parents,
   `wp_set_object_terms`, term-relationship sync, ordering integrated into the
   graph.
3. **Orphan, lock, status, dry-run.** Content-set scoped `--prune`, lock skipping,
   `status` diff, `--dry-run` plans for both directions.
4. **Extract the shared sync core and migrate Page Sync** onto it (the boundary is
   now proven by a real second consumer). Page Sync behavior identical, its suites
   green.
5. **Converge database record sync.** Make `Database`'s `storage: jsonc` (then
   `storage: post_type`) a core consumer, retiring its hand-rolled writer, with
   the Database suite green.
6. **Widen and harden.** Multiple post types, full meta allowlist semantics, media
   manifest robustness, large-set batching, multisite scoping, secret-pattern
   warnings.

Deferred beyond v1: media binary copying, block-embedded ID rewriting, options
and menus, taxonomy definition capture, a REST and admin UI surface.

## Boundaries And Relationships

- **Page Sync** owns page *template and structure* authoring (files to database,
  keyed merge, block markup). **Content Sync** owns content *data* (records, meta,
  terms, taxonomies, relationships) with portable identity and bidirectional push
  and pull. To avoid two systems writing the same `wp_posts` row, Content Sync
  **excludes Page Sync managed posts by default**; with `includePageSyncManaged`
  it syncs only their meta in v1, and terms from phase 2 onward, never their
  `post_content`.
- **`storage: jsonc`** in `Database` is an existing records-to-files projection.
  The plan converges it onto the shared core as a consumer (Phase 5), so record
  sync gains the same identity, fingerprint, diff-minimal writes, and dry-run.
- **Downstream products** (for example a git-workspace product that wants content
  variants compared across worktrees) consume `wp bs content` and the files. That
  integration does not live in Blockstudio and is out of scope here.

## Open Questions

- Future author portability: login versus a dedicated portable user key; behavior
  when the user is absent on push. v1 ignores authors by default.
- Taxonomy definition capture: whether to record `register_taxonomy` args so a
  fresh environment can register the taxonomy, given Blockstudio does not register
  taxonomies today. Proposed: deferred.
- Whether `_blockstudio_content_last_export_hash` is needed alongside the single
  fingerprint to distinguish file-origin from db-origin change. Proposed: single
  fingerprint in v1, add only if status proves ambiguous.
- Future slug-conflict resolution policy: v1 blocks in preflight. A later version
  may choose to accept WordPress slug mutation and rewrite the file on next pull.
- `push`/`pull` naming confirmation: push deploys files to the database, pull
  captures the database to files. Lock this before implementation.
