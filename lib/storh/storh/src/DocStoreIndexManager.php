<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class DocStoreIndexManager
{
    private const EQ_REBUILD_FLUSH_IDS = 1048576;

    private const RANGE_REBUILD_CHUNK_ENTRIES = 16384;

    private const RANGE_SPARSE_STRIDE = 256;

    private const VALUE_COUNT_SCAN_CHUNK_BYTES = 512;

    /** @var array<string, array{field: string, unique: bool, range: bool}> */
    private array $pending = array();

    /** @var array<string, array{field: string, unique: bool, range: bool}>|null */
    private ?array $definitions_cache = null;

    /** @var array<string, list<array{key: string, offset: int}>> */
    private array $range_sparse_checkpoints_cache = array();

    public function __construct(private readonly DocPerFileStore $store)
    {
        $this->pending = $this->definitions();
    }

    public function field(string $field): DocStoreIndexFieldBuilder
    {
        $this->assert_field($field);
        $this->pending[ $field ] ??= array(
            'field'  => $field,
            'unique' => false,
            'range'  => false,
        );

        return new DocStoreIndexFieldBuilder($this, $field);
    }

    public function define_field(string $field, bool $unique = false, bool $range = false): self
    {
        $this->assert_field($field);
        $existing = $this->pending[ $field ] ?? array( 'field' => $field, 'unique' => false, 'range' => false );

        $this->pending[ $field ] = array(
            'field'  => $field,
            'unique' => $existing['unique'] || $unique,
            'range'  => $existing['range'] || $range,
        );

        return $this;
    }

    public function apply_schema(Schema $schema): self
    {
        foreach ($schema->fields() as $field) {
            if ($field->indexed()) {
                $this->define_field($field->name(), $field->unique(), $field->range());
            }
        }

        return $this;
    }

    public function sync(bool $rebuild = true): void
    {
        $this->store->with_write_lock(
            function () use ($rebuild): void {
                $before = $this->definitions();
                $after  = $this->pending;
                ksort($after);

                AtomicFilesystem::write_atomic(
                    $this->manifest_path(),
                    Jsonc::encode_object(array( 'fields' => array_values($after) ))
                );
                $this->definitions_cache = $after;

                if ($rebuild && $before !== $after) {
                    $this->rebuild();
                }
            }
        );
    }

    /**
     * @return array{fields: int, entries: int}
     */
    public function rebuild(bool $from_filesystem = false): array
    {
        return $this->store->with_write_lock(fn(): array => $this->rebuild_unlocked($from_filesystem));
    }

    /**
     * @return array{fields: int, entries: int}
     */
    private function rebuild_unlocked(bool $from_filesystem): array
    {
        $this->delete_directory($this->entries_root());
        AtomicFilesystem::ensure_directory($this->entries_root());
        $this->range_sparse_checkpoints_cache = array();

        $buckets = array();
        $range_buckets = array();
        $range_chunks = array();
        $range_entries = 0;
        $eq_entries = 0;
        $entries = 0;
        $definitions = $this->rebuild_definitions($this->definitions());
        $compound_roots = $this->rebuild_compound_roots($definitions);
        $compound_key_cache = array();
        $records = $from_filesystem ? $this->store->records_from_filesystem() : $this->store->stream();
        foreach ($records as $record) {
            $this->collect_rebuild_index_entries(
                $buckets,
                $range_buckets,
                $range_entries,
                $eq_entries,
                $compound_roots,
                $compound_key_cache,
                $definitions,
                $record->id(),
                $record->data()
            );
            $entries++;

            // @codeCoverageIgnoreStart
            if ($eq_entries >= self::EQ_REBUILD_FLUSH_IDS) {
                $this->merge_index_buckets($buckets, false);
                $buckets = array();
                $eq_entries = 0;
            }
            // @codeCoverageIgnoreEnd

            // @codeCoverageIgnoreStart
            if ($range_entries >= self::RANGE_REBUILD_CHUNK_ENTRIES) {
                $this->flush_range_chunks($range_buckets, $range_chunks);
                $range_buckets = array();
                $range_entries = 0;
            }
            // @codeCoverageIgnoreEnd
        }

        $this->merge_index_buckets($buckets, false);
        $this->flush_range_chunks($range_buckets, $range_chunks);
        $this->write_range_chunks($range_chunks);

        return array(
            'fields'  => count($this->definitions()),
            'entries' => $entries,
        );
    }

    /**
     * @return list<string>
     */
    public function verify_against_store(): array
    {
        $definitions = $this->definitions();
        if (array() === $definitions) {
            return array();
        }

        $expected = $this->expected_index_maps($definitions);
        $errors = array();

        $actual_eq = array();
        $actual_range = array();
        $actual_compound = array();
        $this->collect_actual_eq_index_map($definitions, $actual_eq, $errors);
        $this->collect_actual_range_index_map($definitions, $actual_range, $errors);
        $this->collect_actual_compound_index_map($definitions, $actual_compound, $errors);

        $this->compare_index_maps('equality', $expected['eq'], $actual_eq, $errors);
        $this->compare_index_maps('range', $expected['range'], $actual_range, $errors);
        $this->compare_index_maps('compound', $expected['compound'], $actual_compound, $errors);

        return $errors;
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool}> $definitions
     * @return array{
     *     eq: array<string, array<string, true>>,
     *     range: array<string, array<string, true>>,
     *     compound: array<string, array<string, true>>
     * }
     */
    private function expected_index_maps(array $definitions): array
    {
        $eq = array();
        $range = array();
        $compound = array();

        foreach ($this->store->records_from_filesystem() as $record) {
            $compound_values = array();
            foreach ($definitions as $definition) {
                $field = $definition['field'];
                $data = $record->data();
                if (! array_key_exists($field, $data) || ! $this->indexable($data[ $field ])) {
                    continue;
                }

                $value = $data[ $field ];
                if ($definition['range']) {
                    $this->add_index_map_id(
                        $range,
                        $field . "\0" . $this->range_key($value) . "\0" . $this->value_key($value),
                        $record->id()
                    );
                    continue;
                }

                $this->add_index_map_id($eq, $field . "\0" . $this->value_key($value), $record->id());
                if (! $definition['unique']) {
                    $compound_values[ $field ] = $value;
                }
            }

            $fields = array_keys($compound_values);
            $field_count = count($fields);
            for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
                $left = $fields[ $left_index ];
                for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                    $right = $fields[ $right_index ];
                    $this->add_index_map_id(
                        $compound,
                        $left . "\t" . $right . "\0" . $this->compound_value_key(
                            $compound_values[ $left ],
                            $compound_values[ $right ]
                        ),
                        $record->id()
                    );
                }
            }
        }

        return array(
            'eq'       => $eq,
            'range'    => $range,
            'compound' => $compound,
        );
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool}> $definitions
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function collect_actual_eq_index_map(array $definitions, array &$actual, array &$errors): void
    {
        foreach ($definitions as $definition) {
            if ($definition['range']) {
                continue;
            }

            $root = $this->entries_root() . '/eq/' . $this->field_key($definition['field']);
            foreach (glob($root . '/*.jsonc') ?: array() as $path) {
                $this->collect_actual_value_index_file($path, $definition['field'], false, $actual, $errors);
            }
        }
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool}> $definitions
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function collect_actual_range_index_map(array $definitions, array &$actual, array &$errors): void
    {
        foreach ($definitions as $definition) {
            if (! $definition['range']) {
                continue;
            }

            $field = $definition['field'];
            $this->collect_actual_range_index_file($this->range_field_root($field), $field, $actual, $errors);
            $this->collect_actual_range_index_file($this->range_delta_field_root($field), $field, $actual, $errors);
        }
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool}> $definitions
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function collect_actual_compound_index_map(array $definitions, array &$actual, array &$errors): void
    {
        $fields = array_keys(array_filter(
            $definitions,
            static fn(array $definition): bool => ! $definition['range'] && ! $definition['unique']
        ));
        $field_count = count($fields);
        for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
            $left = $fields[ $left_index ];
            for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                $right = $fields[ $right_index ];
                $root = $this->entries_root()
                    . '/compound/'
                    . $this->field_key($left)
                    . '-'
                    . $this->field_key($right);
                foreach (glob($root . '/*.jsonc') ?: array() as $path) {
                    $this->collect_actual_value_index_file($path, $left . "\t" . $right, true, $actual, $errors);
                }
            }
        }
    }

    /**
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function collect_actual_value_index_file(
        string $path,
        string $expected_field,
        bool $compound,
        array &$actual,
        array &$errors
    ): void {
        try {
            $entry = AtomicFilesystem::read_jsonc_object($path);
        } catch (\Throwable $throwable) {
            $errors[] = 'Unreadable index file: ' . $path . ' (' . $throwable->getMessage() . ')';
            return;
        }

        $field = $entry['field'] ?? null;
        $key = $entry['key'] ?? null;
        if (! is_string($field) || ! is_string($key)) {
            $errors[] = 'Malformed index file: ' . $path;
            return;
        }
        if ($field !== $expected_field) {
            $errors[] = 'Index field mismatch: ' . $path;
        }
        if (! $compound && $key !== $this->value_key($entry['value'] ?? null)) {
            $errors[] = 'Index key mismatch: ' . $path;
        }

        $ids = $this->ids_from_value_entry($entry);
        if (isset($entry['count']) && is_int($entry['count']) && $entry['count'] !== count($ids)) {
            $errors[] = 'Index count mismatch: ' . $path;
        }

        foreach ($ids as $id) {
            $this->add_index_map_id($actual, $field . "\0" . $key, $id);
        }
    }

    /**
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function collect_actual_range_index_file(string $path, string $field, array &$actual, array &$errors): void
    {
        if (! is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            $errors[] = 'Unreadable range index file: ' . $path;
            return;
        }

        try {
            while (false !== ( $line = fgets($handle) )) {
                $entry = $this->decode_index_line($line);
                $key = $entry['key'] ?? null;
                $value = $entry['value'] ?? null;
                if (array() === $entry || ! is_string($key) || ! $this->indexable($value)) {
                    $errors[] = 'Malformed range index line: ' . $path;
                    continue;
                }
                if ($key !== $this->range_key($value)) {
                    $errors[] = 'Range index key mismatch: ' . $path;
                }

                $identity = $field . "\0" . $key . "\0" . $this->value_key($value);
                foreach ($this->ids_from_value_entry($entry) as $id) {
                    $this->add_index_map_id($actual, $identity, $id);
                }

                $removed = isset($entry['remove']) && is_array($entry['remove']) ? $entry['remove'] : array();
                foreach ($removed as $id) {
                    if (is_string($id)) {
                        $this->remove_index_map_id($actual, $identity, $id);
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, array<string, true>> $map
     */
    private function add_index_map_id(array &$map, string $identity, string $id): void
    {
        $map[ $identity ] ??= array();
        $map[ $identity ][ $id ] = true;
    }

    /**
     * @param array<string, array<string, true>> $map
     */
    private function remove_index_map_id(array &$map, string $identity, string $id): void
    {
        unset($map[ $identity ][ $id ]);
        if (isset($map[ $identity ]) && array() === $map[ $identity ]) {
            unset($map[ $identity ]);
        }
    }

    /**
     * @param array<string, array<string, true>> $expected
     * @param array<string, array<string, true>> $actual
     * @param list<string> $errors
     */
    private function compare_index_maps(string $label, array $expected, array $actual, array &$errors): void
    {
        $identities = array_values(array_unique(array_merge(array_keys($expected), array_keys($actual))));
        sort($identities, SORT_STRING);

        foreach ($identities as $identity) {
            $expected_ids = array_keys($expected[ $identity ] ?? array());
            $actual_ids = array_keys($actual[ $identity ] ?? array());
            sort($expected_ids, SORT_STRING);
            sort($actual_ids, SORT_STRING);
            if ($expected_ids !== $actual_ids) {
                $errors[] = 'Index mismatch (' . $label . '): ' . str_replace("\0", ' ', $identity);
            }
        }
    }

    /**
     * @return array<string, array{field: string, unique: bool, range: bool}>
     */
    public function definitions(): array
    {
        if (null !== $this->definitions_cache) {
            return $this->definitions_cache;
        }

        if (! is_file($this->manifest_path())) {
            $this->definitions_cache = array();

            return array();
        }

        $decoded = AtomicFilesystem::read_jsonc_object($this->manifest_path());
        $fields  = array();
        $items   = isset($decoded['fields']) && is_array($decoded['fields']) ? $decoded['fields'] : array();
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['field']) || ! is_string($item['field'])) {
                continue;
            }

            $fields[ $item['field'] ] = array(
                'field'  => $item['field'],
                'unique' => true === ( $item['unique'] ?? false ),
                'range'  => true === ( $item['range'] ?? false ),
            );
        }

        ksort($fields);
        $this->definitions_cache = $fields;

        return $fields;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $old_data
     */
    public function validate_unique(?string $id, array $data, ?array $old_data = null): void
    {
        foreach ($this->definitions() as $definition) {
            if (! $definition['unique'] || ! array_key_exists($definition['field'], $data)) {
                continue;
            }

            $value = $data[ $definition['field'] ];
            if (! $this->indexable($value)) {
                continue;
            }

            $ids = $this->ids_for_value($definition['field'], $value);
            foreach ($ids as $existing_id) {
                if ($existing_id !== $id) {
                    throw new StorageException('Unique index violation on field: ' . $definition['field']);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $old_data
     */
    public function update_record(string $id, array $data, ?array $old_data): void
    {
        $this->store->with_write_lock(
            function () use ($id, $data, $old_data): void {
                if (null !== $old_data) {
                    $this->remove_record($id, $old_data);
                }

                $buckets = array();
                $this->collect_index_entries($buckets, $this->definitions(), $id, $data);
                $this->merge_index_buckets($buckets);
            }
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function remove_record(string $id, array $data): void
    {
        $this->store->with_write_lock(
            function () use ($id, $data): void {
                $definitions = $this->definitions();
                $compound_values = array();
                foreach ($definitions as $definition) {
                    $field = $definition['field'];
                    if (! array_key_exists($field, $data) || ! $this->indexable($data[ $field ])) {
                        continue;
                    }

                    if ($definition['range']) {
                        $this->append_range_entry($field, $data[ $field ], array(), array( $id ));
                    } else {
                        $this->remove_id_from_entry(
                            $this->eq_entry_path($field, $data[ $field ]),
                            $this->value_key($data[ $field ]),
                            $id
                        );
                        if (! $definition['unique']) {
                            $compound_values[ $field ] = $data[ $field ];
                        }
                    }
                }

                $this->remove_compound_entries($id, $compound_values);
            }
        );
    }

    /**
     * @return null|list<string>
     */
    public function candidate_ids(QueryBuilder $query): ?array
    {
        $all_candidates = array();
        $groups = $query->groups();
        $ordered_range_ids = $this->ordered_range_candidate_ids($query, $groups);
        if (null !== $ordered_range_ids) {
            return $ordered_range_ids;
        }

        $limit = $this->candidate_limit($query, $groups);

        foreach ($groups as $group) {
            $group_ids = $this->candidate_ids_for_group($group, $limit);
            if (null === $group_ids) {
                return null;
            }

            foreach ($group_ids as $id) {
                $all_candidates[ $id ] = true;
            }

            if (null !== $limit && count($all_candidates) >= $limit) {
                break;
            }
        }

        $ids = array_keys($all_candidates);
        if (null === $limit) {
            sort($ids);
        }

        return $ids;
    }

    public function candidate_count(QueryBuilder $query): ?int
    {
        $groups = $query->groups();
        if (1 === count($groups) && 1 === count($groups[0])) {
            return $this->candidate_count_for_condition($groups[0][0], $query->limit_value());
        }

        if (1 === count($groups)) {
            return $this->candidate_count_for_group($groups[0], $query->limit_value());
        }

        $all_candidates = array();
        foreach ($groups as $group) {
            $group_ids = $this->candidate_ids_for_group($group, null, true);
            if (null === $group_ids) {
                return null;
            }

            foreach ($group_ids as $id) {
                $all_candidates[ $id ] = true;
            }
        }

        $count = count($all_candidates);
        $limit = $query->limit_value();

        return null === $limit ? $count : min($count, $limit);
    }

    public function candidate_order_satisfies(QueryBuilder $query): bool
    {
        return null !== $this->ordered_range_condition($query, $query->groups());
    }

    private function candidate_count_for_condition(QueryCondition $condition, ?int $limit): ?int
    {
        if (! $this->indexed_condition_supported($condition)) {
            return null;
        }

        $count = match ($condition->operator()) {
            'eq' => $this->count_for_value($condition->field(), $condition->value(), $limit),
            'in' => is_array($condition->value())
                ? $this->count_for_values($condition->field(), $condition->value(), $limit)
                : 0,
            'gt', 'gte', 'lt', 'lte', 'between', 'prefix' => $this->count_for_range_condition($condition, $limit),
            // @codeCoverageIgnoreStart
            default => null,
            // @codeCoverageIgnoreEnd
        };

        if (null === $count) {
            return null;
        }

        return null === $limit ? $count : min($count, $limit);
    }

    /**
     * @param list<QueryCondition> $group
     */
    private function candidate_count_for_group(array $group, ?int $limit): ?int
    {
        $compound_count = $this->compound_count_for_group($group, $limit);
        if (null !== $compound_count) {
            return $compound_count;
        }

        $candidate_sets = array();
        foreach ($group as $condition) {
            $ids = $this->indexed_condition_ids($condition);
            if (null === $ids) {
                return null;
            }

            $candidate_sets[] = $ids;
        }

        if (array() === $candidate_sets) {
            return null;
        }

        usort($candidate_sets, static fn(array $left, array $right): int => count($left) <=> count($right));

        $ids = array_fill_keys($candidate_sets[0], true);
        for ($index = 1; $index < count($candidate_sets); $index++) {
            if (array() === $ids) {
                return 0;
            }

            $next = array_fill_keys($candidate_sets[ $index ], true);
            foreach ($ids as $id => $_) {
                if (! isset($next[ $id ])) {
                    unset($ids[ $id ]);
                }
            }
        }

        $count = count($ids);

        return null === $limit ? $count : min($count, $limit);
    }

    /**
     * @return array{store: string, plan: string, indexes: list<array<string, mixed>>, groups: int}
     */
    public function explain(QueryBuilder $query): array
    {
        $indexes = array();
        $plan    = 'full_scan';

        foreach ($query->groups() as $group) {
            $condition = $this->best_condition($group);
            if (null !== $condition) {
                $plan      = 'index_scan';
                $indexes[] = array(
                    'field'    => $condition->field(),
                    'operator' => $condition->operator(),
                );
            }
        }

        return array(
            'store'   => DocPerFileStore::class,
            'plan'    => $plan,
            'indexes' => $indexes,
            'groups'  => count($query->groups()),
        );
    }

    /**
     * @param list<list<QueryCondition>> $groups
     */
    private function candidate_limit(QueryBuilder $query, array $groups): ?int
    {
        $limit = $query->limit_value();
        if (null !== $query->cursor_id() || null === $limit) {
            return null;
        }

        if (1 !== count($groups)) {
            return null;
        }

        $group = $groups[0];
        if (1 !== count($group)) {
            return null;
        }

        $condition = $group[0];
        if (! $query->has_ordering()) {
            return 'eq' === $condition->operator() ? $limit : null;
        }

        if ('asc' !== $query->order_direction() || null === $this->ordered_range_condition($query, $groups)) {
            return null;
        }

        return $limit;
    }

    /**
     * @param list<list<QueryCondition>> $groups
     * @return QueryCondition|null
     */
    private function ordered_range_condition(QueryBuilder $query, array $groups): ?QueryCondition
    {
        if (
            null === $query->limit_value() ||
            null !== $query->cursor_id() ||
            ! in_array($query->order_direction(), array( 'asc', 'desc' ), true)
        ) {
            return null;
        }

        if (1 !== count($groups) || 1 !== count($groups[0])) {
            return null;
        }

        $condition = $groups[0][0];
        if ($query->order_field() !== $condition->field() || ! $this->range_order_supported($condition)) {
            return null;
        }

        $definition = $this->definitions()[ $condition->field() ] ?? null;
        if (null === $definition || ! $definition['range'] || is_file($this->range_delta_field_root($condition->field()))) {
            return null;
        }

        return $condition;
    }

    /**
     * @param list<list<QueryCondition>> $groups
     * @return list<string>|null
     */
    private function ordered_range_candidate_ids(QueryBuilder $query, array $groups): ?array
    {
        $limit = $query->limit_value();
        if (null === $limit || 'desc' !== $query->order_direction()) {
            return null;
        }

        $condition = $this->ordered_range_condition($query, $groups);
        if (null === $condition) {
            return null;
        }

        return $this->tail_ids_for_range_condition($condition, $limit);
    }

    /**
     * @param list<QueryCondition> $group
     * @return null|list<string>
     */
    private function candidate_ids_for_group(array $group, ?int $limit = null, bool $require_all_conditions = false): ?array
    {
        $compound_ids = $this->compound_ids_for_group($group, $limit);
        if (null !== $compound_ids) {
            return $compound_ids;
        }

        $candidate_sets = array();
        foreach ($group as $condition) {
            $ids = $this->indexed_condition_ids($condition, $limit);
            if (null === $ids) {
                if ($require_all_conditions) {
                    return null;
                }

                continue;
            }

            $candidate_sets[] = $ids;
        }

        if (array() === $candidate_sets) {
            return null;
        }

        if (1 === count($candidate_sets)) {
            return $candidate_sets[0];
        }

        usort($candidate_sets, static fn(array $left, array $right): int => count($left) <=> count($right));

        $ids = array_fill_keys($candidate_sets[0], true);
        for ($index = 1; $index < count($candidate_sets); $index++) {
            if (array() === $ids) {
                return array();
            }

            $next = array_fill_keys($candidate_sets[ $index ], true);
            foreach ($ids as $id => $_) {
                if (! isset($next[ $id ])) {
                    unset($ids[ $id ]);
                }
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /**
     * @return null|list<string>
     */
    private function indexed_condition_ids(QueryCondition $condition, ?int $limit = null): ?array
    {
        if (! $this->indexed_condition_supported($condition)) {
            return null;
        }

        if ('eq' === $condition->operator()) {
            return $this->ids_for_value($condition->field(), $condition->value(), $limit);
        }

        if ('in' === $condition->operator()) {
            if (! is_array($condition->value())) {
                return array();
            }

            $ids = array();
            foreach ($condition->value() as $value) {
                foreach ($this->ids_for_value($condition->field(), $value, $limit) as $id) {
                    $ids[ $id ] = true;
                    if (null !== $limit && count($ids) >= $limit) {
                        return array_keys($ids);
                    }
                }
            }

            return array_keys($ids);
        }

        if (in_array($condition->operator(), array( 'gt', 'gte', 'lt', 'lte', 'between', 'prefix' ), true)) {
            return $this->ids_for_range_condition($condition, $limit);
        }

        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }

    private function indexed_condition_supported(QueryCondition $condition): bool
    {
        $definition = $this->definitions()[ $condition->field() ] ?? null;
        if (null === $definition) {
            return false;
        }

        if (in_array($condition->operator(), array( 'eq', 'in' ), true)) {
            return true;
        }

        return $definition['range']
            && in_array($condition->operator(), array( 'gt', 'gte', 'lt', 'lte', 'between', 'prefix' ), true);
    }

    /**
     * @param list<QueryCondition> $group
     */
    private function best_condition(array $group): ?QueryCondition
    {
        $definitions = $this->definitions();
        foreach ($group as $condition) {
            $definition = $definitions[ $condition->field() ] ?? null;
            if (null === $definition) {
                continue;
            }

            if (in_array($condition->operator(), array( 'eq', 'in' ), true)) {
                return $condition;
            }

            if (
                $definition['range'] && in_array(
                    $condition->operator(),
                    array( 'gt', 'gte', 'lt', 'lte', 'between', 'prefix' ),
                    true
                )
            ) {
                return $condition;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function ids_for_value(string $field, mixed $value, ?int $limit = null): array
    {
        if (! $this->indexable($value)) {
            return array();
        }

        $root = $this->eq_value_root($field, $value);
        if (! is_file($root)) {
            $definition = $this->definitions()[ $field ] ?? null;
            if (null !== $definition && $definition['range']) {
                return $this->ids_for_range_value($field, $value, $limit);
            }

            return array();
        }

        return $this->ids_from_value_file($root, $this->value_key($value), $limit);
    }

    /**
     * @param array<mixed> $values
     */
    private function count_for_values(string $field, array $values, ?int $limit = null): ?int
    {
        $count = 0;
        $seen = array();

        foreach ($values as $value) {
            if (! $this->indexable($value)) {
                continue;
            }

            $key = $this->value_key($value);
            if (isset($seen[ $key ])) {
                continue;
            }

            $seen[ $key ] = true;
            $value_count = $this->count_for_value($field, $value, null === $limit ? null : $limit - $count);
            if (null === $value_count) {
                return null;
            }

            $count += $value_count;
            if (null !== $limit && $count >= $limit) {
                return $limit;
            }
        }

        return $count;
    }

    private function count_for_value(string $field, mixed $value, ?int $limit = null): ?int
    {
        if (! $this->indexable($value)) {
            return 0;
        }

        $root = $this->eq_value_root($field, $value);
        if (is_file($root)) {
            return $this->count_ids_from_value_file($root, $this->value_key($value), $limit);
        }

        $definition = $this->definitions()[ $field ] ?? null;
        if (null !== $definition && $definition['range']) {
            return null;
        }

        return 0;
    }

    /**
     * @param list<QueryCondition> $group
     * @return list<string>|null
     */
    private function compound_ids_for_group(array $group, ?int $limit): ?array
    {
        $compound = $this->compound_group($group);
        if (null === $compound) {
            return null;
        }

        $key = $this->compound_value_key($compound[1], $compound[3]);
        $path = $this->compound_entry_path($compound[0], $compound[2], $key);

        return is_file($path)
            ? $this->ids_from_value_file($path, $key, $limit)
            : array();
    }

    /**
     * @param list<QueryCondition> $group
     */
    private function compound_count_for_group(array $group, ?int $limit): ?int
    {
        $compound = $this->compound_group($group);
        if (null === $compound) {
            return null;
        }

        $key = $this->compound_value_key($compound[1], $compound[3]);
        $path = $this->compound_entry_path($compound[0], $compound[2], $key);

        return is_file($path)
            ? $this->count_ids_from_value_file($path, $key, $limit)
            : 0;
    }

    /**
     * @param list<QueryCondition> $group
     * @return array{0: string, 1: mixed, 2: string, 3: mixed}|null
     */
    private function compound_group(array $group): ?array
    {
        if (2 !== count($group)) {
            return null;
        }

        $left = $group[0];
        $right = $group[1];
        if ('eq' !== $left->operator() || 'eq' !== $right->operator()) {
            return null;
        }

        if ($left->field() === $right->field()) {
            return null;
        }

        $definitions = $this->definitions();
        $left_definition = $definitions[ $left->field() ] ?? null;
        $right_definition = $definitions[ $right->field() ] ?? null;
        if (
            null === $left_definition ||
            null === $right_definition ||
            $left_definition['range'] ||
            $right_definition['range'] ||
            $left_definition['unique'] ||
            $right_definition['unique'] ||
            ! $this->indexable($left->value()) ||
            ! $this->indexable($right->value())
        ) {
            return null;
        }

        if (strcmp($left->field(), $right->field()) <= 0) {
            return array( $left->field(), $left->value(), $right->field(), $right->value() );
        }

        return array( $right->field(), $right->value(), $left->field(), $left->value() );
    }

    /**
     * @return list<string>
     */
    private function ids_for_range_condition(QueryCondition $condition, ?int $limit = null): array
    {
        $ids = array();
        $delta_path = $this->range_delta_field_root($condition->field());
        if (! is_file($delta_path)) {
            $this->collect_range_condition_ids($this->range_field_root($condition->field()), $condition, $ids, true, $limit);
        } else {
            $this->collect_range_condition_ids($this->range_field_root($condition->field()), $condition, $ids, true);
            $this->collect_range_condition_ids($delta_path, $condition, $ids, false);
        }

        $result = array_keys($ids);
        if (null === $limit) {
            sort($result);

            return $result;
        }

        return array_slice($result, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function tail_ids_for_range_condition(QueryCondition $condition, int $limit): array
    {
        $ids = array();
        $this->collect_range_condition_ids_reverse($this->range_field_root($condition->field()), $condition, $ids, $limit);

        return array_keys($ids);
    }

    private function count_for_range_condition(QueryCondition $condition, ?int $limit = null): int
    {
        $delta_path = $this->range_delta_field_root($condition->field());
        if (! is_file($delta_path)) {
            return $this->count_range_condition_entries($this->range_field_root($condition->field()), $condition, true, $limit);
        }

        return $this->count_range_condition_entries($this->range_field_root($condition->field()), $condition, true)
            + $this->count_range_condition_entries($delta_path, $condition, false);
    }

    /**
     * @return array{0: string|null, 1: bool, 2: string|null, 3: bool}|null
     */
    private function range_key_window(QueryCondition $condition): ?array
    {
        $operator = $condition->operator();
        $value = $condition->value();

        if ('gt' === $operator || 'gte' === $operator) {
            $key = $this->ordered_range_key($value);

            return null === $key ? null : array( $key, 'gte' === $operator, null, true );
        }

        if ('lt' === $operator || 'lte' === $operator) {
            $key = $this->ordered_range_key($value);

            return null === $key ? null : array( null, true, $key, 'lte' === $operator );
        }

        if ('between' === $operator) {
            $lower = $this->ordered_range_key($value);
            $upper = $this->ordered_range_key($condition->second_value());

            return null === $lower || null === $upper ? null : array( $lower, true, $upper, true );
        }

        return null;
    }

    private function ordered_range_key(mixed $value): ?string
    {
        if (is_int($value) && $value >= 0) {
            return $this->range_key($value);
        }

        if (is_string($value) || is_bool($value)) {
            return $this->range_key($value);
        }

        return null;
    }

    private function range_condition_supported(QueryCondition $condition): bool
    {
        return in_array($condition->operator(), array( 'gt', 'gte', 'lt', 'lte', 'between', 'prefix' ), true);
    }

    private function range_order_supported(QueryCondition $condition): bool
    {
        if (! $this->range_condition_supported($condition)) {
            return false;
        }

        return match ($condition->operator()) {
            'prefix' => is_string($condition->value()),
            'between' => null !== $this->ordered_range_key($condition->value()) &&
                null !== $this->ordered_range_key($condition->second_value()),
            default => null !== $this->ordered_range_key($condition->value()),
        };
    }

    /**
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function range_key_matches_window(string $key, array $window): bool
    {
        [ $lower, $lower_inclusive, $upper, $upper_inclusive ] = $window;

        if (null !== $lower) {
            $comparison = strcmp($key, $lower);
            if ($comparison < 0 || (0 === $comparison && ! $lower_inclusive)) {
                return false;
            }
        }

        if (null !== $upper) {
            $comparison = strcmp($key, $upper);
            if ($comparison > 0 || (0 === $comparison && ! $upper_inclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function range_key_after_window(string $key, array $window): bool
    {
        $upper = $window[2];
        if (null === $upper) {
            return false;
        }

        $comparison = strcmp($key, $upper);

        return $comparison > 0 || (0 === $comparison && ! $window[3]);
    }

    /**
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function range_key_before_window(string $key, array $window): bool
    {
        $lower = $window[0];
        if (null === $lower) {
            return false;
        }

        $comparison = strcmp($key, $lower);

        return $comparison < 0 || (0 === $comparison && ! $window[1]);
    }

    /**
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function range_seek_offset(string $field, array $window): int
    {
        $lower = $window[0];
        if (null === $lower) {
            return 0;
        }

        $offset = 0;
        foreach ($this->range_sparse_checkpoints($field) as $item) {
            if (strcmp($item['key'], $lower) >= 0) {
                break;
            }

            $offset = $item['offset'];
        }

        return $offset;
    }

    /**
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function range_reverse_seek_offset(string $field, array $window, int $file_size): int
    {
        $upper = $window[2];
        if (null === $upper) {
            return $file_size;
        }

        $upper_inclusive = $window[3];
        foreach ($this->range_sparse_checkpoints($field) as $item) {
            $comparison = strcmp($item['key'], $upper);
            if ($comparison > 0 || (0 === $comparison && ! $upper_inclusive)) {
                return $item['offset'];
            }
        }

        return $file_size;
    }

    /**
     * @return list<array{key: string, offset: int}>
     */
    private function range_sparse_checkpoints(string $field): array
    {
        if (isset($this->range_sparse_checkpoints_cache[ $field ])) {
            return $this->range_sparse_checkpoints_cache[ $field ];
        }

        $path = $this->range_sparse_index_path($field);
        if (! is_file($path)) {
            $this->range_sparse_checkpoints_cache[ $field ] = array();

            return array();
        }

        $decoded = AtomicFilesystem::read_jsonc_object($path);
        $items = isset($decoded['checkpoints']) && is_array($decoded['checkpoints']) ? $decoded['checkpoints'] : array();
        $checkpoints = array();
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $item['key'] ?? null;
            $candidate_offset = $item['offset'] ?? null;
            if (! is_string($key) || ! is_int($candidate_offset)) {
                continue;
            }

            $checkpoints[] = array( 'key' => $key, 'offset' => $candidate_offset );
        }

        $this->range_sparse_checkpoints_cache[ $field ] = $checkpoints;

        return $checkpoints;
    }

    private function range_line_key(string $line): ?string
    {
        $prefix = '{"key":"';
        if (! str_starts_with($line, $prefix)) {
            return null;
        }

        $start = strlen($prefix);
        $end = strpos($line, '"', $start);

        return false === $end ? null : substr($line, $start, $end - $start);
    }

    /**
     * @param array<string, true> $ids
     */
    private function collect_range_condition_ids(
        string $path,
        QueryCondition $condition,
        array &$ids,
        bool $sorted,
        ?int $limit = null
    ): void {
        if (! is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            return;
        }

        $operator = $condition->operator();
        $expected = $condition->value();
        $second_expected = $condition->second_value();
        $key_window = $this->range_key_window($condition);

        try {
            if ($sorted && null !== $key_window) {
                fseek($handle, $this->range_seek_offset($condition->field(), $key_window));
            }

            while (false !== ( $line = fgets($handle) )) {
                $line_key = null;
                if (null !== $key_window) {
                    $line_key = $this->range_line_key($line);
                    if (null !== $line_key) {
                        if ($sorted && $this->range_key_after_window($line_key, $key_window)) {
                            break;
                        }

                        if (! $this->range_key_matches_window($line_key, $key_window)) {
                            continue;
                        }
                    }
                }

                $value_object = $this->decode_index_line($line);
                if (array() === $value_object) {
                    continue;
                }

                $value = $value_object['value'] ?? null;
                if (! $this->range_value_matches($operator, $value, $expected, $second_expected)) {
                    continue;
                }

                $this->apply_range_entry_ids($value_object, $ids);
                if (null !== $limit && $sorted && count($ids) >= $limit) {
                    return;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, true> $ids
     */
    private function collect_range_condition_ids_reverse(
        string $path,
        QueryCondition $condition,
        array &$ids,
        int $limit
    ): void {
        if (! is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            return;
        }

        $key_window = $this->range_key_window($condition);
        $buffer = '';

        try {
            fseek($handle, 0, SEEK_END);
            $position = ftell($handle);
            // @codeCoverageIgnoreStart
            if (false === $position) {
                return;
            }
            // @codeCoverageIgnoreEnd
            if (null !== $key_window) {
                $position = $this->range_reverse_seek_offset($condition->field(), $key_window, $position);
            }

            while ($position > 0) {
                $read = min(8192, $position);
                $position -= $read;
                fseek($handle, $position);
                $chunk = fread($handle, $read);
                if (false === $chunk || '' === $chunk) {
                    break;
                }

                $buffer = $chunk . $buffer;
                $lines = explode("\n", $buffer);
                $buffer = array_shift($lines) ?? '';

                for ($index = count($lines) - 1; $index >= 0; $index--) {
                    $line = $lines[ $index ];
                    if ('' === $line) {
                        continue;
                    }

                    if ($this->collect_reverse_range_line($line, $condition, $ids, $key_window, $limit)) {
                        return;
                    }
                }
            }

            if ('' !== $buffer) {
                $this->collect_reverse_range_line($buffer, $condition, $ids, $key_window, $limit);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, true> $ids
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool}|null $key_window
     */
    private function collect_reverse_range_line(
        string $line,
        QueryCondition $condition,
        array &$ids,
        ?array $key_window,
        int $limit
    ): bool {
        if (null !== $key_window) {
            $line_key = $this->range_line_key($line);
            if (null !== $line_key) {
                if ($this->range_key_after_window($line_key, $key_window)) {
                    return false;
                }

                if ($this->range_key_before_window($line_key, $key_window)) {
                    return true;
                }
            }
        }

        $entry = $this->decode_index_line($line);
        if (
            array() === $entry ||
            ! $this->range_value_matches(
                $condition->operator(),
                $entry['value'] ?? null,
                $condition->value(),
                $condition->second_value()
            )
        ) {
            return false;
        }

        $this->apply_range_entry_ids($entry, $ids);

        return count($ids) >= $limit;
    }

    private function count_range_condition_entries(string $path, QueryCondition $condition, bool $sorted, ?int $limit = null): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            return 0;
        }

        $count = 0;
        $key_window = $this->range_key_window($condition);
        $requires_value_match = null === $key_window;

        try {
            if ($sorted && null !== $key_window) {
                fseek($handle, $this->range_seek_offset($condition->field(), $key_window));
            }

            while (false !== ( $line = fgets($handle) )) {
                $line_key = null;
                if (null !== $key_window) {
                    $line_key = $this->range_line_key($line);
                    if (null !== $line_key) {
                        if ($sorted && $this->range_key_after_window($line_key, $key_window)) {
                            break;
                        }

                        if (! $this->range_key_matches_window($line_key, $key_window)) {
                            continue;
                        }
                    }
                }

                if ($requires_value_match) {
                    $entry = $this->decode_index_line($line);
                    if (
                        array() === $entry ||
                        ! $this->range_value_matches(
                            $condition->operator(),
                            $entry['value'] ?? null,
                            $condition->value(),
                            $condition->second_value()
                        )
                    ) {
                        continue;
                    }

                    $count += $this->range_entry_count($entry);
                    if (null !== $limit && $sorted && $count >= $limit) {
                        return $limit;
                    }
                    continue;
                }

                $count += $this->range_line_count($line);
                if (null !== $limit && $sorted && $count >= $limit) {
                    return $limit;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    private function range_line_count(string $line): int
    {
        $marker = ',"count":';
        $offset = strpos($line, $marker);
        if (false === $offset) {
            throw new StorageException('Malformed range index count.');
        }

        $start = $offset + strlen($marker);
        $negative = '-' === ( $line[ $start ] ?? '' );
        if ($negative) {
            $start++;
        }

        $end = $start;
        $length = strlen($line);
        while ($end < $length && ctype_digit($line[ $end ])) {
            $end++;
        }

        if ($end === $start) {
            throw new StorageException('Malformed range index count.');
        }

        $count = (int) substr($line, $start, $end - $start);

        return $negative ? -$count : $count;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function range_entry_count(array $entry): int
    {
        if (! isset($entry['count']) || ! is_int($entry['count'])) {
            throw new StorageException('Malformed range index count.');
        }

        return $entry['count'];
    }

    /**
     * @param array<string, true> $ids
     * @param array{0: string|null, 1: bool, 2: string|null, 3: bool} $window
     */
    private function collect_range_value_ids(
        string $path,
        string $field,
        string $key,
        mixed $value,
        array &$ids,
        bool $sorted,
        array $window
    ): void {
        if (! is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            return;
        }

        try {
            if ($sorted) {
                fseek($handle, $this->range_seek_offset($field, $window));
            }

            while (false !== ( $line = fgets($handle) )) {
                $line_key = $this->range_line_key($line);
                if (null !== $line_key) {
                    if ($sorted && $this->range_key_after_window($line_key, $window)) {
                        break;
                    }

                    if ($line_key !== $key) {
                        continue;
                    }
                }

                $entry = $this->decode_index_line($line);
                if (($entry['key'] ?? null) !== $key || ($entry['value'] ?? null) !== $value) {
                    continue;
                }

                $this->apply_range_entry_ids($entry, $ids);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, true> $ids
     */
    private function apply_range_entry_ids(array $entry, array &$ids): void
    {
        foreach ($this->ids_from_value_entry($entry) as $id) {
            $ids[ $id ] = true;
        }

        $removed = isset($entry['remove']) && is_array($entry['remove']) ? $entry['remove'] : array();
        foreach ($removed as $id) {
            if (is_string($id)) {
                unset($ids[ $id ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function ids_for_range_value(string $field, mixed $value, ?int $limit = null): array
    {
        $key = $this->range_key($value);
        $ids = array();
        $window = array( $key, true, $key, true );
        $this->collect_range_value_ids($this->range_field_root($field), $field, $key, $value, $ids, true, $window);
        $this->collect_range_value_ids($this->range_delta_field_root($field), $field, $key, $value, $ids, false, $window);

        $result = array_keys($ids);
        sort($result);

        return null === $limit ? $result : array_slice($result, 0, $limit);
    }

    private function range_value_matches(string $operator, mixed $value, mixed $expected, mixed $second_expected): bool
    {
        return match ($operator) {
            'gt' => QueryCondition::compare($value, $expected) > 0,
            'gte' => QueryCondition::compare($value, $expected) >= 0,
            'lt' => QueryCondition::compare($value, $expected) < 0,
            'lte' => QueryCondition::compare($value, $expected) <= 0,
            'between' => QueryCondition::compare($value, $expected) >= 0
                && QueryCondition::compare($value, $second_expected) <= 0,
            'prefix' => is_string($value) && is_string($expected) && str_starts_with($value, $expected),
            default => false,
        };
    }

    private function manifest_path(): string
    {
        return $this->index_root() . '/manifest.jsonc';
    }

    private function index_root(): string
    {
        return $this->store->collection_root() . '/.storh/indexes';
    }

    private function entries_root(): string
    {
        return $this->index_root() . '/entries';
    }

    private function eq_value_root(string $field, mixed $value): string
    {
        return $this->entries_root() . '/eq/' . $this->field_key($field) . '/' . $this->value_key($value) . '.jsonc';
    }

    private function eq_entry_path(string $field, mixed $value): string
    {
        return $this->eq_value_root($field, $value);
    }

    private function compound_entry_path(string $left_field, string $right_field, string $compound_key): string
    {
        return $this->entries_root()
            . '/compound/'
            . $this->field_key($left_field)
            . '-'
            . $this->field_key($right_field)
            . '/'
            . $compound_key
            . '.jsonc';
    }

    private function range_field_root(string $field): string
    {
        return $this->entries_root() . '/range/' . $this->field_key($field) . '.jsonl';
    }

    private function range_delta_field_root(string $field): string
    {
        return $this->entries_root() . '/range/' . $this->field_key($field) . '.delta.jsonl';
    }

    private function range_sparse_index_path(string $field): string
    {
        return $this->entries_root() . '/range/' . $this->field_key($field) . '.idx.jsonc';
    }

    private function range_chunk_path(string $field, int $index): string
    {
        return $this->entries_root() . '/range/.chunks/' . $this->field_key($field) . '-' . $index . '.jsonl';
    }

    private function field_key(string $field): string
    {
        return bin2hex($field);
    }

    private function value_key(mixed $value): string
    {
        if (is_int($value)) {
            return 'i-' . str_replace('-', 'm', (string) $value);
        }

        if (is_string($value)) {
            return strlen($value) <= 96 ? 's-' . bin2hex($value) : 's-' . hash('xxh128', $value);
        }

        if (is_bool($value)) {
            return 'b-' . ( $value ? '1' : '0' );
        }

        if (is_float($value)) {
            return 'f-' . str_replace(array( '-', '.', '+' ), array( 'm', 'd', 'p' ), sprintf('%.17G', $value));
        }

        if (null === $value) {
            return 'n';
        }

        return 'z-' . hash('xxh128', serialize($value));
    }

    private function compound_value_key(mixed $left, mixed $right): string
    {
        return 'c-' . hash('xxh128', $this->value_key($left) . "\0" . $this->value_key($right));
    }

    private function range_key(mixed $value): string
    {
        if (is_int($value) && $value >= 0) {
            return 'n-' . str_pad((string) $value, 17, '0', STR_PAD_LEFT) . 'd000000';
        }

        if (is_int($value) || is_float($value)) {
            return 'n-' . str_replace(array( '-', '.' ), array( 'm', 'd' ), sprintf('%024.6F', (float) $value));
        }

        if (is_string($value)) {
            return 's-' . bin2hex($value);
        }

        if (is_bool($value)) {
            return 'b-' . ( $value ? '1' : '0' );
        }

        return 'z-' . $this->value_key($value);
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     * @param array<string, array{field: string, unique: bool, range: bool, eqRoot?: string}> $definitions
     * @param array<string, mixed> $data
     */
    private function collect_index_entries(array &$buckets, array $definitions, string $id, array $data): void
    {
        $compound_values = array();
        foreach ($definitions as $definition) {
            $field = $definition['field'];
            if (! array_key_exists($field, $data) || ! $this->indexable($data[ $field ])) {
                continue;
            }

            $value = $data[ $field ];
            if ($definition['range']) {
                $this->append_range_entry($field, $value, array( $id ), array());
                continue;
            }

            $this->collect_index_entry(
                $buckets,
                $this->eq_entry_path($field, $value),
                $field,
                $this->value_key($value),
                $value,
                $id
            );
            if (! $definition['unique']) {
                $compound_values[ $field ] = $value;
            }
        }

        $this->collect_compound_index_entries($buckets, $compound_values, $id);
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool}> $definitions
     * @return array<string, array{field: string, unique: bool, range: bool, fieldKey: string, eqRoot: string}>
     */
    private function rebuild_definitions(array $definitions): array
    {
        $prepared = array();
        foreach ($definitions as $field => $definition) {
            $definition['fieldKey'] = $this->field_key($definition['field']);
            $definition['eqRoot'] = $this->entries_root() . '/eq/' . $definition['fieldKey'];
            $prepared[ $field ] = $definition;
        }

        return $prepared;
    }

    /**
     * @param array<string, array{field: string, unique: bool, range: bool, fieldKey: string, eqRoot: string}> $definitions
     * @return array<string, array<string, string>>
     */
    private function rebuild_compound_roots(array $definitions): array
    {
        $roots = array();
        $fields = array_keys(array_filter(
            $definitions,
            static fn(array $definition): bool => ! $definition['range'] && ! $definition['unique']
        ));
        $field_count = count($fields);
        for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
            $left = $fields[ $left_index ];
            for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                $right = $fields[ $right_index ];
                $roots[ $left ][ $right ] = $this->entries_root()
                    . '/compound/'
                    . $definitions[ $left ]['fieldKey']
                    . '-'
                    . $definitions[ $right ]['fieldKey'];
            }
        }

        return $roots;
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     * @param array<string, list<string>> $range_buckets
     * @param array<string, array<string, string>> $compound_roots
     * @param array<string, string> $compound_key_cache
     * @param array<string, array{field: string, unique: bool, range: bool, fieldKey: string, eqRoot: string}> $definitions
     * @param array<string, mixed> $data
     */
    private function collect_rebuild_index_entries(
        array &$buckets,
        array &$range_buckets,
        int &$range_entries,
        int &$eq_entries,
        array $compound_roots,
        array &$compound_key_cache,
        array $definitions,
        string $id,
        array $data
    ): void {
        $compound_fields = array();
        $compound_value_keys = array();
        foreach ($definitions as $definition) {
            $field = $definition['field'];
            if (! array_key_exists($field, $data) || ! $this->indexable($data[ $field ])) {
                continue;
            }

            $value = $data[ $field ];
            if ($definition['range']) {
                $this->collect_range_entry($range_buckets, $range_entries, $field, $value, $id);
                continue;
            }

            $value_key = $this->value_key($value);
            $this->collect_index_entry(
                $buckets,
                $definition['eqRoot'] . '/' . $value_key . '.jsonc',
                $field,
                $value_key,
                $value,
                $id
            );
            if (! $definition['unique']) {
                $compound_fields[] = $field;
                $compound_value_keys[] = $value_key;
            }
            $eq_entries++;
        }

        $eq_entries += $this->collect_rebuild_compound_index_entries(
            $buckets,
            $compound_fields,
            $compound_value_keys,
            $compound_roots,
            $compound_key_cache,
            $id
        );
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     * @param list<string> $fields
     * @param list<string> $value_keys
     * @param array<string, array<string, string>> $compound_roots
     * @param array<string, string> $compound_key_cache
     */
    private function collect_rebuild_compound_index_entries(
        array &$buckets,
        array $fields,
        array $value_keys,
        array $compound_roots,
        array &$compound_key_cache,
        string $id
    ): int {
        $field_count = count($fields);
        if ($field_count < 2) {
            return 0;
        }

        $entries = 0;
        for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
            $left = $fields[ $left_index ];
            for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                $right = $fields[ $right_index ];
                $compound_key_source = $value_keys[ $left_index ] . "\0" . $value_keys[ $right_index ];
                $key = $compound_key_cache[ $compound_key_source ] ??= 'c-' . hash('xxh128', $compound_key_source);

                $this->collect_index_entry(
                    $buckets,
                    $compound_roots[ $left ][ $right ] . '/' . $key . '.jsonc',
                    $left . "\t" . $right,
                    $key,
                    null,
                    $id
                );
                $entries++;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     * @param array<string, mixed> $values
     */
    private function collect_compound_index_entries(array &$buckets, array $values, string $id): int
    {
        if (count($values) < 2) {
            return 0;
        }

        $fields = array_keys($values);
        $entries = 0;
        $field_count = count($fields);
        for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
            $left = $fields[ $left_index ];
            for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                $right = $fields[ $right_index ];
                $key = $this->compound_value_key($values[ $left ], $values[ $right ]);
                $this->collect_index_entry(
                    $buckets,
                    $this->compound_entry_path($left, $right, $key),
                    $left . "\t" . $right,
                    $key,
                    null,
                    $id
                );
                $entries++;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function remove_compound_entries(string $id, array $values): void
    {
        if (count($values) < 2) {
            return;
        }

        $fields = array_keys($values);
        $field_count = count($fields);
        for ($left_index = 0; $left_index < $field_count - 1; $left_index++) {
            $left = $fields[ $left_index ];
            for ($right_index = $left_index + 1; $right_index < $field_count; $right_index++) {
                $right = $fields[ $right_index ];
                $key = $this->compound_value_key($values[ $left ], $values[ $right ]);
                $this->remove_id_from_entry(
                    $this->compound_entry_path($left, $right, $key),
                    $key,
                    $id
                );
            }
        }
    }

    /**
     * @param array<string, list<string>> $range_buckets
     */
    private function collect_range_entry(array &$range_buckets, int &$range_entries, string $field, mixed $value, string $id): void
    {
        $key = $this->range_key($value);
        $value_key = $this->value_key($value);
        $range_buckets[ $field ][] = $key . "\t" . $value_key . "\t"
            . '{"key":"' . $key . '","value":' . json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            ) . ',"count":1,"ids":["' . $id . '"],"remove":[]}' . "\n";
        $range_entries++;
    }

    /**
     * @param array<string, list<string>> $range_buckets
     * @param array<string, list<string>> $range_chunks
     */
    private function flush_range_chunks(array $range_buckets, array &$range_chunks): void
    {
        foreach ($range_buckets as $field => $entries) {
            sort($entries, SORT_STRING);
            $chunk = $this->range_chunk_path($field, count($range_chunks[ $field ] ?? array()));
            AtomicFilesystem::write_atomic($chunk, implode('', $entries));
            $range_chunks[ $field ][] = $chunk;
        }
    }

    /**
     * @param array<string, list<string>> $range_chunks
     */
    private function write_range_chunks(array $range_chunks): void
    {
        foreach ($range_chunks as $field => $chunks) {
            $this->merge_range_chunks($field, $chunks);
        }
    }

    /**
     * @param list<string> $chunks
     */
    private function merge_range_chunks(string $field, array $chunks): void
    {
        $handles = array();
        $heads = array();

        foreach ($chunks as $index => $chunk) {
            $handle = @fopen($chunk, 'rb');
            if (false === $handle) {
                throw new StorageException('Could not read range index chunk: ' . $chunk);
            }

            $handles[ $index ] = $handle;
            $line = fgets($handle);
            if (false !== $line) {
                $heads[ $index ] = $line;
            }
        }

        $contents = '';
        $offset = 0;
        $line_count = 0;
        $checkpoints = array();

        try {
            while (array() !== $heads) {
                $selected = $this->smallest_range_chunk_line($heads);
                $line = $heads[ $selected ];
                [ $key, $encoded ] = $this->range_chunk_line_parts($line);

                if (0 === $line_count % self::RANGE_SPARSE_STRIDE) {
                    $checkpoints[] = array(
                        'key'    => $key,
                        'offset' => $offset,
                    );
                }

                $contents .= $encoded;
                $offset += strlen($encoded);
                $line_count++;

                $next = fgets($handles[ $selected ]);
                if (false === $next) {
                    unset($heads[ $selected ]);
                } else {
                    $heads[ $selected ] = $next;
                }
            }
        } finally {
            foreach ($handles as $handle) {
                fclose($handle);
            }
        }

        AtomicFilesystem::write_atomic($this->range_field_root($field), $contents);
        AtomicFilesystem::write_atomic(
            $this->range_sparse_index_path($field),
            $this->encode_index_object(
                array(
                    'stride'      => self::RANGE_SPARSE_STRIDE,
                    'checkpoints' => $checkpoints,
                )
            )
        );
        $this->range_sparse_checkpoints_cache[ $field ] = $checkpoints;

        foreach ($chunks as $chunk) {
            @unlink($chunk);
        }
    }

    /**
     * @param array<int, string> $heads
     */
    private function smallest_range_chunk_line(array $heads): int
    {
        $selected = array_key_first($heads);
        if (null === $selected) {
            throw new StorageException('Cannot merge empty range index chunk set.');
        }

        foreach ($heads as $index => $line) {
            if (strcmp($line, $heads[ $selected ]) < 0) {
                $selected = $index;
            }
        }

        return $selected;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range_chunk_line_parts(string $line): array
    {
        $first = strpos($line, "\t");
        $second = false === $first ? false : strpos($line, "\t", $first + 1);
        if (false === $first || false === $second) {
            throw new StorageException('Malformed range index chunk.');
        }

        return array(
            substr($line, 0, $first),
            substr($line, $second + 1),
        );
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     */
    private function collect_index_entry(array &$buckets, string $path, string $field, string $value_key, mixed $value, string $id): void
    {
        $buckets[ $path ] ??= array(
            'path'  => $path,
            'field' => $field,
            'key'   => $value_key,
            'value' => $value,
            'ids'   => array(),
        );

        $buckets[ $path ]['ids'][ $id ] = true;
    }

    /**
     * @param array<string, array{path: string, field: string, key: string, value: mixed, ids: array<string, true>}> $buckets
     */
    private function merge_index_buckets(array $buckets, bool $sort_ids = true): void
    {
        foreach ($buckets as $bucket) {
            $ids = is_file($bucket['path'])
                ? array_fill_keys($this->ids_from_value_entry(AtomicFilesystem::read_jsonc_object($bucket['path'])), true)
                : array();

            foreach ($bucket['ids'] as $id => $_) {
                $ids[ $id ] = true;
            }

            $this->write_value_index($bucket['path'], $bucket['field'], $bucket['key'], $bucket['value'], $ids, $sort_ids);
        }
    }

    /**
     * @param array<string, true> $ids
     */
    private function write_value_index(string $path, string $field, string $key, mixed $value, array $ids, bool $sort_ids = true): void
    {
        $encoded_ids = array_keys($ids);
        if ($sort_ids) {
            sort($encoded_ids);
        }

        AtomicFilesystem::write_atomic(
            $path,
            $this->encode_index_object(
                array(
                    'field' => $field,
                    'key'   => $key,
                    'count' => count($encoded_ids),
                    'value' => $value,
                    'ids'   => $encoded_ids,
                )
            )
        );
    }

    private function remove_id_from_entry(string $path, string $value_key, string $id): void
    {
        if (! is_file($path)) {
            return;
        }

        $entry = AtomicFilesystem::read_jsonc_object($path);
        $field = isset($entry['field']) && is_string($entry['field']) ? $entry['field'] : '';
        if ('' === $field) {
            return;
        }

        if (($entry['key'] ?? null) !== $value_key) {
            return;
        }

        $ids = array_fill_keys($this->ids_from_value_entry($entry), true);
        unset($ids[ $id ]);
        if (array() === $ids) {
            @unlink($path);
            return;
        }

        $this->write_value_index($path, $field, $value_key, $entry['value'] ?? null, $ids);
    }

    /**
     * @return list<string>
     */
    private function ids_from_value_file(string $path, string $expected_key, ?int $limit = null): array
    {
        if (null !== $limit) {
            return $this->limited_ids_from_value_file($path, $expected_key, $limit);
        }

        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new StorageException('Could not read equality index: ' . $path);
        }

        $key_marker = '"key":"' . $expected_key . '"';
        $key_offset = strpos($contents, $key_marker);
        if (false === $key_offset) {
            return array();
        }

        $ids_marker = ',"ids":[';
        $ids_offset = strpos($contents, $ids_marker, $key_offset + strlen($key_marker));
        if (false === $ids_offset) {
            throw new StorageException('Malformed equality index: ' . $path);
        }

        $position = $ids_offset + strlen($ids_marker);
        $end = strpos($contents, ']', $position);
        if (false === $end) {
            throw new StorageException('Malformed equality index: ' . $path);
        }

        if ($end === $position) {
            return array();
        }

        if ('"' !== $contents[ $position ] || '"' !== $contents[ $end - 1 ]) {
            throw new StorageException('Malformed equality index: ' . $path);
        }

        return explode('","', substr($contents, $position + 1, $end - $position - 2));
    }

    private function count_ids_from_value_file(string $path, string $expected_key, ?int $limit = null): int
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new StorageException('Could not read equality index: ' . $path);
        }

        $count = null;
        $key_marker = '"key":"' . $expected_key . '"';
        $count_marker = ',"count":';
        $buffer = '';
        $key_offset = null;

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, self::VALUE_COUNT_SCAN_CHUNK_BYTES);
                if (false === $chunk) {
                    throw new StorageException('Could not read equality index: ' . $path);
                }

                $buffer .= $chunk;
                if (null === $key_offset) {
                    $offset = strpos($buffer, $key_marker);
                    if (false === $offset) {
                        continue;
                    }

                    $key_offset = $offset;
                }

                $search_offset = $key_offset + strlen($key_marker);
                $count_offset = strpos($buffer, $count_marker, $search_offset);
                if (false === $count_offset) {
                    continue;
                }

                $start = $count_offset + strlen($count_marker);
                $end = $start;
                $length = strlen($buffer);
                while ($end < $length && ctype_digit($buffer[ $end ])) {
                    $end++;
                }

                if ($end === $start) {
                    throw new StorageException('Malformed equality index: ' . $path);
                }

                if ($end === $length && ! feof($handle)) {
                    continue;
                }

                $count = (int) substr($buffer, $start, $end - $start);
                break;
            }
        } finally {
            fclose($handle);
        }

        if (null === $key_offset) {
            return 0;
        }

        if (null === $count) {
            throw new StorageException('Malformed equality index: ' . $path);
        }

        return null === $limit ? $count : min($count, $limit);
    }

    /**
     * @return list<string>
     */
    private function limited_ids_from_value_file(string $path, string $expected_key, int $limit): array
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new StorageException('Could not read equality index: ' . $path);
        }

        $key_marker = '"key":"' . $expected_key . '"';
        $ids_marker = ',"ids":[';
        $buffer = '';
        $key_offset = null;
        $position = null;
        $ids = array();
        $count = 0;
        $needs_more = false;

        try {
            while (! feof($handle) && $count < $limit) {
                $chunk = fread($handle, 8192);
                if (false === $chunk) {
                    throw new StorageException('Could not read equality index: ' . $path);
                }

                $buffer .= $chunk;
                if (null === $key_offset) {
                    $offset = strpos($buffer, $key_marker);
                    if (false === $offset) {
                        continue;
                    }

                    $key_offset = $offset;
                }

                if (null === $position) {
                    $offset = strpos($buffer, $ids_marker, $key_offset + strlen($key_marker));
                    if (false === $offset) {
                        continue;
                    }

                    $position = $offset + strlen($ids_marker);
                }

                $needs_more = false;
                while ($count < $limit) {
                    $open = strpos($buffer, '"', $position);
                    $end = strpos($buffer, ']', $position);
                    if (false === $open) {
                        if (false !== $end) {
                            return $ids;
                        }

                        $needs_more = true;
                        break;
                    }

                    if (false !== $end && $end < $open) {
                        return $ids;
                    }

                    $close = strpos($buffer, '"', $open + 1);
                    if (false === $close) {
                        $needs_more = true;
                        break;
                    }

                    $ids[] = substr($buffer, $open + 1, $close - $open - 1);
                    $count++;

                    $position = $close + 1;
                }
            }
        } finally {
            fclose($handle);
        }

        if (null === $key_offset) {
            return array();
        }

        if (null === $position || $needs_more) {
            throw new StorageException('Malformed equality index: ' . $path);
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function ids_from_value_entry(array $entry, ?int $limit = null): array
    {
        $ids = array();
        $items = isset($entry['ids']) && is_array($entry['ids']) ? $entry['ids'] : array();
        foreach ($items as $id) {
            if (is_string($id)) {
                $ids[] = $id;
                if (null !== $limit && count($ids) >= $limit) {
                    return $ids;
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode_index_object(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * @param list<string> $ids
     * @param list<string> $remove
     */
    private function append_range_entry(string $field, mixed $value, array $ids, array $remove): void
    {
        if (array() === $ids && array() === $remove) {
            return;
        }

        $path = $this->range_delta_field_root($field);
        AtomicFilesystem::ensure_directory(dirname($path));
        $line = $this->encode_index_object(
            array(
                'key'    => $this->range_key($value),
                'value'  => $value,
                'count'  => count($ids) - count($remove),
                'ids'    => $ids,
                'remove' => $remove,
            )
        );

        AtomicFilesystem::append($path, $line);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_index_line(string $line): array
    {
        try {
            $decoded = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return array();
        }

        if (! is_array($decoded) || ( array() !== $decoded && array_is_list($decoded) )) {
            return array();
        }

        return $this->string_keyed($decoded);
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function string_keyed(array $value): array
    {
        foreach ($value as $key => $_item) {
            if (! is_string($key)) {
                $object = array();
                foreach ($value as $copy_key => $item) {
                    if (is_string($copy_key)) {
                        $object[ $copy_key ] = $item;
                    }
                }

                return $object;
            }
        }

        return $value;
    }

    private function indexable(mixed $value): bool
    {
        return null === $value || is_scalar($value);
    }

    private function assert_field(string $field): void
    {
        if ('' === trim($field)) {
            throw new StorageException('Index field cannot be empty.');
        }
    }

    private function delete_directory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }

        @rmdir($directory);
    }
}
