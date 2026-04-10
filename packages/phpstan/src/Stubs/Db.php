<?php

namespace Blockstudio;

/**
 * Database access for Blockstudio block schemas.
 *
 * @template TRecord of array<string, mixed>
 */
class Db
{
    /**
     * Get a database instance for a block schema.
     *
     * @return self<array<string, mixed>>|null
     */
    public static function get(string $block, string $schema = 'default'): ?self {}

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return TRecord|\WP_Error
     */
    public function create(array $data) {}

    /**
     * List records.
     *
     * @param array<string, mixed> $filters
     * @return list<TRecord>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array {}

    /**
     * Get a single record by ID.
     *
     * @return TRecord|null
     */
    public function get_record(int $id) {}

    /**
     * Update a record by ID.
     *
     * @param array<string, mixed> $data
     * @return TRecord|\WP_Error|null
     */
    public function update(int $id, array $data) {}

    /**
     * Delete a record by ID.
     */
    public function delete(int $id): bool {}

    /**
     * Render a form for the schema.
     */
    public function form(): string {}

    /**
     * Get all schemas registered in the project.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_all(): array {}
}
