# 005: Custom Field Types and Object Values

## Summary

Blockstudio has a mature field system, but the available editor controls are
closed over the field types that ship with core. Reusable custom fields already
exist through `field.json` and `type: "custom/{name}"`, but those only reuse
groups of existing fields. They do not let a theme or plugin register a new
editor control.

This PRD adds a first-class custom field type system for Blockstudio 7.5.0:

- third-party themes and plugins can register new field type metadata in PHP
- third-party editor scripts can register the matching React control in
  JavaScript
- custom field types can store primitive, array, or object values
- object values are treated as normal Blockstudio values in templates, storage,
  repeaters, custom fields, and extensions

The user-facing result: a consumer can register one field type such as
`divine/dimensions`, use it in `block.json`, and receive a stable object value
like `{ "top": "sm", "right": "md", "bottom": "sm", "left": "md" }` without
patching Blockstudio core or maintaining per-project forks.

## Product Context

Blockstudio users can already compose advanced blocks from built-in fields and
reusable custom field groups. The missing piece is a supported way for a theme
or plugin to provide a new editor control and make it behave like a native field
type everywhere Blockstudio understands attributes.

The feature must be implemented as one end-to-end contract:

- PHP registration defines the field type metadata
- editor registration supplies the matching React control
- attribute generation, REST, storage, rendering, schemas, docs, and PHPStan all
  understand the same type definition
- object and array values are preserved as real structured data
- tests prove the behavior in unit coverage and in the editor/browser flow

This should be built as a Blockstudio-native mechanism, not as a collection of
project-specific controls. Blockstudio ships the extension point. Consumers ship
the custom controls that use it.

## Current State

### Existing reusable custom fields

Blockstudio already supports reusable field definitions:

- `includes/classes/field-discovery.php` discovers `field.json` files
- `includes/classes/field-registry.php` stores reusable definitions
- `Build::expand_custom_fields()` expands `type: "custom/{name}"`
- filters exist for `blockstudio/fields/paths` and `blockstudio/fields`

These are reusable field groups. They are not custom field controls.

### Existing field type handling

Field types are hardcoded in several places:

- `includes/classes/field-type-config.php` has fixed type categories
- `includes/classes/attribute-builder.php` registers fixed PHP handlers
- `includes/classes/field-handlers/*` convert fields to WordPress attributes
- `src/blocks/components/fields/index.tsx` imports and renders a fixed control
  switch
- `docs/src/schemas/schema.ts` enumerates field type strings
- `packages/phpstan/src/Reflection/FieldTypeRegistry.php` maps known field types
  to template value types

There is also legacy attribute building in `Build::build_attributes()` that REST
still uses. A custom field type implementation must keep the modern
`Attribute_Builder` path and legacy REST build path aligned.

### Existing object values

Several built-in fields already return object-like values:

- `color`, `gradient`, `icon`, `link`, `radio`, and single `select`
- `files` can return number, object, or array depending on settings
- repeaters return arrays of field objects

What is missing is a generic path for custom object-valued field controls.

### Existing nested template parsing

`Extensions::parse_template()` already supports nested paths through
`Extensions::get()`, for example:

```php
Extensions::parse_template(
	'mt-{attributes.margin.top}',
	array(
		'attributes' => array(
			'margin' => array(
				'top' => 'sm',
			),
		),
	)
);
```

The custom field type work should preserve and test this behavior for
object-valued custom fields, especially in extensions.

## Goals

- Add a stable public API for registering custom field types from themes and
  plugins.
- Add an editor-side registry for matching custom React controls.
- Support raw object values without coercing them through select/radio/color
  normalization.
- Make custom field types work everywhere normal field types work:
  - regular blocks
  - extensions
  - reusable custom fields
  - groups
  - tabs
  - repeaters
  - Page Sync and markdown-backed pages
  - storage-backed fields
- Keep existing built-in field behavior unchanged.
- Keep `custom/{name}` reusable fields unchanged.
- Provide enough schema/docs/PHPStan coverage that the feature is usable without
  reading source code.
- Provide test coverage that proves editor save, reload, render, storage, and
  nested object values work.

## Non Goals

- Do not ship project-specific controls as built-in Blockstudio fields in v1.
  Blockstudio ships the mechanism. Consumers ship their own controls.
- Do not scan arbitrary plugin folders for `field.php` files.
- Do not introduce a second reusable custom field system.
- Do not broaden extension `set` behavior for normal blocks unless it is
  deliberately documented and tested. The v1 requirement is that object values
  work in templates and existing extension `set` rules.
- Do not add runtime filesystem scanning on frontend requests.

## Public API

### Field type names

Built-in field types keep their existing unnamespaced names:

- `text`
- `html-tag`
- `files`
- `repeater`

Third-party custom field types must use a namespaced lowercase dashcase name:

```text
namespace/type-name
```

Examples:

- `divine/dimensions`
- `divine/text-options`
- `acme/spacing-token`

Rules:

- lower-case ASCII only
- namespace and type segments use `^[a-z][a-z0-9-]*$`
- `custom/` is reserved for reusable custom field definitions
- `blockstudio/` is reserved for core
- invalid registrations are ignored by filters and rejected by helper functions

The editor must never use the raw type name directly as a CSS class suffix.
Add a sanitizing helper so `divine/dimensions` becomes a safe class/data suffix
such as `divine-dimensions`.

### PHP helper

Add public functions:

```php
bs_register_field_type( 'divine/dimensions', array(
	'attribute'     => 'object',
	'default'       => array(),
	'editor_script' => 'divine-field-dimensions',
	'editor_style'  => 'divine-field-dimensions',
	'storage'       => array(
		'type'        => 'object',
		'rest_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'string' ),
		),
	),
) );

bs_unregister_field_type( 'divine/dimensions' );
```

Return value:

- `true` when registration succeeds
- `false` when the name or definition is invalid

### PHP filter

Add a filter:

```php
add_filter( 'blockstudio/field_types', function ( array $types ): array {
	$types['divine/dimensions'] = array(
		'attribute'     => 'object',
		'default'       => array(),
		'editor_script' => 'divine-field-dimensions',
	);

	return $types;
} );
```

The helper and filter feed the same registry. The registry is the source of
truth for all PHP consumers.

### Field type definition

Supported definition keys:

| Key | Type | Required | Purpose |
| --- | --- | --- | --- |
| `attribute` | `string|array|null` | yes | WordPress attribute type. Valid values: `string`, `number`, `boolean`, `object`, `array`, or union array. |
| `default` | `mixed` | no | Registry-level default when a field does not define its own default. |
| `source` | `string` | no | WordPress attribute source, for rare custom cases. |
| `produces_attribute` | `boolean` | no | Defaults to `true`. Use `false` for display-only controls. |
| `editor_script` | `string|array` | no | Registered script handle(s) to enqueue in the block editor when the type is used. |
| `editor_style` | `string|array` | no | Registered style handle(s) to enqueue in the block editor when the type is used. |
| `storage` | `array` | no | Storage hints for post meta and options. |
| `supports` | `array` | no | Capability flags such as `options`, `multiple`, `media`, or future flags. |

Do not make arbitrary PHP callbacks part of v1 unless they are needed for a
specific proven use case. Keep the first public API data-shaped and stable.

### Storage definition

For storage-backed fields, custom field types need explicit storage typing:

```php
'storage' => array(
	'type'        => 'object',
	'rest_schema' => array(
		'type'                 => 'object',
		'additionalProperties' => true,
	),
)
```

Rules:

- `string`, `number`, `boolean`, `array`, and `object` must map to valid
  `register_post_meta()` and `register_setting()` declarations.
- `array` and `object` must provide proper `show_in_rest` schemas.
- object values must not silently fall back to string.
- repeater descendants keep the existing array-storage behavior.

### JavaScript registry

Expose an editor global:

```js
window.blockstudio.registerFieldType('divine/dimensions', {
	component: DimensionsField,
});

window.blockstudio.unregisterFieldType('divine/dimensions');
```

The component receives a stable props object:

```ts
type BlockstudioCustomFieldProps = {
	type: string;
	id: string;
	field: BlockstudioAttribute;
	value: unknown;
	defaultValue: unknown;
	onChange: (value: unknown) => void;
	attributes: BlockstudioBlockAttributes;
	block: BlockstudioBlock;
	clientId: string;
	inRepeater: boolean;
	repeaterId: string;
	disabled: boolean;
};
```

Also pass field definition properties through so controls can read custom keys:

```json
{
  "id": "margin",
  "type": "divine/dimensions",
  "label": "Margin",
  "sides": ["top", "right", "bottom", "left"]
}
```

Important: custom field `onChange()` must store the value directly. It must not
run through the built-in select/radio/color normalization path.

Unknown custom field type behavior:

- If PHP knows the type but no JS control is registered, render a small
  non-editable editor notice in development/admin contexts.
- Do not corrupt or clear saved values.
- Frontend rendering still receives the saved value.

## Architecture

### PHP registry

Add `includes/classes/field-type-registry.php`.

Responsibilities:

- store built-in and custom field type definitions
- validate names and definitions
- load filter registrations once per request
- expose `get()`, `all()`, `has()`, `register()`, `unregister()`, and `reset()`
  for tests

Update `Field_Type_Config` to ask the registry for custom field types instead
of hardcoding all classification decisions.

Do not remove the existing constants yet. Keep them for backward compatibility
and tests, but make helper methods aware of registered custom types.

### Attribute building

Add a generic custom field type handler after built-in handlers:

- if a built-in handler supports the type, use it
- otherwise, if the registry has the type and `produces_attribute !== false`,
  build a generic WordPress attribute from the registered definition
- copy safe field-level keys such as `default`, `fallback`, `source`, `storage`,
  and `set`
- preserve the original field type in the `field` key

Add object support through a generic object handler or fallback handler.

Update the legacy `Build::build_attributes()` path or route it through
`Attribute_Builder` so REST and registration produce identical output.

### Editor rendering

Add `src/blocks/components/fields/registry.ts`.

Responsibilities:

- hold registered custom controls
- expose `registerFieldType`, `unregisterFieldType`, and `getFieldType`
- attach registration methods to `window.blockstudio`

Update `src/blocks/components/fields/index.tsx`:

- before falling through to `null`, check the registry for the field type
- render custom fields inside the normal `Control` wrapper so labels,
  descriptions, help text, switches, disabled state, repeaters, and conditions
  behave like built-ins
- use direct `onChange()` storage for custom controls
- sanitize custom field type names for CSS classes

### Asset enqueueing

When a registered custom field type declares `editor_script` or `editor_style`,
Blockstudio should enqueue those handles in block editor contexts when the type
is used by any registered block, extension, or reusable custom field expansion.

Rules:

- Consumers register their own scripts/styles with WordPress.
- Blockstudio enqueues handles only. It does not infer paths or scan folders.
- Missing handles should trigger a development/admin warning but not fatal.
- No frontend request scanning.

### Rendering and templates

Template values should be available as normal Blockstudio attributes:

PHP:

```php
$a['margin']['top'] ?? null;
```

Twig:

```twig
{{ a.margin.top }}
```

Blade:

```blade
{{ $a['margin']['top'] ?? '' }}
```

Extension `set` rules must support object paths:

```json
{
  "set": [
    {
      "attribute": "class",
      "value": "mt-{attributes.margin.top} mb-{attributes.margin.bottom}"
    }
  ]
}
```

This must be covered by unit tests in `ExtensionsTest`.

### Storage

Update storage handlers:

- `includes/classes/storage-handlers/post-meta-storage.php`
- `includes/classes/storage-handlers/option-storage.php`

Required behavior:

- custom string field registers as `string`
- custom number field registers as `number`
- custom boolean field registers as `boolean`
- custom array field registers as `array` with REST schema
- custom object field registers as `object` with REST schema
- repeater descendants still use array storage

### PHPStan

Update `packages/phpstan/src/Reflection/FieldTypeRegistry.php` enough for v1:

- built-in behavior unchanged
- unknown custom field types default to `MixedType`
- if a custom field type definition can be statically loaded later, that can be
  a follow-up

Do not overpromise PHPStan precision for runtime filter registrations in v1.

### Schema and Types

Update:

- `docs/src/schemas/schema.ts`
- generated `src/types/block.ts`
- `src/types/types.ts`

Schema must accept:

- existing built-ins
- existing `custom/{name}` reusable field references
- namespaced custom field types in the form `namespace/type-name`

Schema must keep `custom/` reserved for reusable custom fields.

## Example Consumer Plugin

A minimal consumer plugin should look like this:

```php
add_action( 'init', function () {
	wp_register_script(
		'divine-field-dimensions',
		plugins_url( 'dimensions.js', __FILE__ ),
		array( 'blockstudio-blocks', 'wp-element', 'wp-components' ),
		'1.0.0',
		true
	);

	bs_register_field_type(
		'divine/dimensions',
		array(
			'attribute'     => 'object',
			'default'       => array(),
			'editor_script' => 'divine-field-dimensions',
			'storage'       => array(
				'type'        => 'object',
				'rest_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'string' ),
				),
			),
		)
	);
} );
```

```js
window.blockstudio.registerFieldType('divine/dimensions', {
	component({ value, onChange, sides = ['top', 'right', 'bottom', 'left'] }) {
		const next = value && typeof value === 'object' ? value : {};

		return wp.element.createElement(
			'div',
			{},
			sides.map((side) =>
				wp.element.createElement(wp.components.TextControl, {
					key: side,
					label: side,
					value: next[side] || '',
					onChange: (sideValue) =>
						onChange({
							...next,
							[side]: sideValue,
						}),
				})
			)
		);
	},
});
```

Usage:

```json
{
  "blockstudio": {
    "attributes": [
      {
        "id": "margin",
        "type": "divine/dimensions",
        "label": "Margin",
        "sides": ["top", "bottom"],
        "default": {
          "top": "md",
          "bottom": "md"
        }
      }
    ]
  }
}
```

## Backward Compatibility

- Existing built-in field types remain unchanged.
- Existing `custom/{name}` reusable fields remain unchanged.
- Existing `field.json` discovery and filters remain unchanged.
- Existing saved block attributes continue to render.
- Existing extension `set` rules continue to work.
- Unknown field types continue to be ignored by attribute building unless they
  are explicitly registered.
- No public API should introduce camelCase field type names. Public custom field
  type names are lowercase dashcase and namespaced.

## Tests

The implementation is not done until both unit and E2E coverage exist for the
new field type contract and a final GitHub Actions run passes from a commit that
contains `[all]`.

### Unit tests

Add tests for:

- valid custom field type registration
- invalid names rejected
- `custom/` namespace rejected for field types
- duplicate registration behavior
- helper and filter both feed the same registry
- registry reset works for test isolation
- generic string custom type builds a string attribute
- generic object custom type builds an object attribute
- default and fallback pass through
- `produces_attribute => false` produces no WordPress attribute
- custom type works inside group
- custom type works inside tabs
- custom type works inside repeater
- `Build::build_attributes()` and `Attribute_Builder` agree
- object custom type storage registers as object with REST schema
- array custom type storage registers as array with REST schema
- extension `set` resolves nested object paths
- missing nested object paths render empty segments, matching existing behavior

### E2E tests

Add a test fixture in the test theme:

- register `test/dimensions` in `tests/theme/functions.php` or a test helper
- register an editor script that calls `window.blockstudio.registerFieldType`
- add a block such as `tests/theme/blockstudio/types/custom-field-type`

Cover:

- custom field control appears in the sidebar
- object values can be edited
- object values save and reload
- object values render on the frontend
- object values work inside a repeater row
- custom field type inside a reusable `custom/{name}` field expands and saves
- extension `set` can use `{attributes.margin.top}`
- missing custom editor control does not clear existing saved data

### Asset tests

Cover:

- registered editor script handle enqueues when a used custom field type exists
- registered editor style handle enqueues when used
- unused custom field type handles do not enqueue
- missing registered handles do not fatal

## Docs

Update docs:

- add `docs/content/docs/blocks/attributes/custom-field-types.mdx`
- link it from `docs/content/docs/blocks/attributes/meta.json`
- update `field-types.mdx` with a clear distinction:
  - field types are controls/data behavior
  - custom fields are reusable groups of fields
- update schema-driven docs and generated types
- update `includes/llm/blockstudio-llm.txt`
- add a release-facing `readme.txt` changelog line when the implementation
  lands
- start the 7.5 release blog post at `docs/content/blog/blockstudio-7-5.mdx`
  with the custom field type story, examples, and migration notes

Docs must include:

- naming rules
- PHP helper
- PHP filter
- JavaScript registry
- editor script/style handles
- object value examples
- storage schema examples
- repeaters and custom fields support
- limitations and fallback behavior

## Implementation Plan

1. Add `Field_Type_Registry` and public helper functions.
2. Wire `Field_Type_Config` to registry-backed type resolution.
3. Add generic custom field type attribute building.
4. Align legacy REST attribute building with `Attribute_Builder`.
5. Add object-aware storage registration.
6. Add editor field type registry and direct custom `onChange` path.
7. Add custom field type asset enqueueing.
8. Add schema and TypeScript type updates.
9. Add unit coverage.
10. Add E2E fixture and browser coverage.
11. Add docs, LLM file, changelog, and the first 7.5 blog post draft.
12. Run the relevant local unit and E2E tests while developing.
13. Push a final commit with `[all]` and keep fixing until GitHub CI is green.

## Open Decisions

- Whether v1 should support `set` on normal Blockstudio block fields or keep
  `set` documented as extension-only. The conservative v1 path is extension-only
  plus normal template access.
- Whether to support a PHP `sanitize_callback` in field type definitions. Avoid
  in v1 unless storage tests expose a concrete need.
- Whether custom field type assets should enqueue for all registered custom
  field types or only used custom field types. The preferred path is used-only,
  but implementation can enqueue all registered custom type assets if used-only
  discovery proves brittle. This must be documented either way.
- Whether PHPStan can read declarative custom field type config from a static
  file in a later release. Runtime filters cannot be fully represented
  statically in v1.

## Definition of Done

A consumer can:

1. register `divine/dimensions` in PHP
2. register the matching editor component in JavaScript
3. use `"type": "divine/dimensions"` in `block.json`
4. edit an object value in the block editor
5. save and reload without losing data
6. render the value in PHP, Twig, and Blade templates
7. use the value inside repeaters and reusable custom fields
8. use nested object paths in extension `set` rules
9. store the value in post meta or options with a correct REST schema

Existing built-in fields, reusable `custom/{name}` fields, extensions, Page
Sync, and content rendering continue to pass their current tests.

Release gate:

- unit tests pass
- E2E tests pass
- docs, LLM output, schema, generated types, changelog, and the initial 7.5
  blog post draft are updated
- the final GitHub Actions run triggered by `[all]` is green
