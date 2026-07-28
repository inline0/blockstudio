---
title: AI Integration
description: An addressable documentation index for coding agents, plus a generated contract for your own project.
path: "dev/ai"
order: 66
section: "Dev"
subsection: "Integrations"
meta_title: "AI Integration"
meta_description: "An addressable documentation index for coding agents, plus a generated contract for your own project."
---

# AI Integration

Blockstudio ships two context files for coding assistants. The primary one is a
compact index of the documentation. The second is the full text of every
document and schema, for tools that want the whole corpus in one request.

An agent reads the index, finds the one document it needs, and opens that. The
alternative is handing an agent half a megabyte of undifferentiated prose, where
nothing is addressable and everything is loaded whether it is relevant or not.

## The index

`blockstudio-llm.txt` lists every published document once, grouped by the
section and subsection it belongs to, in navigation order. Each entry carries:

- `route`: the documentation URL.
- `file`: the Markdown source path in the Blockstudio repository.
- `purpose`: one line describing what the document is for.
- `settings`: the `blockstudio.json` paths that document owns.
- `hooks`: the PHP and JavaScript filter and action names it owns.
- `php`: the functions, classes, and methods it owns.
- `cli`: the commands it owns.

```text
## Docs / Dev / Inspection Tools

### Canvas
route: /docs/dev/canvas
file: docs/docs/dev/canvas.md
purpose: A visual workspace and public inventory API for Blockstudio content.
settings: dev/canvas/enabled, ui
hooks: blockstudio/settings/dev/canvas/admin_bar, blockstudio/settings/dev/canvas/enabled
php: Blockstudio\Canvas, Canvas::documents(), Canvas::inventory()
```

An identifier line is only written when the document actually owns something of
that kind, so the entry above has no `cli` line while the static prerendering
page owns the whole prerender command family.

The identifier lines are what make the index answerable. An agent looking for
the hook that fires before a block renders scans the hook lines and lands on
[PHP Hooks](/docs/blocks/hooks/php), which owns the complete filter and action
vocabulary. An agent looking for static prerendering scans for the same word and
lands on [Static Prerendering](/docs/production/static-prerendering) and
[Settings](/docs/general/settings), which own the counters and the configuration
respectively. The five public JSON schemas are listed the same way, with their
route and their file.

Identifiers are read from prose, inline code, and code examples. Blocks marked
`text` hold literal output and directory listings, so they are skipped: a
document that prints a command's output does not thereby own that command.

## The full text

`blockstudio-llm-full.txt` is the complete documentation and every schema in one
file. It is still generated, still published, and still addressable, but it is
no longer what an agent is handed first, and it says so in its own header.

## How they are built

Both files are assembled at build time by `npm run build:llm`, which runs
`scripts/build-llm.ts`. The script:

1. Reads the frontmatter of every published document in the `docs`, `guides`,
   and `registry` collections for its title, route, and purpose.
2. Extracts the identifiers each document owns from its own body: hook names
   from filter and action calls, settings paths validated against the settings
   schema, PHP functions and classes, and CLI commands.
3. Writes the index to `includes/llm/blockstudio-llm.txt`.
4. Walks the documentation tree in navigation order, strips navigation-only
   Markdown while preserving headings and code examples, appends the JSON
   schemas, and writes the full text to
   `includes/llm/blockstudio-llm-full.txt`.

`npm run docs:check` fails when a published document is missing from the index,
or when the index points at a file that no longer exists.

## How to use them

1. Enable the `ai/enableContextGeneration` setting in your `blockstudio.json`:

```json title="blockstudio.json"
{
  "ai": {
    "enableContextGeneration": true
  }
}
```

2. The files are now available at `your-site.com/blockstudio-llm.txt` and
   `your-site.com/blockstudio-llm-full.txt`.

3. Point your AI tool at the index:
   - **Cursor**: add the URL as a doc in your project settings.
   - **Claude Code**: reference the URL or download the file and add it to your project context.
   - **GitHub Copilot**: include the file in your repository or reference it in your instructions.

Any tool that accepts a URL or text file as context will work. Both files are static and do not include site-specific data like your registered blocks or current settings.

## Your project's own contract

The index describes Blockstudio. It does not describe your project, which is the
other half of what an agent needs: where blocks and pages live, which features
are enabled, and what analysis will reject. `vendor/bin/blockstudio-agents`
generates that from your `blockstudio.json` and your own files. See
[the project contract](/docs/dev/phpstan#project-contract-for-coding-agents).
