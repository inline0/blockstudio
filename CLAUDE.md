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
├── _reference/          # v6 reference (gitignored, for snapshots)
├── tests/               # Test infrastructure
└── docs/                # Detailed documentation
```

## Documentation

| Document | Description |
|----------|-------------|
| [docs/README.md](docs/README.md) | Overview and quick start |
| [docs/architecture.md](docs/architecture.md) | Singleton pattern, class design, Build decomposition |
| [docs/testing.md](docs/testing.md) | Snapshot testing, Playground setup, ports |
| [docs/migration.md](docs/migration.md) | Class migration workflow, WPCS checklist |
| [docs/conventions.md](docs/conventions.md) | WordPress coding standards, JS/TS policies |

## Key Rules for Claude

1. **Run tests** after changes: `npm run test:v7`
2. **Never modify `_reference/`** - read-only v6 baseline
3. **100% WordPress Coding Standards** - no exceptions
4. **One class at a time** - migrate and test incrementally

## Commands

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start v7 server (port 9401) |
| `npm run test:v7` | Run v7 tests (14 tests) |
| `npm run playground` | Start v6 reference server (port 9400) |
| `composer cs` | Check PHPCS |
| `composer cs:fix` | Auto-fix PHPCS issues |

## Ports

| Server | Port |
|--------|------|
| v7 Unit Tests | 9401 |
| v6 Reference | 9400 |
| v7 E2E | 9411 |
| v6 E2E | 9410 |
