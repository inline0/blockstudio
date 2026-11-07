<?php

/**
 * Render a Blockstudio block from PHP.
 *
 * @param array{name: string, data?: array<string, mixed>, mode?: string} $args
 * @return string
 */
function bs_render_block(array $args): string {}

/**
 * Validate a parsed block's stored values without rendering or writing them.
 *
 * @param array<string, mixed> $attrs
 * @return list<array{code:string,path:string,source:string,message:string,value:mixed,expected:mixed}>|null
 */
function bs_validate_block(string $name, array $attrs): ?array {}

/**
 * Describe the values a block stores in blockstudio.attributes as a JSON Schema.
 *
 * @return array<string, mixed>|null
 */
function bs_block_schema(string $name): ?array {}

/**
 * Get a stored block's values as its template receives them, without rendering.
 *
 * @param array<string, mixed> $attrs
 * @return array<string, mixed>|null
 */
function bs_block_values(string $name, array $attrs): ?array {}

/**
 * Extract a group's child fields from an attributes array.
 *
 * @param array<string, mixed> $attributes
 * @return array<string, mixed>
 */
function bs_get_group(array $attributes, string $group_id): array {}

/**
 * Get the scoped CSS class for a block.
 */
function bs_get_scoped_class(string $block_name): string {}

/**
 * Whether Blockstudio is rendering for the block editor canvas.
 */
function bs_is_editor(): bool {}

/**
 * Whether Blockstudio is rendering for the block inserter preview.
 */
function bs_is_preview(): bool {}

/**
 * Whether Blockstudio is buffering the current frontend response.
 */
function bs_is_buffering(): bool {}

/**
 * Remove the comments Blockstudio puts in front of every rendered block.
 */
function bs_strip_render_markers(string $html): string {}

/**
 * Render a database form for a block schema.
 *
 * @param array{block: string, schema?: string, fields?: list<string>} $args
 */
function bs_db_form(array $args): string {}

/**
 * Render a database table for a block schema.
 *
 * @param array{block: string, schema?: string} $args
 */
function bs_db_table(array $args): string {}

/**
 * Merge Tailwind class values with the bundled engine.
 */
function bs_tw_merge(mixed ...$classes): string {}

/**
 * Create a CVA-style Tailwind variant composer.
 *
 * @param array<string, mixed> $config
 * @return callable(?array<string, mixed>): string
 */
function bs_tw_variants(array $config): callable {}

/**
 * Render an image through the Blockstudio media runtime.
 *
 * @param array<string, mixed> $args
 */
function bs_media_image(array $args): string {}

/**
 * Inline placeholder SVG helpers.
 */
function blockstudio_placeholder(string $variant = 'default'): string {}
function blockstudio_placeholder_dark(string $variant = 'default'): string {}
function blockstudio_placeholder_light(string $variant = 'default'): string {}
