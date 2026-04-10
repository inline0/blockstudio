<?php

namespace Blockstudio;

class Field_Registry
{
    public static function instance(): self {}

    /**
     * Get all registered custom fields.
     *
     * @return array<string, array{name?: string, title?: string, attributes?: list<array<string, mixed>>}>
     */
    public function all(): array {}

    /**
     * Get a single field by name.
     *
     * @return array{name?: string, title?: string, attributes?: list<array<string, mixed>>}|null
     */
    public function get(string $name): ?array {}
}
