# Blockstudio UI

Headless UI component library for WordPress, built entirely on the Interactivity API. 60 components ported from [Base UI](https://base-ui.com), plus demo apps that showcase them. Component source lives in `includes/ui`; this test harness enables the bundled UI feature from the theme's `blockstudio.json`.

## Quick Reference

```bash
npm run wp-env:start                        # Start wp-env and activate the UI theme (port 8879)
npm test                                    # Run all Playwright tests (493 tests)
npm test -- --grep "tabs"                   # Run specific tests
npx playwright test tests/ui/tabs.spec.ts --config=playwright.config.ts  # Run one file
node screenshots.mjs                        # Capture component screenshots
```

## Project Structure

```
includes/ui/
  blocks/                # UI components (bsui/* namespace, 60 components)
  apps/                  # Full applications built with the components

tests/ui/
  theme/
    interactions/        # Test-only multi-component scenarios (standalone blocks)
    pages/               # Test pages (one per component/app/interaction)
  tests/
    ui/                  # Component tests (34 files)
    apps/                # App tests (kitchen-sink)
    interactions/        # Interaction tests (8 files)
    helpers/
      dual-context.ts    # Runs tests in both frontend and editor contexts
  playwright.config.ts   # Port 8879, Chromium only
```

## Architecture

`tests/ui/theme/blockstudio.json` enables bundled UI components:

```json
{
	"ui": {
		"enabled": true
	}
}
```

When enabled, `Blockstudio\Ui` registers the bundled component directories:

```php
Blockstudio\Build::init( array( 'dir' => BLOCKSTUDIO_DIR . '/includes/ui/blocks' ) );
Blockstudio\Build::init( array( 'dir' => BLOCKSTUDIO_DIR . '/includes/ui/apps' ) );
```

The theme harness registers only the test fixtures from `tests/ui/theme/interactions`.

### Component Types

**Single-part** (e.g. `button/`): one directory with `block.json` + `index.php`.

**Compound** (e.g. `tabs/`): root block owns state and store. Child blocks declare `"parent"` and `"usesContext"` in block.json. Root uses `<InnerBlocks>`, pages compose children with `<block>` syntax.

**Apps** (e.g. `chat/`): compound blocks with DB, RPC, and full application logic. Same parent/child pattern.

### Namespaces

- `bsui/*` for UI components: `bsui/tabs`, `bsui/button`, `bsui/drawer`
- `app/*` for apps: `app/chat`, `app/chat-sidebar`
- `interaction/*` for interactions: `interaction/checkbox-counter-sync`

## Building SSR-first Interactivity API Apps

### block.json must declare interactivity support

```json
{
  "supports": { "interactivity": true },
  "blockstudio": { "interactivity": { "enqueue": true } }
}
```

Without `"supports": { "interactivity": true }`, WordPress core will NOT process `data-wp-each`, `data-wp-bind`, `data-wp-text`, etc. server-side. This was a major gotcha.

### State getters do not SSR

JS getters like `get isUserMessage() { return getContext().msg.role === 'user' }` cannot be evaluated server-side. WordPress can only SSR direct state/context values.

**Bad** (getter, will not SSR):
```html
<div data-wp-class--chat-msg-user="state.isUserMessage">
```

**Good** (direct context value, SSRs correctly):
```html
<div data-wp-bind--data-role="context.msg.role">
```

Then use CSS attribute selectors instead of class-based selectors:
```css
[data-role="user"] { justify-content: flex-end; }
[data-role="assistant"] { justify-content: flex-start; }
```

### PHP state values for SSR, JS getters for client-side

Set computed values in PHP via `wp_interactivity_state()`. JS getters with the same name take over after hydration.

```php
wp_interactivity_state( 'app/chat', array(
    'currentChatTitle' => $current_title,
    'cannotSend'       => true,
) );
```

**Read-only getters** (never assigned to) can coexist with PHP state values. The PHP value seeds SSR, the getter overrides client-side:

```js
state: {
    get cannotSend() {
        return ! getContext().input.trim() || getContext().sending;
    },
}
```

**Writable state** must NOT have a getter. If you need to assign `state.foo = value` in an action, do not define `get foo()`. Use the PHP value directly and update it manually in actions.

This prevents `data-wp-bind` flicker: SSR resolves the PHP value (e.g. `disabled` renders on first paint), then the getter takes over reactively. Without the PHP seed, SSR can't resolve the getter and strips the attribute, causing a flash of enabled state before JS hydrates.

### Active states via PHP flags

Add boolean flags to array items in PHP, bind with data attributes, style with CSS:

```php
$chats_with_active = array_map( function ( $chat ) use ( $current_chat_id ) {
    $chat['active'] = (int) $chat['id'] === $current_chat_id;
    $chat['url']    = $page_url . '?chat=' . $chat['id'];
    return $chat;
}, $chats );
```

```html
<div data-wp-bind--data-active="context.chat.active">
```

```css
[data-active="true"] { background: var(--bs-ui-accent); }
```

### Links work without JS

Add URL fields to state items in PHP. Use `href="#"` with `data-wp-bind--href` so SSR resolves the real URL and the link works without JS. `event.preventDefault()` in the JS action prevents navigation when JS is available.

```html
<bs:bsui-button href="#" data-wp-bind--href="context.chat.url" data-wp-on--click="actions.selectChat" />
```

### Cross-namespace component reuse

When rendering a sub-block inside another component's namespace (e.g. sidebar inside a drawer), add explicit `data-wp-interactive` to the sub-block root. Otherwise it inherits the parent's namespace and state/actions will not resolve.

```html
<!-- header/index.php: sidebar rendered inside bsui/drawer -->
<bs:bsui-drawer-popup flush="true">
    <bs:app-chat-sidebar />
</bs:bsui-drawer-popup>
```

The sidebar template has `data-wp-interactive="app/chat"` on its root so actions like `actions.selectChat` resolve in the correct store.

### Compound blocks follow the tabs pattern

Root block owns state, DB, RPC, and the store. Child blocks declare their parent:

```json
{
  "name": "app/chat-sidebar",
  "parent": ["app/chat"],
  "usesContext": ["app/chat"]
}
```

Page template composes children:

```html
<block name="app/chat">
    <block name="app/chat-sidebar"></block>
    <block name="app/chat-header"></block>
    <block name="app/chat-messages"></block>
    <block name="app/chat-input"></block>
</block>
```

### CSS layout with display: contents

Blockstudio wraps InnerBlocks in a `<div>`. Add `display: contents` so the wrapper does not break CSS grid/flex layouts:

```css
[data-app-chat] > div { display: contents; }
```

### Drawer flush prop

Use `flush="true"` on `<bs:bsui-drawer-popup>` to remove default padding when nesting full-width content like sidebars.

## Component Pseudo-syntax

Components use a pseudo-HTML syntax in `index.php` templates:

```html
<bs:bsui-button label="Send" variant="ghost" size="icon" />
<bs:bsui-textarea placeholder="Type a message..." rows="3" />
```

- Attributes map to `block.json` attributes
- `html-` prefix passes attributes to the rendered HTML element: `html-data-chat-send`
- `data-wp-*` directives pass through to the output: `data-wp-on--click="actions.send"`
- `<bs:bsui-drawer-popup flush="true">` wraps InnerBlocks content

## DB and RPC

### Database

`db.php` returns an associative array defining schemas:

```php
return array(
    'chats' => array(
        'storage'    => 'jsonc',
        'capability' => array( 'create' => true, 'read' => true, 'update' => true, 'delete' => true ),
        'fields'     => array(
            'title' => array( 'type' => 'string', 'required' => true, 'maxLength' => 200 ),
        ),
    ),
);
```

Data files live in `root/db/` as `.jsonc` files (one JSON object per line).

**PHP:** `Blockstudio\Db::get( 'app/chat', 'chats' )` returns a DB instance with `->list()`, `->create()`, `->delete()`.

**JS:** `bs.db( 'app/chat', 'chats' )` returns the same interface client-side with `.list()`.

### RPC

`rpc.php` returns an array of named procedures:

```php
return array(
    'send' => array(
        'callback' => function ( array $params ) { ... },
        'public'   => true,
        'methods'  => array( 'POST' ),
    ),
);
```

**JS:** `bs.fn( 'send', { chat_id: 1, content: 'hello' }, 'app/chat' )` calls the procedure. Generator functions (`*sendMessage()`) use `yield` for async RPC calls.

## Testing

493 tests across 43 files. All tests use Playwright against the wp-env instance on port 8879.

### Dual-context testing

The helper at `tests/helpers/dual-context.ts` runs every test in both frontend and editor contexts:

```ts
const contexts = createContexts( 'kitchen-sink', '[data-app-kitchen-sink]' );

for ( const { name, setup } of contexts ) {
    test.describe( `app/kitchen-sink (${ name })`, () => {
        let ctx: TestContext;
        test.beforeEach( async ( { page } ) => { ctx = await setup( page ); } );
        test( 'SSR: checkbox renders', async () => { ... } );
    } );
}
```

Frontend tests hit the page URL directly. Editor tests log into wp-admin, open the post editor, and interact inside the editor iframe.

### Test directories

| Directory | What it tests |
|-----------|--------------|
| `tests/ui/` | Individual components (tabs, dialog, select, etc.) |
| `tests/apps/` | Full applications (kitchen-sink) |
| `tests/interactions/` | Multi-component scenarios |

### Running tests

```bash
npm test                                    # All tests
npm test -- --grep "tabs"                   # Filter by name
npx playwright test tests/ui/tabs.spec.ts --config=playwright.config.ts  # One file
```

### Force sync after template changes

```bash
npx @wordpress/env run cli wp eval 'Blockstudio\Pages::force_sync_all();'
```

## CSS Design System

Global tokens in `blocks/global-style.css`. All variables use `--bs-ui-*` prefix. Light/dark mode via `:root` and `.dark` scopes with OKLCH colors. Key tokens:

- Colors: `--bs-ui-background`, `--bs-ui-foreground`, `--bs-ui-primary`, `--bs-ui-secondary`, `--bs-ui-muted`, `--bs-ui-accent`, `--bs-ui-destructive`, `--bs-ui-border`
- Radius: `--bs-ui-radius`, `--bs-ui-radius-sm`, `--bs-ui-radius-lg`
- Fonts: `--bs-ui-font-sans`, `--bs-ui-font-mono`
- Sizing: `--bs-ui-control-height`, `--bs-ui-control-padding`, `--bs-ui-font-size`

## Comment Policy

Same as parent repo:
- No JSDoc internally, comments only for why not what
- No banner comments (no `// ==========` separators)
- No em dashes in code, docs, or copy

## wp-env

Port **8879** (tests on 8880). Use OrbStack, not Docker Desktop (4.67.0 has a nested mount bug).

If mounts appear as empty directories after start, use the symlink fix:

```bash
npm run wp-env:stop
WP_DIR=$(grep -rl "8879" ~/.wp-env/*/docker-compose.yml | head -1 | sed 's|/docker-compose.yml||')
rmdir "$WP_DIR/WordPress/wp-content/plugins/blockstudio7"
rmdir "$WP_DIR/WordPress/wp-content/themes/theme"
ln -s "$(cd ../.. && pwd)" "$WP_DIR/WordPress/wp-content/plugins/blockstudio7"
ln -s "$(pwd)/theme" "$WP_DIR/WordPress/wp-content/themes/theme"
npm run wp-env:start
```

Never use `wp-env destroy` to fix mount issues.
