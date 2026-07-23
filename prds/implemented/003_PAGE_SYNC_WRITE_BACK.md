# 003: Page Sync Write-Back (Block-Editor Edits to Page Files)

## Summary

Page Sync (`includes/classes/page-sync.php`) currently projects page source files
into WordPress posts in one direction: file -> database. When a Page
Sync-managed page is edited in the block editor, the edit lands in `wp_posts`.
The source file remains unchanged, so the edit is not reviewable in git, is not
portable across environments, and can be lost on a later file-origin sync or a
fresh checkout.

This PRD adds an optional database -> file write-back path for Page
Sync-managed pages. A deliberate editor save can update the page's source file,
turning the edit into a normal source diff while preserving the rule that the
file remains the source of truth.

This is scoped to Blockstudio-owned Page Sync posts only. Content Sync continues
to own non-managed content and continues to exclude Page Sync-managed posts by
default.

## Current State

- `Page_Sync::sync()` creates or updates one post per page source.
- `find_existing_post()` provides stable file-to-post identity through explicit
  `postId`, `_blockstudio_page_key`, `_blockstudio_page_source`,
  `_blockstudio_page_name`, and `_blockstudio_page_collection`.
- `build_fingerprint()` hashes page data and source inputs, then stores the
  result in `_blockstudio_page_fingerprint`. It is a source fingerprint, not a
  complete three-way database/file conflict model.
- `_blockstudio_page_locked` and `Pages::lock()` / `Pages::unlock()` stop
  file-origin sync from overwriting a post. They do not persist editor edits
  back to files.
- `Block_Merger` preserves editor edits for keyed blocks during file-to-database
  sync by matching `__BLOCKSTUDIO_KEY` in parsed block arrays.
- Important limitation: `Block_Merger` has no source byte ranges, no original
  authoring syntax, and no link back to the exact source fragment that produced
  a parsed block.
- Important limitation: `Html_Parser` strips PHP before parsing blocks, so PHP
  preservation cannot be recovered from parsed blocks. Any byte-stable PHP
  preservation must happen in a source-aware writer before or around parsing.

## Core Problem

Page files are authored in source formats: PHP, HTML, Markdown, Twig, Blade,
loader-returned content, or generated containers. The block editor saves
serialized Gutenberg block markup. A whole-file "serialize the post back to the
source file" implementation would destroy source authoring form, PHP, helper
calls, dynamic sections, and semantic block tags.

Write-back therefore must be source-region aware. It may only update source
regions that Blockstudio can map and re-emit safely. Everything else must remain
byte-stable or be reported as unsupported.

## Required Design

### Opt-in behavior

Write-back must be disabled by default for 7.4-era existing sites.

Enable it through explicit page or collection configuration, for example:

```json
{
  "writeBack": true
}
```

Collection manifests can provide a default for all collection pages. Individual
`page.json` or frontmatter can override it.

The exact schema key can change during implementation, but the shipped behavior
must be explicit and documented.

### Trigger and loop guards

Register a save hook for Page Sync-managed posts only. The hook must skip:

- autosaves
- revisions
- REST/autosave heartbeat saves that do not represent a deliberate post save
- posts without `_blockstudio_page_*` identity
- generated container pages
- pages whose write-back config is disabled
- users who cannot edit the post
- file-to-database sync operations initiated by `Page_Sync::sync()` or
  `force_sync()`

Add an explicit in-process guard, for example `Page_Sync::$is_syncing`, so the
write-back hook cannot run when Page Sync itself calls `wp_update_post()`.

Prefer `wp_after_insert_post` or an equivalent post-save point where the final
saved `post_content` is available. Do not write on every editor heartbeat.

### Source support matrix

The implementation must define and enforce support per source type.

Initial supported target:

- File-backed HTML/PHP page sources whose editable regions can be mapped by
  `key` / `__BLOCKSTUDIO_KEY`.

Explicitly unsupported in the first vertical slice unless implementation proves
otherwise:

- generated container pages
- inline loader pages with no writable source file
- loader-generated pages whose source is not the target file
- Twig and Blade rendered templates, because rendered output does not preserve
  source spans
- Markdown -> Markdown reconstruction beyond a narrow, tested subset
- arbitrary unkeyed editor changes

Unsupported sources must fail safely with a clear status/report and must not
partially rewrite files.

### Source-region mapper

Do not assume `Block_Merger` can map editor blocks back to source. It cannot.

Add a dedicated source-region mapper that reads the original source file and
finds keyed editable regions before PHP stripping or block parsing destroys
source location information.

The mapper must produce, at minimum:

- source file path
- source hash before write
- page identity
- keyed region id
- start/end byte offsets or equivalent replacement range
- original source fragment
- parsed block identity for the region
- whether the region is writable
- reason when not writable

Region detection should use the same authored `key` syntax that feeds
`__BLOCKSTUDIO_KEY`, for example keyed `<block>`, `<bs:*>`, prefix tags, and
keyed supported HTML elements. The exact set must be documented and tested.

Duplicate keys are a hard write-back error for that page. Silent "second key is
unkeyed" behavior is acceptable for merge preservation, but not for source file
mutation.

### Region writer

Add a dedicated writer that receives:

- original full source content
- mapped source regions
- current saved post blocks
- page metadata

The writer may replace only mapped writable regions. Unchanged source outside
those regions must remain byte-identical.

Changed regions can be emitted in a canonical authoring form if preserving the
exact original syntax is too complex. That tradeoff is acceptable only for the
changed region; untouched regions must not be normalized.

Round-trip invariant:

1. editor save
2. write-back
3. Page Sync file-to-database sync

After those steps, the post and source should be stable, with no further
database or file change and no loop.

### Serialization rules

Write-back must never reverse-engineer PHP, helper calls, includes, Twig, Blade,
or other dynamic code from rendered output.

Only editor changes inside mapped keyed regions are candidates for write-back.
If a saved block inside a keyed region cannot be represented in the supported
authoring form, the region is marked unsupported and the file write is aborted
unless the implementation explicitly supports partial-region reporting without
mutating the file.

For the first implementation, prefer all-or-nothing per page write-back. A
partial file mutation with unsupported regions is too easy to misunderstand.

### Fingerprints and conflict model

Do not overload `_blockstudio_page_fingerprint` as the complete conflict model.
It is currently the file-origin sync fingerprint.

Add write-back-specific state, for example:

- `_blockstudio_page_source_hash`
- `_blockstudio_page_post_hash`
- `_blockstudio_page_write_back_status`
- `_blockstudio_page_write_back_error`
- `_blockstudio_page_write_back_at`

The write-back decision should compare:

- source hash at last successful sync/write-back
- current source hash on disk
- post hash at last successful sync/write-back
- current saved post hash

If both the source file and the database post changed since the last shared
state, report a conflict and do not overwrite the file.

After a successful write-back, update the write-back state and the normal Page
Sync fingerprint so the next file-origin sync is a no-op.

### Lock semantics

Do not reuse `_blockstudio_page_locked` as the write-back state.

Current behavior: locked means file-origin sync must not update the database
post. Write-back needs a separate state because an edited post may be "dirty"
and eligible to write back even though file-origin sync should not clobber it.

Define explicit semantics:

- `_blockstudio_page_locked`: skip file -> database updates.
- write-back disabled/config false: skip database -> file updates.
- write-back conflict/error meta: report status and skip file mutation until
  resolved.

If the implementation chooses to make lock block write-back too, that must be a
documented product decision with tests. The safer default is to keep lock and
write-back state separate.

### Redirectable write event

Expose a stable interception point before the file write so downstream products
can redirect the target to a worktree or review branch.

Recommended shape:

```php
$payload = apply_filters(
  'blockstudio/pages/write_back_payload',
  array(
    'post_id' => $post_id,
    'page' => $page_data,
    'source_path' => $source_path,
    'target_path' => $source_path,
    'source_before' => $source_before,
    'source_after' => $source_after,
    'source_hash_before' => $source_hash_before,
    'regions' => $regions,
  )
);
```

Also fire an action after success/failure with the result object:

- `blockstudio/pages/write_back_succeeded`
- `blockstudio/pages/write_back_failed`

The downstream worktree or branch policy is out of scope for Blockstudio.
Blockstudio only provides the redirectable write payload and result hooks.

## Non Goals

- Not Content Sync. Non-managed posts, postmeta, terms, taxonomies, and media
  stay with Content Sync.
- Not whole-file regeneration.
- Not reverse-engineering PHP, Twig, Blade, helper calls, includes, or dynamic
  rendered output.
- Not writing unkeyed arbitrary editor changes back to source files.
- Not implementing downstream worktree or branch routing.
- Not importing or syncing media files.

## Security and Safety

- Write only for Page Sync-managed posts with explicit write-back enabled.
- Resolve the source path from Page Sync metadata/registry, not from request
  input.
- Require `current_user_can( 'edit_post', $post_id )`.
- Skip autosaves and revisions.
- Ensure the resolved target is inside an allowed theme/plugin source root,
  unless a trusted filter redirects it.
- Use atomic writes where possible: write temp file, verify, then rename.
- Preserve file permissions where practical.
- Abort on duplicate keys, source drift, unsupported regions, invalid target
  paths, or unwritable files.
- Never silently drop editor content.

## Acceptance Criteria

- With write-back enabled, editing a keyed text region in a Page Sync-managed
  PHP/HTML page and saving updates the corresponding source file region.
- Unchanged source outside the edited region is byte-identical after write-back.
- PHP and dynamic source outside mapped regions are preserved byte-for-byte.
- A write-back followed by Page Sync produces no further post or source change.
- The hook does not run during Page Sync's own `wp_update_post()` calls.
- Autosaves and revisions do not write files.
- Generated pages, inline loader pages, unsupported Twig/Blade pages, and
  unsupported Markdown cases do not mutate files and produce a clear report.
- Duplicate region keys block write-back for the page.
- Source drift since last sync blocks write-back.
- Non-managed posts never write files.
- A filter can redirect the write target and inspect the complete payload.
- Docs, schema/types where applicable, `includes/llm/blockstudio-llm.txt`, and
  `readme.txt` changelog are updated.

## Tests

Unit tests:

- source-region mapper finds keyed block, `bs:*`, prefix-tag, and keyed HTML
  regions with correct replacement ranges
- mapper rejects duplicate keys
- writer preserves source outside changed regions byte-for-byte
- writer aborts when a post block cannot be represented in source form
- conflict detector allows unchanged source + changed post
- conflict detector blocks changed source + changed post
- write-back state updates hashes/fingerprints after success
- save hook skips autosaves, revisions, unmanaged posts, generated pages, and
  Page Sync internal updates
- redirect filter can change `target_path`

E2E tests:

- edit a keyed heading in the block editor, save, and assert the page source file
  changed with a minimal diff
- run Page Sync after write-back and assert no further post/content drift
- edit an unsupported source type and assert no file mutation plus visible/admin
  or API-readable error status
- verify autosave does not mutate the source file

Run the local targeted tests first where practical, then push with `[all]` and
keep CI green.

## Implementation Order

1. Add config/schema plumbing for explicit write-back enablement.
2. Add source hash/post hash state helpers.
3. Add the source-region mapper for the first supported PHP/HTML vertical
   slice.
4. Add the region writer with all-or-nothing page writes.
5. Add save hook with autosave/revision/capability/internal-sync guards.
6. Add conflict reporting and status meta.
7. Add redirectable payload filter and success/failure actions.
8. Add docs, LLM docs, changelog, and tests.
9. Expand supported source forms only after the vertical slice is proven
   idempotent.

## Boundaries and Relationships

- Page Sync gains this database -> file direction for managed pages only.
- Content Sync continues to own non-managed content and excludes Page
  Sync-managed posts by default.
- `Block_Merger` remains useful for file -> database preservation, but write-back
  requires a new source-aware mapper/writer.
- Downstream products such as Divine can consume the redirectable payload to
  route writes into a worktree or review flow. That policy does not live in
  Blockstudio.

## Open Questions

- Should the first release support Markdown write-back at all, or only report it
  as unsupported until a constrained Markdown writer exists?
- Should successful write-back automatically clear `_blockstudio_page_locked`, or
  should lock remain strictly user-controlled?
- Should write-back errors surface as admin notices, REST fields, post meta, or
  all three?
- Should changed regions use canonical `<block name="">` syntax or preserve
  project-specific shorthand syntax when possible?
- Should write-back be available for plugin-bundled page sources, or only active
  theme sources by default?
