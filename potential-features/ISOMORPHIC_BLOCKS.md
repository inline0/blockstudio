# Isomorphic Blocks — Client-Side Twig Rendering

## Problem

Every attribute change in the editor triggers a REST API round-trip (`POST /blockstudio/v1/gutenberg/block/render/{name}`), adding ~500ms+ latency per edit. For simple Twig templates that only use raw attributes (`a.text`, `a.color`, etc.), this server call is unnecessary.

## Solution

Add `"isomorphic": true` to `blockstudio` in `block.json`. When enabled:
- **Editor**: Render Twig templates client-side using [Twing](https://www.npmjs.com/package/twing) — no REST API calls on attribute changes
- **Frontend**: Unchanged — server-side rendering via Timber/Twig as before
- **Only works with `.twig` templates** — PHP and Blade templates require server execution

## Architecture

```
Current:  attribute change → 500ms debounce → REST API → Timber renders → HTML → parseBlock()
New:      attribute change → 100ms debounce → Twing renders → HTML → parseBlock()
```

The existing `parseBlock()` pipeline (RichText/InnerBlocks/MediaPlaceholder/useBlockProps replacements) is reused as-is. The only change is where the HTML comes from.

## Files to Modify

| File | Change |
|------|--------|
| `includes/classes/block-registrar.php` | Pass `native_path` to metadata builder; add `isomorphic` flag + `templateSource` to metadata |
| `src/blocks/components/block/index.tsx` | Add Twing rendering path alongside `fetchData` |
| `src/blocks/index.tsx` | Initialize Twing environments per isomorphic block at registration time |
| `src/types/block.ts` | Add `isomorphic` and `templateSource` to `BlockstudioClass` interface |
| `docs/src/schemas/schema.ts` | Add `isomorphic` property to blockstudio schema |
| `docs/content/docs/isomorphic.mdx` | New documentation page for isomorphic rendering |
| `readme.txt` | Changelog entry |
| `package.json` | Add `twing` dependency |
| `webpack.config.cjs` | No changes expected (Twing bundles normally via webpack) |

## New Files

| File | Purpose |
|------|---------|
| `src/blocks/isomorphic.ts` | Twing initialization + render function module |
| `tests/theme/blockstudio/isomorphic/default/block.json` | Test block with `isomorphic: true` |
| `tests/theme/blockstudio/isomorphic/default/index.twig` | Simple Twig template for testing |
| `tests/e2e/isomorphic.ts` | E2E tests for isomorphic rendering |

## Implementation

### 1. Install Twing

```bash
npm install twing
```

Twing is a pure-JS Twig engine (~1.1MB bundled). It supports the full Twig syntax including filters, conditionals, loops, and includes. Bundled directly via webpack — no separate entry point needed.

### 2. `src/blocks/isomorphic.ts` — Twing module

New module that encapsulates all Twing logic:

```typescript
import { TwingEnvironment, TwingLoaderArray } from 'twing';

const environments = new Map<string, TwingEnvironment>();

export function initIsomorphic(blockName: string, templateSource: string) {
  const loader = new TwingLoaderArray({ template: templateSource });
  const env = new TwingEnvironment(loader, { autoescape: false });
  environments.set(blockName, env);
}

export async function renderIsomorphic(
  blockName: string,
  attributes: Record<string, unknown>,
  block: Record<string, unknown>,
  context: Record<string, unknown>,
): Promise<string> {
  const env = environments.get(blockName);
  if (!env) throw new Error(`No isomorphic environment for ${blockName}`);

  return env.render('template', {
    attributes,
    a: attributes,
    block,
    b: block,
    context,
    c: context,
    isEditor: true,
    isPreview: false,
  });
}

export function hasIsomorphic(blockName: string): boolean {
  return environments.has(blockName);
}
```

Context variables mirror the PHP side (`block.php` lines 1241-1251): `a`/`attributes`, `b`/`block`, `c`/`context`, `isEditor`, `isPreview`. v1 skips `content`, `postId`, `post_id` (not available client-side without server).

### 3. `includes/classes/block-registrar.php` — Metadata changes

**`build_blockstudio_metadata()`** (line 261): Add `native_path` parameter and two new fields:

```php
private function build_blockstudio_metadata(
    WP_Block_Type $block,
    array $filtered_attributes,
    array $block_json,
    string $native_path    // NEW parameter
): array {
    $is_isomorphic = ! empty( $block_json['blockstudio']['isomorphic'] )
        && str_ends_with( $native_path, '.twig' );

    $template_source = null;
    if ( $is_isomorphic ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
        $template_source = file_get_contents( $native_path );
    }

    return array(
        'attributes'     => $filtered_attributes,
        'blockEditor'    => array(
            'disableLoading' => $disable_loading,
        ),
        'conditions'     => $block->blockstudio['conditions'] ?? true,
        'editor'         => $block->blockstudio['editor'] ?? false,
        'extend'         => $block->blockstudio['extend'] ?? false,
        'group'          => $block->blockstudio['group'] ?? false,
        'icon'           => $block->blockstudio['icon'] ?? null,
        'isomorphic'     => $is_isomorphic,           // NEW
        'refreshOn'      => $block->blockstudio['refreshOn'] ?? false,
        'templateSource' => $template_source,          // NEW (null if not isomorphic)
        'transforms'     => $block->blockstudio['transforms'] ?? false,
        'variations'     => $block->variations ?? false,
    );
}
```

**Call site** (line 145): Pass `$native_path`:

```php
$block->blockstudio = $this->build_blockstudio_metadata(
    $block,
    $filtered_attributes,
    $block_json,
    $native_path    // NEW
);
```

Key design: `isomorphic` is only `true` when both `blockstudio.isomorphic: true` in block.json AND the template is `.twig`. PHP/Blade templates silently ignore the flag.

### 4. `src/blocks/index.tsx` — Initialize Twing at registration

After line 37 (inside the `forEach` loop), before `registerBlockType`:

```typescript
if (block.blockstudio?.isomorphic && block.blockstudio?.templateSource) {
  initIsomorphic(block.name, block.blockstudio.templateSource);
}
```

Import at top: `import { initIsomorphic } from './isomorphic';`

### 5. `src/blocks/components/block/index.tsx` — Isomorphic render path

**New function** alongside `fetchData` (~line 226):

```typescript
const renderLocally = async () => {
  const html = await renderIsomorphic(
    block.name,
    attributes,
    {
      name: block.name,
      blockstudio: block.blockstudio,
    },
    context,
  );
  parseBlock({ rendered: html });
  loaded();
};
const debouncedRenderLocally = useDebounce(renderLocally, 100);
```

Import: `import { renderIsomorphic, hasIsomorphic } from '../isomorphic';` (adjust relative path)

**`onAttributeChange` effect** (line 379): Route to local or server:

```typescript
useEffect(
  function onAttributeChange() {
    const newAttributes = cloneDeep(attributes) as Record<string, Any>;
    Object.keys(attributes).forEach((key) => {
      if (key.startsWith('BLOCKSTUDIO_RICH_TEXT')) {
        delete newAttributes[key];
      }
    });

    if (
      JSON.stringify(attributesRef.current) === JSON.stringify(newAttributes)
    ) {
      return;
    }
    attributesRef.current = newAttributes as BlockstudioBlockAttributes;
    if (firstRenderDone.current) {
      if (hasIsomorphic(block.name)) {
        debouncedRenderLocally();
      } else {
        debouncedFetchData();
      }
    }
  },
  [attributes],
);
```

**`onContextChange` effect** (line 401): Same routing:

```typescript
useEffect(
  function onContextChange() {
    if (firstRenderDone.current) {
      if (hasIsomorphic(block.name)) {
        debouncedRenderLocally();
      } else {
        debouncedFetchData();
      }
    }
  },
  [context],
);
```

**Initial load**: Isomorphic blocks still use the server-side batch render (`/render/all`) for the first load. This ensures `postId`, `content`, and other server-only context is available on page load. Isomorphic mode only applies to subsequent attribute/context changes.

### 6. `src/types/block.ts` — Type updates

Add to `BlockstudioClass` (after `innerBlocks` at line 257):

```typescript
/**
 * Enable client-side Twig rendering in the editor. Only works with .twig templates.
 */
isomorphic?: boolean;
/**
 * Raw Twig template source for client-side rendering. Set automatically by PHP when isomorphic is true.
 */
templateSource?: string;
```

### 7. `docs/src/schemas/schema.ts` — Schema update

Add after `innerBlocks` property (line ~1061):

```typescript
isomorphic: {
  type: "boolean",
  description: "Enable client-side Twig rendering in the editor. Only works with .twig templates. Server-side rendering is unchanged.",
},
```

### 8. Test block

**`tests/theme/blockstudio/isomorphic/default/block.json`:**
```json
{
  "$schema": "https://app.blockstudio.dev/schema",
  "name": "blockstudio/isomorphic-default",
  "title": "Isomorphic Default",
  "category": "blockstudio-test-native",
  "icon": "star-filled",
  "description": "Tests isomorphic client-side Twig rendering.",
  "blockstudio": {
    "isomorphic": true,
    "attributes": [
      {
        "id": "heading",
        "type": "text",
        "label": "Heading",
        "default": "Default Heading"
      },
      {
        "id": "description",
        "type": "textarea",
        "label": "Description",
        "default": "Default description text."
      }
    ]
  }
}
```

**`tests/theme/blockstudio/isomorphic/default/index.twig`:**
```twig
<div class="blockstudio-test__block isomorphic-test">
  <h2>{{ a.heading }}</h2>
  <p>{{ a.description }}</p>
</div>
```

### 9. E2E Tests

**`tests/e2e/isomorphic.ts`** — 4 tests:

| # | Test | Verifies |
|---|------|----------|
| 1 | Isomorphic block renders on initial load | Block visible in editor with default attributes |
| 2 | Attribute change re-renders without server call | Change heading text, verify new text appears without network request |
| 3 | Rendered HTML uses parseBlock pipeline | useBlockProps/RichText placeholders are replaced correctly |
| 4 | Frontend renders via server-side Timber | Visit frontend URL, verify SSR output matches |

Test #2 is the key assertion — intercept network requests and verify no REST API call is made to `/blockstudio/v1/gutenberg/block/render/` after an attribute change.

### 10. Documentation + Changelog

**New file `docs/content/docs/isomorphic.mdx`** — dedicated docs page:
- Title: "Isomorphic Rendering"
- What it is and the architecture (server vs client rendering)
- How to enable (`"isomorphic": true` in blockstudio)
- Context variables available client-side (`a`/`attributes`, `b`/`block`, `c`/`context`, `isEditor`)
- Limitations (Twig only, no server-side context like `postId`/`content` on re-renders, no Timber functions)
- When to use (simple presentation blocks) vs when to keep server rendering (blocks needing post data, Timber, custom filters)
- Example block.json + index.twig

Add `"isomorphic"` to `docs/content/docs/meta.json` in the Blocks section (after `"rendering"`).

**`readme.txt`:** Changelog entry.

## Limitations (v1)

| Limitation | Reason |
|------------|--------|
| No `postId`/`post_id` in re-renders | Not available client-side |
| No `content` (InnerBlocks HTML) in re-renders | Would need separate serialization |
| No Timber functions (`TimberPost`, etc.) | Server-only |
| No custom Twig functions/filters added by Timber or plugins | Twing is standalone |
| Initial load still uses server | Ensures full context on first render |

These are acceptable for v1. Most isomorphic blocks are presentation blocks that only use `a.fieldName` — they don't need server context on attribute changes.

## Edge Cases

| Scenario | Behavior |
|----------|----------|
| `isomorphic: true` on PHP template | Silently ignored — `str_ends_with('.twig')` check fails |
| `isomorphic: true` on Blade template | Silently ignored — same check |
| Twig syntax error in template | Twing throws at render time — catch and fall back to server render |
| Block uses `{{ postId }}` | Works on initial load (server), renders empty/undefined on subsequent client re-renders |
| `refreshOn` with isomorphic | Still works — custom event triggers local render instead of server fetch |

## Verification

```bash
# Install dependency
npm install twing

# TypeScript
npx tsc --noEmit

# PHPCS
composer cs

# Build
npm run build

# Run isomorphic E2E tests
npx playwright test tests/e2e/isomorphic.ts --config=playwright.wp-env.config.ts --retries=0

# Run all E2E tests (regression)
npm run test:e2e

# Run unit tests (snapshot — may need refresh after adding test block)
npm run playground:unit && npm run test:unit
```
