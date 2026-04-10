<?php

/**
 * Render a Blockstudio block from PHP.
 *
 * @param array{name: string, data?: array<string, mixed>, mode?: string} $args
 * @return string
 */
function bs_render_block(array $args): string {}

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
 * Inline placeholder SVG helpers.
 */
function blockstudio_placeholder(string $variant = 'default'): string {}
function blockstudio_placeholder_dark(string $variant = 'default'): string {}
function blockstudio_placeholder_light(string $variant = 'default'): string {}
