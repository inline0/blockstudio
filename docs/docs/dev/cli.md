---
title: CLI
description: Manage Blockstudio blocks, pages, database, RPC, and cron from the command line via WP-CLI.
path: "dev/cli"
order: 64
section: "Dev"
subsection: "Command Line"
meta_title: "CLI"
meta_description: "Manage Blockstudio blocks, pages, database, RPC, and cron from the command line via WP-CLI."
---

# CLI

Blockstudio registers WP-CLI commands for managing blocks, file-backed pages,
database records, RPC functions, cron jobs, settings, and the WPML language
configuration from the terminal.

## Blocks

### List all blocks

```bash
wp bs blocks list
```

```
+----------------------------+---------------------+-----------+------------------------+
| name                       | title               | component | category               |
+----------------------------+---------------------+-----------+------------------------+
| my-theme/hero              | Hero                |           | my-theme               |
| my-theme/card              | Card                | yes       | my-theme               |
| my-theme/newsletter        | Newsletter          |           | my-theme               |
+----------------------------+---------------------+-----------+------------------------+
```

### Filter to components only

```bash
wp bs blocks list --components
```

### Output as JSON

```bash
wp bs blocks list --format=json
```

## Pages

File-backed page synchronization is explicit. Ordinary WordPress requests
never perform complete page discovery or write page posts. A cold runtime may
read collection manifests once to restore routes, without synchronizing content.

```bash
wp bs pages sync
```

The command reconciles the complete desired inventory and reports discovered,
created, updated, unchanged, locked, preserved, removed, and failed pages. Equal fingerprints
produce no post or post-meta writes.

Table output names changed, locked, content-preserved, and removed pages with
their post IDs. JSON includes `pages.<name>.preserved`,
`pages.<name>.overwritten`, and `removedPages` entries with the page name, key,
post ID, and cleanup action. An identical post after keyed merging is reported
as unchanged without creating a revision or advancing its modified timestamp.

Existing publication statuses are preserved; file-owned title and slug
replacements are printed as warnings. See
[Post Field Ownership](/docs/pages-and-patterns/pages#post-field-ownership).

Use `--authoritative` to replace managed content instead of preserving keyed
editor content, and `--full` to report the pass as a full reconciliation
regardless of the stored source identity. Every pass compares the complete
inventory either way:

```bash
wp bs pages sync --authoritative --full
```

For deployment scripts that need the complete report:

```bash
wp bs pages sync --format=json
```

Every page the command parses is checked for attributes that its blocks store
but never render: attributes a Blockstudio block neither declares as a field
nor forwards to its root element, such as an undeclared `class`. The table
output prints a warning per page and block, the `dropped` column counts them,
and the JSON report lists them under `pages.<name>.droppedAttributes`. Pages
whose source did not change are not parsed again, so they are reported the
next time they change, or on the first sync after an upgrade that changes the
parser.

The command exits unsuccessfully when discovery or reconciliation reports an
error. See [Pages](/docs/pages-and-patterns/pages#sync-behavior) for the PHP API
and deployment identity contract.

## Database

### List schemas

```bash
wp bs db schemas
```

```
+-------------------+-------------+---------+---------------------+
| block             | schema      | storage | fields              |
+-------------------+-------------+---------+---------------------+
| my-theme/app      | subscribers | table   | email, name, plan   |
| my-theme/app      | logs        | jsonc   | action, details     |
| my-theme/app      | events      | storh   | title, status       |
+-------------------+-------------+---------+---------------------+
```

### CRUD operations

```bash
# List records
wp bs db list my-theme/app subscribers

# List with filters
wp bs db list my-theme/app subscribers --plan=pro --limit=10

# Get a single record
wp bs db get my-theme/app subscribers 1

# Create a record
wp bs db create my-theme/app subscribers --email=a@b.com --plan=pro

# Update a record
wp bs db update my-theme/app subscribers 1 --plan=enterprise

# Delete a record
wp bs db delete my-theme/app subscribers 1
```

For single-schema blocks, the schema name defaults to `default`:

```bash
wp bs db list my-theme/leads
```

### Migrate JSONC records to Storh

Use `migrate` when a schema already has a `db/{schema}.jsonc` file and you want
to move those records into the Storh file backend:

```bash
wp bs db migrate my-theme/app subscribers --to=storh
```

For single-schema blocks:

```bash
wp bs db migrate my-theme/app --to=storh
```

The command preserves integer record IDs, writes to
`uploads/blockstudio/db/{block-scope}/{schema}/`, rebuilds Storh indexes,
verifies the target store, and leaves the source `.jsonc` file in place.

### Output formats

All list commands support `--format=table` (default), `--format=json`, and
`--format=csv`:

```bash
wp bs db list my-theme/app subscribers --format=json
```

## RPC

### List all functions

```bash
wp bs rpc list
```

```
+-------------------+-------------+--------+----------------+---------+
| block             | function    | public | capability     | methods |
+-------------------+-------------+--------+----------------+---------+
| my-theme/app      | subscribe   | yes    |                | POST    |
| my-theme/app      | stats       |        | edit_posts     | POST    |
| my-theme/app      | get_status  |        |                | GET,POST|
+-------------------+-------------+--------+----------------+---------+
```

### Call a function

```bash
wp bs rpc call my-theme/app subscribe --email=user@example.com
```

Output is JSON:

```json
{
  "success": true
}
```

Parameters are passed as `--key=value` flags:

```bash
wp bs rpc call my-theme/app load_more --offset=10 --limit=5
```

## Cron

### List all jobs

```bash
wp bs cron list
```

```
+-------------------+----------+----------+---------------------+
| block             | job      | schedule | next_run            |
+-------------------+----------+----------+---------------------+
| my-theme/app      | cleanup  | daily    | 2026-03-17 00:00:00 |
| my-theme/app      | sync     | hourly   | 2026-03-16 15:00:00 |
+-------------------+----------+----------+---------------------+
```

### Run a job manually

```bash
wp bs cron run my-theme/app cleanup
```

This executes the callback immediately, regardless of the schedule. Useful for
testing and debugging.

## Settings

### Show all settings

```bash
wp bs settings list
```

### Get a specific setting

```bash
wp bs settings get tailwind/enabled
wp bs settings get assets/enqueue
```

## Tailwind

### Compile HTML to CSS

```bash
wp bs tailwind compile '<div class="flex items-center p-4">'
```

### Compile from file

```bash
wp bs tailwind compile --file=template.html
```

### Cache management

```bash
wp bs tailwind cache path    # show cache directory
wp bs tailwind cache clear   # clear compiled cache
```

## SCSS

### Compile SCSS to CSS

```bash
wp bs scss compile '$color: red; .test { color: $color; }'
```

### Compile from file

```bash
wp bs scss compile --file=styles.scss
```

## Custom Fields

### List all registered custom fields

```bash
wp bs fields list
```

```
+-------+----------------+---------------------------+
| name  | title          | fields                    |
+-------+----------------+---------------------------+
| hero  | Hero Section   | heading, description, cta |
| card  | Card           | title, image, link        |
+-------+----------------+---------------------------+
```

## Assets

### List all assets

```bash
wp bs assets list
```

### Filter by type

```bash
wp bs assets list --type=global
wp bs assets list --type=admin
wp bs assets list --type=editor
wp bs assets list --type=registered
```

## Scripting

All commands support `--format=json` for easy piping:

```bash
# Export all subscribers as JSON
wp bs db list my-theme/app subscribers --format=json > subscribers.json

# Count records
wp bs db list my-theme/app subscribers --format=json | jq length

# Batch delete
wp bs db list my-theme/app logs --format=json | \
  jq -r '.[].id' | \
  xargs -I {} wp bs db delete my-theme/app logs {}
```

## Content Sync

Content Sync projects allowlisted WordPress content to files and applies those
files back to the database. It is explicit and CLI-driven; it does not sync on
page load or admin initialization.

See [Content Sync](/docs/content-sync) for configuration, file format, and
guidance on when file-based database content is a good fit.

### Pull database content to files

```bash
wp bs content pull
```

Limit the pull to one configured post type or taxonomy:

```bash
wp bs content pull --post-type=team_member
wp bs content pull --taxonomy=category
```

Preview without writing UIDs or files:

```bash
wp bs content pull --dry-run
```

### Push files to the database

```bash
wp bs content push
```

Preview the apply plan without writing:

```bash
wp bs content push --dry-run
```

Prune content-set owned database posts and terms that no longer exist in files:

```bash
wp bs content push --prune --yes
```

With `--prune`, Blockstudio applies all file changes first, then removes content-set owned entities that no longer exist in the files. It refuses to run a destructive prune unless `--yes` is present.

### Check status

```bash
wp bs content status
```

The table reports each file-backed entity as `unchanged`, `would-update`, `missing-db`, `conflict`, `locked`, or `orphaned`.

## Static Prerendering

See [Static Prerendering](/docs/production/static-prerendering) for the cache
itself; these commands drive it.

### Warm stale entries

```bash
wp bs prerender warm
```

Enqueues stale public URLs, then re-renders up to `warm.concurrency` jobs from
the durable warm queue and prints a JSON summary with `queued`, `processed`,
`warmed`, `skipped`, and `failed` counts. Content and site changes enqueue
replacement jobs automatically. Run the command again, or let the scheduled
warmer run, to work a larger queue down; a URL that fails to render stays
stale and is enqueued again on the next run. In `graph` mode the per-URL
warmer is off and the command enqueues nothing.

### Report cache state

```bash
wp bs prerender status
```

Reports the active identity, files, bytes, graph records, queue state, the
early-serve map report, and per-scope hit/miss/build/failure counters.

### Purge the cache

```bash
wp bs prerender purge
```

Removes the current site's prerendered documents. The next anonymous request or
warm pass rebuilds them.

### Check and prune the early-serve map

```bash
wp bs prerender map status
wp bs prerender map prune --dry-run
wp bs prerender map prune
```

`map status` reports the installation-wide early-serve map: its entries and
routes, route documents that are missing, `orphaned` entries whose site no
longer exists, and `dead` entries whose directory is gone. `map prune` removes
orphaned and dead entries and prints each removed entry with its reason;
`--dry-run` prints the same report without writing. A map owned by another
tool is never touched.

## WPML

### Generate the language configuration

```bash
wp bs wpml config
wp bs wpml config --file=wp-content/themes/my-theme/wpml-config.xml
```

Prints a `wpml-config.xml` that declares the text fields of every registered
block for translation and its files fields as attachment IDs, or writes it with
`--file`. In an existing file, only the section between the Blockstudio markers
is replaced. See [WPML](/docs/dev/wpml) for what is declared and how to keep the file up to date.

## Block Tag Migration

A standalone script rewrites prefix and alias shorthands to the canonical
`<bs:namespace-slug>` spelling. It does not load WordPress and does not write
unless `--apply` is explicitly supplied:

```bash
php vendor/blockstudio/blockstudio/bin/migrate-block-tags.php \
  --root="$PWD" \
  --prefix-map=/tmp/project-prefixes.json \
  --known-blocks=/tmp/project-blocks.json \
  --aliases=/tmp/project-aliases.json \
  --report=/tmp/blockstudio-tag-migration.json \
  --dry-run
```

The prefix file maps caller-owned prefixes to ordered namespaces. The known
block file is a JSON list of registered block names. Exact aliases are
optional. The report includes deterministic mappings, before/after hashes,
unknown tags, ambiguous aliases, dynamic tags, and examples found in comments,
code fences, `<pre>`, or `<code>` ranges.

The scanner handles paired, nested, and self-closing markup, including literal
tags in PHP strings. It preserves attributes and changes only tag names.
Comments and code samples are report-only. Unrelated custom elements are
ignored. Applying a migration is blocked while unknown, ambiguous, or dynamic
cases remain unless the caller deliberately passes `--allow-unresolved`.
Running the command again over canonical output produces no changes.

See [Migration](/docs/dev/migration/v7#canonical-block-tags) for why the
canonical spelling exists and what it resolves.

## Teardown

```bash
wp bs teardown
```

Removes the cron events and static prerender state an installation owns: the
current site's early-serve map entry, and the shared `advanced-cache.php`
drop-in and `WP_CACHE` declaration once the final owned entry is gone.
Installations without a plugin deactivation hook, such as Composer-bundled
setups, use this for a clean removal. Configuration files are left untouched.
