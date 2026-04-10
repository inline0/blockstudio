# Blockstudio Registry PRD

A shadcn-style CLI for WordPress blocks. No packages, no builds. Just source code copied into your project.

## Concept

Block authors publish registries (JSON files at URLs). Users configure which registries to pull from and where blocks should land. `npx blockstudio add ui/tabs` resolves the block across registries, downloads the folder, and drops it in.

Works with any block type: Blockstudio blocks, `@wordpress/create-block` output, or plain PHP blocks. The registry doesn't care about internals. It copies folders.

## Stack

- **Runtime**: Node.js (ESM)
- **CLI framework**: Commander
- **UI layer**: Ink (React for CLIs) + ink-select-input, ink-spinner, ink-text-input
- **HTTP**: Native fetch
- **Validation**: Zod (config + registry schemas)
- **Build**: tsup
- **Testing**: vitest + execa (spawn real CLI processes for E2E)
- **Formatting**: chalk (via Ink)

## User-facing files

### `blocks.json` (project config)

Lives in the theme or plugin root. Tells the CLI where to install blocks and which registries to search.

```json
{
  "$schema": "https://blockstudio.dev/schema/blocks.json",
  "directory": "blockstudio",
  "registries": {
    "ui": "https://blocks.example.com/registry.json",
    "starter": "https://raw.githubusercontent.com/acme/blocks/main/registry.json"
  }
}
```

| Field | Description |
|-------|-------------|
| `directory` | Relative path from `blocks.json` where blocks are installed. |
| `registries` | Named registries. Key is the namespace used in `add` commands. Value is a URL to a `registry.json`. |

### `registry.json` (block author publishes this)

A JSON file hosted at a URL. Lists all blocks available in that registry.

```json
{
  "$schema": "https://blockstudio.dev/schema/registry.json",
  "name": "ui",
  "description": "Core UI blocks for Blockstudio projects.",
  "baseUrl": "https://raw.githubusercontent.com/acme/blocks/main/blocks",
  "blocks": [
    {
      "name": "tabs",
      "title": "Tabs",
      "description": "Tabbed content with accessible keyboard navigation.",
      "category": "layout",
      "type": "blockstudio",
      "dependencies": ["tab-item"],
      "files": [
        "block.json",
        "index.php",
        "style.scss"
      ]
    },
    {
      "name": "tab-item",
      "title": "Tab Item",
      "description": "Single tab panel used inside Tabs.",
      "category": "layout",
      "type": "blockstudio",
      "files": [
        "block.json",
        "index.php"
      ]
    },
    {
      "name": "accordion",
      "title": "Accordion",
      "description": "Expandable content sections.",
      "category": "layout",
      "type": "create-block",
      "files": [
        "block.json",
        "edit.js",
        "save.js",
        "index.js",
        "style.css",
        "editor.css"
      ]
    }
  ]
}
```

| Field | Description |
|-------|-------------|
| `name` | Registry identifier. |
| `description` | What this registry contains. |
| `baseUrl` | Base URL for file downloads. Files are fetched from `{baseUrl}/{block.name}/{file}`. |
| `blocks[].name` | Block folder name. Also the identifier used in `add` commands. |
| `blocks[].title` | Human-readable name. |
| `blocks[].description` | One-line description shown in search/list. |
| `blocks[].category` | Grouping for list/search output. |
| `blocks[].type` | `blockstudio`, `create-block`, or `wordpress`. Informational only. CLI doesn't change behavior based on this. |
| `blocks[].dependencies` | Other blocks in the same registry that must be installed alongside this one. |
| `blocks[].files` | List of files relative to the block folder. These get downloaded and written to disk. |

File resolution: `{baseUrl}/{block.name}/{file}` for each file in the manifest.

## Commands

### `npx blockstudio init`

Creates a `blocks.json` in the current directory. Interactive prompts:

1. Where should blocks be installed? (default: `blockstudio`)
2. Add a registry? (name + URL, repeatable)

### `npx blockstudio add <namespace/block>`

Add a block to the project.

1. Parse `namespace/block` from the argument.
2. Load `blocks.json` from cwd (or walk up to find it).
3. Fetch `registry.json` from the matching namespace.
4. Find the block in the registry.
5. If block name exists in multiple registries (when no namespace given), prompt the user to pick one using Ink select.
6. Resolve dependencies recursively. If `tabs` depends on `tab-item`, both get installed.
7. Check if any target folders already exist. Prompt to overwrite if so.
8. Download all files from `{baseUrl}/{block}/{file}`.
9. Write to `{directory}/{block}/` relative to `blocks.json`.
10. Print summary: what was installed, where.

When called without a namespace (`npx blockstudio add tabs`), search all configured registries. If found in exactly one, use it. If found in multiple, prompt with Ink select showing `registry/block` options.

### `npx blockstudio list [namespace]`

List available blocks from a registry (or all registries).

Render a table with: name, title, category, type. Group by registry if showing all.

### `npx blockstudio search <query>`

Fuzzy search across all registries by name, title, and description.

### `npx blockstudio remove <block>`

Remove an installed block folder. Prompt for confirmation.

## Architecture

```
registry/
  src/
    index.ts              # CLI entry, Commander setup
    commands/
      init.ts             # Create blocks.json
      add.ts              # Add block(s) to project
      list.ts             # List available blocks
      search.ts           # Search across registries
      remove.ts           # Remove installed block
    config/
      schema.ts           # Zod schemas for blocks.json + registry.json
      loader.ts           # Find and parse blocks.json (walk up directories)
    registry/
      fetcher.ts          # Fetch + cache registry.json from URLs
      resolver.ts         # Dependency resolution (topological sort)
      downloader.ts       # Download block files from baseUrl
    ui/
      App.tsx             # Ink root component
      AddBlock.tsx        # Add flow UI (progress, prompts, summary)
      InitProject.tsx     # Init flow UI
      SelectRegistry.tsx  # Multi-registry picker
      BlockList.tsx       # List/search output
      Spinner.tsx         # Loading state
    utils/
      fs.ts               # File writing, directory creation, overwrite checks
      paths.ts            # Resolve paths relative to blocks.json
  tests/
    fixtures/
      registries/         # Static registry.json + block files for tests
        ui/
          registry.json
          blocks/
            tabs/
              block.json
              index.php
              style.scss
            tab-item/
              block.json
              index.php
            hero/
              block.json
              index.php
        starter/
          registry.json
          blocks/
            hero/         # Same name as ui/hero, for conflict tests
              block.json
              index.php
    unit/
      schema.test.ts      # Zod schema validation (valid + invalid inputs)
      loader.test.ts      # Config loading, directory walking
      resolver.test.ts    # Dependency resolution, cycle detection
    e2e/
      init.test.ts        # Full init flow: spawn CLI, assert blocks.json created
      add.test.ts         # Full add flow: spawn CLI, assert files on disk
      list.test.ts        # List output formatting
      search.test.ts      # Search result matching
      remove.test.ts      # Remove with confirmation
      conflict.test.ts    # Multi-registry conflict prompts
    helpers/
      cli.ts              # Spawn CLI process, send stdin, capture stdout/stderr
      fixtures.ts         # Serve fixture registries (local HTTP or file:// URLs)
      project.ts          # Create temp directories with blocks.json for each test
  _references/
    shadcn/               # shadcn CLI source (gitignored, local reference)
  dist/                   # Built output
  package.json
  tsconfig.json
  tsup.config.ts
  vitest.config.ts
```

## Testing

All tests run with vitest. The test suite covers three layers.

### Unit tests (`tests/unit/`)

Fast, no I/O. Test pure logic in isolation.

- **Schema validation**: valid configs parse, invalid configs throw with correct error paths. Cover every field, every edge case (missing required fields, wrong types, empty arrays, extra properties).
- **Config loader**: mock filesystem, test directory walking (finds `blocks.json` three levels up), handles missing config, handles malformed JSON.
- **Dependency resolver**: linear chains, diamond dependencies, circular dependency detection (throws), missing dependency detection, single block with no deps.

### E2E tests (`tests/e2e/`)

Spawn the real built CLI as a child process using execa. Each test gets a fresh temp directory. A local HTTP server (or file:// URLs) serves fixture registries so tests don't hit the network.

**Test helper** (`tests/helpers/cli.ts`):
```ts
// Spawn the CLI, return stdout/stderr/exitCode
async function run(args: string[], options?: {
  cwd?: string;
  stdin?: string[];     // Lines to feed for interactive prompts
  env?: Record<string, string>;
}): Promise<{ stdout: string; stderr: string; exitCode: number }>

// Spawn CLI and interact with it step by step
async function interactive(args: string[], options?: {
  cwd?: string;
}): Promise<{
  waitForText: (text: string) => Promise<void>;
  type: (input: string) => void;
  pressKey: (key: 'enter' | 'up' | 'down' | 'y' | 'n') => void;
  result: () => Promise<{ stdout: string; exitCode: number }>;
}>
```

**Test fixture server** (`tests/helpers/fixtures.ts`):
```ts
// Start a local server that serves fixture registries
async function startFixtureServer(): Promise<{
  url: string;          // http://localhost:{port}
  stop: () => Promise<void>;
}>
```

**Test project helper** (`tests/helpers/project.ts`):
```ts
// Create a temp directory with a blocks.json pointing at fixture server
async function createTestProject(options?: {
  registries?: Record<string, string>;
  directory?: string;
}): Promise<{
  cwd: string;
  cleanup: () => Promise<void>;
}>
```

**What the E2E tests cover**:

| Test file | What it tests |
|-----------|---------------|
| `init.test.ts` | Creates `blocks.json` with correct structure. Prompts for directory. Prompts for registry name + URL. Handles existing `blocks.json` (overwrite prompt). |
| `add.test.ts` | Downloads block files to correct directory. Resolves `namespace/block` syntax. Auto-resolves when block exists in one registry. Installs dependencies recursively. Skips already-installed blocks. Overwrites when confirmed. Respects `--yes` flag. Respects `--dry-run` (no files written). Handles multiple blocks in one command. |
| `conflict.test.ts` | Prompts when block name exists in multiple registries. User can select which one. Selection installs the correct block. |
| `list.test.ts` | Shows all blocks from all registries. Filters by namespace. Shows block count, names, categories. |
| `search.test.ts` | Matches by name, title, description. Ranks exact name matches higher. Shows which registry each result comes from. |
| `remove.test.ts` | Deletes the block folder. Prompts for confirmation. Aborts on decline. Handles non-existent block. |

**Error scenarios** (tested across commands):

- No `blocks.json` found: clear error message, suggests `init`.
- Registry URL returns 404: error names the registry and URL.
- Registry JSON is malformed: error shows validation failures.
- Network offline: timeout with helpful message.
- Block not found in any registry: lists available blocks as suggestions.
- Target directory not writable: permission error.

### Fixture blocks (`tests/fixtures/`)

Minimal but realistic. Two registries (`ui` and `starter`) with overlapping block names (both have `hero`) to test conflict resolution. Blocks contain real WordPress block files (valid `block.json`, minimal PHP templates) so the test output is representative.

### Running tests

```bash
cd registry
npm test              # Run all tests
npm run test:unit     # Unit tests only
npm run test:e2e      # E2E tests only (requires build first)
npm run test:watch    # Watch mode (unit tests)
```

E2E tests depend on a built CLI (`npm run build` first). The test script handles this automatically.

## Tasks

### Phase 1: Foundation + test infrastructure

- [ ] Set up project: add Ink, Zod, chalk, vitest, execa to dependencies. Update tsup config for JSX.
- [ ] Set up vitest config with separate unit/e2e projects.
- [ ] Create test fixtures: two registries (`ui`, `starter`) with overlapping blocks, valid block files.
- [ ] Create test helpers: `cli.ts` (spawn + interact), `fixtures.ts` (local HTTP server), `project.ts` (temp dirs).
- [ ] Define Zod schemas for `blocks.json` and `registry.json` in `config/schema.ts`.
- [ ] Unit tests for schemas: valid parses, invalid rejects with correct paths, edge cases.
- [ ] Build config loader: find `blocks.json` by walking up from cwd, parse + validate.
- [ ] Unit tests for loader: finds config N levels up, missing config error, malformed JSON.
- [ ] Build registry fetcher: fetch URL, validate against schema, cache in memory.
- [ ] Build dependency resolver: given a block name, return flat list in install order (Kahn's algorithm).
- [ ] Unit tests for resolver: linear deps, diamond deps, circular detection, missing dep error.
- [ ] Build file downloader: fetch each file from `{baseUrl}/{name}/{file}`, return content map.
- [ ] Build file writer: create directories, write files, handle overwrite prompts.

### Phase 2: Core commands + E2E tests

- [ ] `init` command: Ink prompts for directory + registries, write `blocks.json`.
- [ ] E2E test for init: creates valid `blocks.json`, handles existing config.
- [ ] `add` command: full flow from parse to install with Ink UI (spinner, select, summary).
- [ ] E2E test for add: files on disk, namespace resolution, dependency install, overwrite prompt.
- [ ] E2E test for conflicts: block in multiple registries triggers select prompt.
- [ ] `list` command: fetch registries, render table.
- [ ] E2E test for list: output contains block names, categories, registry grouping.
- [ ] `search` command: fuzzy match across registries by name, title, description.
- [ ] E2E test for search: matches by name/title/description, shows registry source.
- [ ] `remove` command: delete block folder with confirmation.
- [ ] E2E test for remove: folder deleted, confirmation prompt, abort on decline.

### Phase 3: Polish + error E2E tests

- [ ] Error handling: offline, 404, invalid JSON, missing config, permission errors.
- [ ] E2E tests for every error path: no config, bad URL, malformed registry, block not found, offline.
- [ ] `--yes` flag to skip all prompts (CI usage).
- [ ] `--dry-run` flag to preview what would be installed.
- [ ] E2E tests for `--yes` and `--dry-run` flags.
- [ ] Add multiple blocks in one command: `npx blockstudio add ui/tabs ui/accordion`.
- [ ] Colorized, clean Ink output with consistent formatting.

### Phase 4: Ecosystem

- [ ] Publish JSON schemas for `blocks.json` and `registry.json` to blockstudio.dev.
- [ ] Documentation page on creating and hosting a registry.
- [ ] Example registry with a handful of starter blocks.
- [ ] `npx blockstudio add --url <direct-url>` for one-off installs without a configured registry.

## Reference

shadcn CLI source is in `_references/shadcn/` (gitignored). Key files:

| What | Path |
|------|------|
| Add command | `_references/shadcn/src/commands/add.ts` |
| Registry fetcher | `_references/shadcn/src/registry/fetcher.ts` |
| Dependency resolver | `_references/shadcn/src/registry/resolver.ts` |
| URL builder | `_references/shadcn/src/registry/builder.ts` |
| Schemas | `_references/shadcn/src/registry/schema.ts` |
| Config loader | `_references/shadcn/src/utils/get-config.ts` |
| File writer | `_references/shadcn/src/utils/updaters/update-files.ts` |

The shadcn model is more complex because it transforms source code (JSX, imports, CSS variables, icon libraries). Our model is simpler: we copy folders as-is. No transforms needed. A WordPress block folder is the same everywhere.
