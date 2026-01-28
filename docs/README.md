# Blockstudio 7 Documentation

## Overview

**Blockstudio** is a WordPress block framework plugin that enables developers to create custom Gutenberg blocks using a filesystem-based approach. Version 7 is a modernized, open-source rewrite following **100% WordPress Coding Standards**.

## Quick Start

```bash
# Run v7 tests
npm run playground:v7    # Start server (port 9401)
npm run test:v7          # Run 14 snapshot tests

# Check coding standards
composer cs              # Run PHPCS
composer cs:fix          # Auto-fix issues
```

## Project Structure

```
blockstudio7/
├── blockstudio.php          # Entry point (v7)
├── includes/                # PHP classes (v7)
│   ├── class-plugin.php     # Main singleton
│   ├── classes/             # All plugin classes
│   ├── interfaces/          # PHP interfaces
│   └── functions/           # Public API
├── package/                 # Frontend code
├── _reference/              # v6 codebase (gitignored, for snapshot comparison)
├── tests/                   # Test infrastructure
│   ├── unit/                # Snapshot tests
│   ├── e2e/                 # End-to-end tests
│   ├── blocks/              # 100+ test blocks
│   └── snapshots/           # Snapshot data
├── docs/                    # Documentation (you are here)
└── CLAUDE.md                # AI assistant instructions
```

## Documentation Index

| Document | Description |
|----------|-------------|
| [Architecture](./architecture.md) | Project structure, singleton pattern, class design |
| [Testing](./testing.md) | Snapshot testing, Playground setup, commands |
| [Migration](./migration.md) | Class migration workflow, WPCS checklist |
| [Conventions](./conventions.md) | Coding standards, comment policies |

## Goals

1. **Open Source Release** - GPL2 licensed, WordPress.org compatible
2. **Architecture Modernization** - Singleton pattern, clean separation
3. **Test-Driven Refactoring** - WordPress Playground integration tests
4. **100% WordPress Coding Standards** - No exceptions

## Key Commands

| Command | Description |
|---------|-------------|
| `npm run playground:v7` | Start v7 Playground server (port 9401) |
| `npm run test:v7` | Run v7 unit tests |
| `npm run playground` | Start v6 reference server (port 9400) |
| `npm test` | Run v6 reference tests |
| `composer cs` | Check WordPress coding standards |
| `composer cs:fix` | Auto-fix coding standard issues |

## Directory Roles

| Directory | Purpose |
|-----------|---------|
| `includes/` | **Primary v7 code** - All development happens here |
| `_reference/` | **Read-only v6 reference** - Never modify, used for snapshot baseline |
| `package/` | Frontend code (React components, copied from v6) |
| `tests/` | All test infrastructure |
