# Blockstudio 7

WordPress block framework plugin - v7 modernization with 100% WordPress Coding Standards.

## Quick Reference

```bash
# Run tests
npm run playground:v7 && npm run test:v7

# Check coding standards
composer cs
```

## Project Structure

```
blockstudio7/
├── blockstudio.php      # Entry point
├── includes/            # PHP classes (primary codebase)
├── package/             # Frontend code
├── docs/                # Documentation (OneDocs/Next.js)
├── _reference/          # v6 reference (gitignored, for snapshots)
└── tests/               # Test infrastructure
```

## Documentation

The `docs/` folder contains the documentation site built with OneDocs (Fumadocs + Next.js).

```bash
cd docs
npm install
npm run dev              # Start dev server on port 9700
npm run fetch-schemas    # Fetch schemas from API (run once for dev)
npm run generate         # Generate docs from schemas
npm run build            # Fetch schemas + generate + build
```

**Schema-driven docs:** Field types and settings filters are auto-generated from:
- `https://app.blockstudio.dev/schema/block`
- `https://app.blockstudio.dev/schema/blockstudio`
- `https://app.blockstudio.dev/schema/extend`

Generated content is injected between `{/* GENERATED_*_START */}` and `{/* GENERATED_*_END */}` markers in MDX files.

## Key Rules for Claude

1. **Run tests** after changes: `npm run test:v7`
2. **Never modify `_reference/`** - read-only v6 baseline
3. **100% WordPress Coding Standards** - no exceptions
4. **One class at a time** - migrate and test incrementally
5. **Never use `npx`** - always use `npm run` scripts from package.json

## Commands

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start v7 server (port 9701) |
| `npm run test:v7` | Run v7 unit/snapshot tests (not E2E) |
| `npm run playground` | Start v6 reference server (port 9706) |
| `composer cs` | Check PHPCS |
| `composer cs:fix` | Auto-fix PHPCS issues |

### Docs Commands (run from `docs/`)

| Command | Description |
|---------|-------------|
| `npm run dev` | Start docs dev server (port 9700) |
| `npm run fetch-schemas` | Fetch schemas from API |
| `npm run generate` | Generate MDX from schemas |
| `npm run build` | Full build (fetch + generate + next build) |

## Ports

| Server | Port |
|--------|------|
| Docs | 9700 |
| v7 Unit Tests | 9701 |
| v6 Reference | 9706 |
| v7 E2E | 9711 |
| v6 E2E | 9710 |
