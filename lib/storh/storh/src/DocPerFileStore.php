<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class DocPerFileStore implements FileStoreInterface
{
    private const CACHE_HASH_ALGORITHM = 'xxh128';

    private const WRITE_CACHE_LIMIT = 100000;

    private const JSONL_EXPORT_BUFFER_BYTES = 1048576;

    /** @var callable(): string */
    private mixed $id_generator;

    private bool $trusted_generated_ids;

    private CacheInterface $cache;

    private bool $cache_enabled;

    private string $cache_scope;

    private ?DocStoreIndexManager $index_manager = null;

    private string $collection_path;

    private string $data_path;

    private string $temp_prefix;

    private int $temp_counter = 0;

    /** @var array<string, true> */
    private array $known_directories = array();

    /** @var array<string, string>|null */
    private ?array $record_path_cache = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $record_data_cache = null;

    private bool $record_cache_ordered = true;

    /** @var list<string>|null */
    private ?array $record_sorted_ids_cache = null;

    private ?string $record_cache_last_id = null;

    private ?string $last_record_content_path = null;

    private ?string $last_record_content_hash = null;

    private ?int $last_record_content_mtime = null;

    private ?int $last_record_content_size = null;

    private ?bool $index_manifest_exists = null;

    /** @var resource|null */
    private mixed $write_lock_handle = null;

    private int $write_lock_depth = 0;

    /** @var array<string, array{mtime: int, size: int, hash: string, data: array<string, mixed>}> */
    private array $validated_record_cache = array();

    private ?int $validated_record_cache_max_bytes;

    public function __construct(
        private readonly string $root,
        private readonly string $collection,
        ?callable $id_generator = null,
        ?CacheInterface $cache = null,
        private readonly ?Schema $schema = null,
        private readonly string $cache_validation = CacheValidation::STAT
    ) {
        CacheValidation::assert_valid($this->cache_validation);

        if (null !== $this->schema && $this->schema->collection_name() !== $this->collection) {
            throw new StorageException('Schema collection does not match DocStore collection.');
        }

        $this->trusted_generated_ids = null === $id_generator;
        $this->id_generator          = $id_generator ?? static fn(): string => UuidV7::generate();
        $this->cache                 = $cache ?? Cache::null();
        $this->cache_enabled = ! $this->cache instanceof NullCache;
        $this->collection_path = rtrim($this->root, '/\\') . '/' . $this->collection;
        $this->cache_scope     = hash(self::CACHE_HASH_ALGORITHM, $this->collection_path);
        $this->data_path       = $this->collection_path . '/data';
        $this->temp_prefix     = (function_exists('getmypid') ? (string) getmypid() : 'p' . bin2hex(random_bytes(4))) . '.' . bin2hex(random_bytes(4));
        $this->validated_record_cache_max_bytes = self::default_validated_record_cache_max_bytes();
        AtomicFilesystem::cleanup_temp_files($this->collection_root());
        if (! is_dir($this->data_root())) {
            $this->record_path_cache = array();
            $this->record_data_cache = array();
        }

        if (null !== $this->schema) {
            $this->indexes()->apply_schema($this->schema)->sync();
        }
    }

    public function __destruct()
    {
        if (is_resource($this->write_lock_handle)) {
            fclose($this->write_lock_handle);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(array $data, ?string $id = null): StorageRecord
    {
        return $this->with_write_lock(fn(): StorageRecord => $this->put_unlocked($data, $id));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function put_unlocked(array $data, ?string $id = null): StorageRecord
    {
        $generated = null === $id;
        $id ??= ( $this->id_generator )();
        $this->assert_record_id($id, $generated);
        $this->schema?->validate($data);

        $indexes = $this->active_indexes();
        $old = null === $indexes || $generated ? null : $this->get($id);
        if (null !== $indexes) {
            $indexes->validate_unique($id, $data, $old?->data());
        }

        $record = new StorageRecord($id, $data);
        $directory = $this->record_directory_for_id($id);
        $path = $directory . '/' . $id . '.jsonc';
        $this->write_record_file($directory, $path, $id, $data);
        $this->remember_written_record($id, $path, $data);

        if (null !== $indexes) {
            $indexes->update_record($id, $data, $old?->data());
        }
        if ($this->cache_enabled) {
            $this->cache_record($record, $path);
        }

        return $record;
    }

    /**
     * @param iterable<array<string, mixed>> $records
     * @return list<StorageRecord>
     */
    public function putMany(iterable $records): array
    {
        return $this->with_write_lock(
            function () use ($records): array {
                $stored = array();
                $indexes = $this->active_indexes();
                $has_indexes = null !== $indexes;

                foreach ($records as $record) {
                    $id   = isset($record['id']) && is_string($record['id']) ? $record['id'] : null;
                    $data = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
                    $generated = null === $id;
                    $id ??= ( $this->id_generator )();
                    $this->assert_record_id($id, $generated);

                    /** @var array<string, mixed> $data */
                    $this->schema?->validate($data);

                    $old = $generated || ! $has_indexes ? null : $this->get($id);
                    if ($has_indexes) {
                        $indexes->validate_unique($id, $data, $old?->data());
                    }

                    $directory = $this->record_directory_for_id($id);
                    $path = $directory . '/' . $id . '.jsonc';
                    $this->write_record_file($directory, $path, $id, $data);
                    $this->remember_written_record($id, $path, $data);

                    if ($has_indexes) {
                        $indexes->update_record($id, $data, $old?->data());
                    }

                    $storage_record = new StorageRecord($id, $data);
                    if ($this->cache_enabled) {
                        $this->cache_record($storage_record, $path);
                    }
                    $stored[] = $storage_record;
                }

                return $stored;
            }
        );
    }

    /**
     * @param iterable<array<string, mixed>> $records
     */
    public function putStream(iterable $records): int
    {
        return $this->with_write_lock(
            function () use ($records): int {
                $count = 0;
                $indexes = $this->active_indexes();
                $has_indexes = null !== $indexes;

                foreach ($records as $record) {
                    $id   = isset($record['id']) && is_string($record['id']) ? $record['id'] : null;
                    $data = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
                    $generated = null === $id;
                    $id ??= ( $this->id_generator )();
                    $this->assert_record_id($id, $generated);

                    /** @var array<string, mixed> $data */
                    $this->schema?->validate($data);

                    $old = $generated || ! $has_indexes ? null : $this->get($id);
                    if ($has_indexes) {
                        $indexes->validate_unique($id, $data, $old?->data());
                    }

                    $directory = $this->record_directory_for_id($id);
                    $path = $directory . '/' . $id . '.jsonc';
                    $this->write_record_file($directory, $path, $id, $data);
                    $this->remember_written_record($id, $path, $data);

                    if ($has_indexes) {
                        $indexes->update_record($id, $data, $old?->data());
                    }

                    if ($this->cache_enabled) {
                        $this->cache_record(new StorageRecord($id, $data), $path);
                    }

                    $count++;
                }

                return $count;
            }
        );
    }

    public function get(string $id): ?StorageRecord
    {
        UuidV7::assert_valid($id);
        $path = $this->record_path_for_id($id);

        if (CacheValidation::TRUST !== $this->cache_validation) {
            $local = $this->validated_record($id, $path);
            if ($local instanceof StorageRecord || false === $local) {
                return false === $local ? null : $local;
            }
        }

        if ($this->cache_enabled) {
            $cached = $this->cached_record($id, $path);
            if ($cached instanceof StorageRecord || false === $cached) {
                return false === $cached ? null : $cached;
            }
        }

        if (CacheValidation::TRUST === $this->cache_validation && isset($this->record_data_cache[ $id ])) {
            return new StorageRecord($id, $this->record_data_cache[ $id ]);
        }

        if (! is_file($path)) {
            if ($this->cache_enabled) {
                $this->cache_missing($id, $path);
            }
            return null;
        }

        $record = $this->record_from_file($path, $id);
        if (! $this->cache_enabled) {
            $this->remember_validated_record_from_path($id, $record->data(), $path);
        }
        if (CacheValidation::TRUST === $this->cache_validation) {
            $this->remember_trusted_read_record($id, $record->data());
        }
        if ($this->cache_enabled) {
            $this->cache_record($record, $path);
        }

        return $record;
    }

    public function delete(string $id): void
    {
        $this->with_write_lock(
            function () use ($id): void {
                UuidV7::assert_valid($id);
                $path = $this->record_path_for_id($id);
                $indexes = $this->active_indexes();
                $old = null !== $indexes && is_file($path) ? $this->get($id) : null;

                if (is_file($path) && ! @unlink($path)) {
                    throw new StorageException('Could not delete storage record: ' . $id);
                }
                if (null !== $this->record_path_cache) {
                    unset($this->record_path_cache[ $id ]);
                    $this->record_sorted_ids_cache = null;
                }
                if (null !== $this->record_data_cache) {
                    unset($this->record_data_cache[ $id ]);
                }
                unset($this->validated_record_cache[ $id ]);

                if (null !== $indexes && null !== $old) {
                    $indexes->remove_record($id, $old->data());
                }

                if ($this->cache_enabled) {
                    $this->cache_missing($id, $path);
                }
            }
        );
    }

    /**
     * @return \Generator<int, StorageRecord>
     */
    public function stream(?RecordQuery $query = null): \Generator
    {
        $query ??= RecordQuery::all();
        $count = 0;
        $filters_records = $query->filters_records();
        $limit = $query->limit_value();

        if ($this->can_use_record_data_fast_path()) {
            if (! $filters_records) {
                if ($this->record_cache_ordered) {
                    foreach ($this->record_data_cache as $id => $data) {
                        yield new StorageRecord($id, $data);
                        $count++;

                        if (null !== $limit && $count >= $limit) {
                            return;
                        }
                    }

                    return;
                }

                foreach ($this->cached_record_ids() as $id) {
                    $data = $this->record_data_cache[ $id ] ?? null;
                    if (null === $data) {
                        continue;
                    }

                    yield new StorageRecord($id, $data);
                    $count++;

                    if (null !== $limit && $count >= $limit) {
                        return;
                    }
                }

                return;
            }

            if ($this->record_cache_ordered) {
                foreach ($this->record_data_cache as $id => $data) {
                    if (! $query->matches_data($id, $data)) {
                        continue;
                    }

                    yield new StorageRecord($id, $data);
                    $count++;

                    if (null !== $limit && $count >= $limit) {
                        return;
                    }
                }

                return;
            }

            foreach ($this->cached_record_ids() as $id) {
                $data = $this->record_data_cache[ $id ] ?? null;
                if (null === $data) {
                    continue;
                }

                if (! $query->matches_data($id, $data)) {
                    continue;
                }

                yield new StorageRecord($id, $data);
                $count++;

                if (null !== $limit && $count >= $limit) {
                    return;
                }
            }

            return;
        }

        foreach ($this->record_paths() as $path) {
            $id = basename($path, '.jsonc');

            try {
                $record = $this->record_from_file($path, $id);
            } catch (\Throwable $throwable) {
                if ($query->handle_error($id, $throwable)) {
                    continue;
                }

                throw $throwable;
            }

            if ($filters_records && ! $query->matches($record)) {
                continue;
            }

            yield $record;
            $count++;

            if (null !== $limit && $count >= $limit) {
                return;
            }
        }
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function indexes(): DocStoreIndexManager
    {
        $this->index_manager ??= new DocStoreIndexManager($this);

        return $this->index_manager;
    }

    private function active_indexes(): ?DocStoreIndexManager
    {
        if (null !== $this->index_manager) {
            return array() === $this->index_manager->definitions() ? null : $this->index_manager;
        }

        if (! $this->has_index_manifest()) {
            return null;
        }

        $indexes = $this->indexes();

        return array() === $indexes->definitions() ? null : $indexes;
    }

    private function has_index_manifest(): bool
    {
        $this->index_manifest_exists ??= is_file($this->collection_root() . '/.storh/indexes/manifest.jsonc');

        return $this->index_manifest_exists;
    }

    private function indexed_count(QueryBuilder $query): ?int
    {
        if (null !== $query->cursor_id()) {
            return null;
        }

        return $this->active_indexes()?->candidate_count($query);
    }

    /**
     * @return list<StorageRecord>
     */
    public function query_records(QueryBuilder $query): array
    {
        $ids     = $this->active_indexes()?->candidate_ids($query);
        $records = array();
        $limit   = $query->limit_value();
        $can_stop_early = null !== $limit && ! $query->has_ordering();
        $simple_equal = $query->simple_equal_filter();

        if (null !== $ids) {
            foreach ($ids as $id) {
                $record = $this->get($id);
                if (null !== $record && $query->matches($record)) {
                    $records[] = $record;
                    if ($can_stop_early && count($records) >= $limit) {
                        return $records;
                    }
                }
            }

            return $records;
        }

        if ($this->can_use_record_data_fast_path()) {
            if (null !== $simple_equal) {
                $field = $simple_equal['field'];
                $value = $simple_equal['value'];
                if ($this->record_cache_ordered) {
                    if ('id' === $field) {
                        foreach ($this->record_data_cache as $id => $data) {
                            if ($id !== $value) {
                                continue;
                            }

                            $records[] = new StorageRecord($id, $data);
                            if ($can_stop_early && count($records) >= $limit) {
                                return $records;
                            }
                        }

                        return $records;
                    }

                    if (null === $value) {
                        foreach ($this->record_data_cache as $id => $data) {
                            $actual = $data[ $field ] ?? null;
                            if (( null === $actual && ! array_key_exists($field, $data) ) || null !== $actual) {
                                continue;
                            }

                            $records[] = new StorageRecord($id, $data);
                            if ($can_stop_early && count($records) >= $limit) {
                                return $records;
                            }
                        }

                        return $records;
                    }

                    foreach ($this->record_data_cache as $id => $data) {
                        if (( $data[ $field ] ?? null ) !== $value) {
                            continue;
                        }

                        $records[] = new StorageRecord($id, $data);
                        if ($can_stop_early && count($records) >= $limit) {
                            return $records;
                        }
                    }

                    return $records;
                }

                if ('id' === $field) {
                    foreach ($this->cached_record_ids() as $id) {
                        if ($id !== $value) {
                            continue;
                        }

                        $data = $this->record_data_cache[ $id ] ?? null;
                        if (null === $data) {
                            continue;
                        }

                        $records[] = new StorageRecord($id, $data);
                        if ($can_stop_early && count($records) >= $limit) {
                            return $records;
                        }
                    }

                    return $records;
                }

                if (null === $value) {
                    foreach ($this->cached_record_ids() as $id) {
                        $data = $this->record_data_cache[ $id ] ?? null;
                        if (null === $data) {
                            continue;
                        }

                        $actual = $data[ $field ] ?? null;
                        if (( null === $actual && ! array_key_exists($field, $data) ) || null !== $actual) {
                            continue;
                        }

                        $records[] = new StorageRecord($id, $data);
                        if ($can_stop_early && count($records) >= $limit) {
                            return $records;
                        }
                    }

                    return $records;
                }

                foreach ($this->cached_record_ids() as $id) {
                    $data = $this->record_data_cache[ $id ] ?? null;
                    if (null === $data || ( $data[ $field ] ?? null ) !== $value) {
                        continue;
                    }

                    $records[] = new StorageRecord($id, $data);
                    if ($can_stop_early && count($records) >= $limit) {
                        return $records;
                    }
                }

                return $records;
            }

            if ($this->record_cache_ordered) {
                foreach ($this->record_data_cache as $id => $data) {
                    if (! $query->matches_data($id, $data)) {
                        continue;
                    }

                    $records[] = new StorageRecord($id, $data);
                    if ($can_stop_early && count($records) >= $limit) {
                        return $records;
                    }
                }

                return $records;
            }

            foreach ($this->cached_record_ids() as $id) {
                $data = $this->record_data_cache[ $id ] ?? null;
                if (null === $data || ! $query->matches_data($id, $data)) {
                    continue;
                }

                $records[] = new StorageRecord($id, $data);
                if ($can_stop_early && count($records) >= $limit) {
                    return $records;
                }
            }

            return $records;
        }

        foreach ($this->stream() as $record) {
            if ($query->matches($record)) {
                $records[] = $record;
                if ($can_stop_early && count($records) >= $limit) {
                    return $records;
                }
            }
        }

        return $records;
    }

    public function first_record(QueryBuilder $query): ?StorageRecord
    {
        $ids = $this->active_indexes()?->candidate_ids($query);
        $simple_equal = $query->simple_equal_filter();

        if (null !== $ids) {
            foreach ($ids as $id) {
                $record = $this->get($id);
                if (null !== $record && $query->matches($record)) {
                    return $record;
                }
            }

            return null;
        }

        if ($this->can_use_record_data_fast_path()) {
            if (null !== $simple_equal) {
                $field = $simple_equal['field'];
                $value = $simple_equal['value'];
                if ($this->record_cache_ordered) {
                    if ('id' === $field) {
                        foreach ($this->record_data_cache as $id => $data) {
                            if ($id !== $value) {
                                continue;
                            }

                            return new StorageRecord($id, $data);
                        }

                        return null;
                    }

                    if (null === $value) {
                        foreach ($this->record_data_cache as $id => $data) {
                            $actual = $data[ $field ] ?? null;
                            if (( null === $actual && ! array_key_exists($field, $data) ) || null !== $actual) {
                                continue;
                            }

                            return new StorageRecord($id, $data);
                        }

                        return null;
                    }

                    foreach ($this->record_data_cache as $id => $data) {
                        if (( $data[ $field ] ?? null ) !== $value) {
                            continue;
                        }

                        return new StorageRecord($id, $data);
                    }

                    return null;
                }

                if ('id' === $field) {
                    foreach ($this->cached_record_ids() as $id) {
                        if ($id !== $value) {
                            continue;
                        }

                        $data = $this->record_data_cache[ $id ] ?? null;
                        if (null !== $data) {
                            return new StorageRecord($id, $data);
                        }
                    }

                    return null;
                }

                if (null === $value) {
                    foreach ($this->cached_record_ids() as $id) {
                        $data = $this->record_data_cache[ $id ] ?? null;
                        if (null === $data) {
                            continue;
                        }

                        $actual = $data[ $field ] ?? null;
                        if (( null === $actual && ! array_key_exists($field, $data) ) || null !== $actual) {
                            continue;
                        }

                        return new StorageRecord($id, $data);
                    }

                    return null;
                }

                foreach ($this->cached_record_ids() as $id) {
                    $data = $this->record_data_cache[ $id ] ?? null;
                    if (null !== $data && ( $data[ $field ] ?? null ) === $value) {
                        return new StorageRecord($id, $data);
                    }
                }

                return null;
            }

            if ($this->record_cache_ordered) {
                foreach ($this->record_data_cache as $id => $data) {
                    if (! $query->matches_data($id, $data)) {
                        continue;
                    }

                    return new StorageRecord($id, $data);
                }

                return null;
            }

            foreach ($this->cached_record_ids() as $id) {
                $data = $this->record_data_cache[ $id ] ?? null;
                if (null === $data || ! $query->matches_data($id, $data)) {
                    continue;
                }

                return new StorageRecord($id, $data);
            }

            return null;
        }

        foreach ($this->stream() as $record) {
            if ($query->matches($record)) {
                return $record;
            }
        }

        return null;
    }

    public function query_records_are_ordered(QueryBuilder $query): bool
    {
        return $this->active_indexes()?->candidate_order_satisfies($query) ?? false;
    }

    public function count_records(QueryBuilder $query): int
    {
        $indexed_count = $this->indexed_count($query);
        if (null !== $indexed_count) {
            return $indexed_count;
        }

        $limit = $query->limit_value();
        $count = 0;

        $ids = $this->active_indexes()?->candidate_ids($query);
        if (null !== $ids) {
            foreach ($ids as $id) {
                $record = $this->get($id);
                if (null === $record || ! $query->matches($record)) {
                    continue;
                }

                $count++;
                if (null !== $limit && $count >= $limit) {
                    return $count;
                }
            }

            return $count;
        }

        if ($this->can_use_record_data_fast_path()) {
            $simple_equal = $query->simple_equal_filter();
            if (null !== $simple_equal) {
                $field = $simple_equal['field'];
                $value = $simple_equal['value'];
                if ('id' === $field) {
                    foreach ($this->record_data_cache as $id => $_data) {
                        if ($id !== $value) {
                            continue;
                        }

                        $count++;
                        if (null !== $limit && $count >= $limit) {
                            return $count;
                        }
                    }

                    return $count;
                }

                if (null === $value) {
                    foreach ($this->record_data_cache as $data) {
                        $actual = $data[ $field ] ?? null;
                        if (( null === $actual && ! array_key_exists($field, $data) ) || null !== $actual) {
                            continue;
                        }

                        $count++;
                        if (null !== $limit && $count >= $limit) {
                            return $count;
                        }
                    }

                    return $count;
                }

                if (null === $limit) {
                    foreach ($this->record_data_cache as $data) {
                        if (( $data[ $field ] ?? null ) === $value) {
                            $count++;
                        }
                    }

                    return $count;
                }

                foreach ($this->record_data_cache as $data) {
                    if (( $data[ $field ] ?? null ) !== $value) {
                        continue;
                    }

                    $count++;
                    if ($count >= $limit) {
                        return $count;
                    }
                }

                return $count;
            }

            foreach ($this->record_data_cache as $id => $data) {
                if (! $query->matches_data($id, $data)) {
                    continue;
                }

                $count++;
                if (null !== $limit && $count >= $limit) {
                    return $count;
                }
            }

            return $count;
        }

        foreach ($this->stream() as $record) {
            if (! $query->matches($record)) {
                continue;
            }

            $count++;
            if (null !== $limit && $count >= $limit) {
                return $count;
            }
        }

        return $count;
    }

    /**
     * @phpstan-assert-if-true array<string, string> $this->record_path_cache
     * @phpstan-assert-if-true array<string, array<string, mixed>> $this->record_data_cache
     */
    private function can_use_record_data_fast_path(): bool
    {
        if (null === $this->record_path_cache || null === $this->record_data_cache) {
            return false;
        }

        return CacheValidation::TRUST === $this->cache_validation || $this->record_data_cache_matches_filesystem();
    }

    private function record_data_cache_matches_filesystem(): bool
    {
        if (null === $this->record_path_cache || null === $this->record_data_cache) {
            return false;
        }

        $paths = $this->record_paths_from_filesystem();
        if (count($paths) !== count($this->record_path_cache)) {
            return false;
        }

        foreach ($paths as $path) {
            $id = basename($path, '.jsonc');
            if (
                ! isset($this->record_path_cache[ $id ]) ||
                $this->record_path_cache[ $id ] !== $path ||
                ! isset($this->record_data_cache[ $id ])
            ) {
                return false;
            }

            $cached = $this->validated_record_cache[ $id ] ?? null;
            if (null === $cached) {
                return false;
            }

            clearstatcache(true, $path);
            if (! is_file($path)) {
                return false;
            }

            $mtime = (int) filemtime($path);
            $size  = (int) filesize($path);
            if ($mtime !== $cached['mtime'] || $size !== $cached['size']) {
                return false;
            }

            if (CacheValidation::HASH === $this->cache_validation) {
                $hash = (string) hash_file(self::CACHE_HASH_ALGORITHM, $path);
                if ($hash !== $cached['hash']) {
                    return false;
                }

                $this->last_record_content_path = $path;
                $this->last_record_content_hash = $hash;
                $this->last_record_content_mtime = $mtime;
                $this->last_record_content_size = $size;
            }
        }

        return true;
    }

    public function cached_record_count(?int $limit = null): ?int
    {
        if (! $this->can_use_record_data_fast_path()) {
            return null;
        }

        $count = count($this->record_data_cache);

        return null === $limit ? $count : min($count, $limit);
    }

    public function cached_first_record(): StorageRecord|false|null
    {
        if (! $this->can_use_record_data_fast_path()) {
            return false;
        }

        if ($this->record_cache_ordered) {
            foreach ($this->record_data_cache as $id => $data) {
                return new StorageRecord($id, $data);
            }

            return null;
        }

        foreach ($this->cached_record_ids() as $id) {
            $data = $this->record_data_cache[ $id ] ?? null;
            if (null !== $data) {
                return new StorageRecord($id, $data);
            }
        }

        return null;
    }

    /**
     * @return list<StorageRecord>|null
     */
    public function cached_records(?int $limit = null): ?array
    {
        if (! $this->can_use_record_data_fast_path()) {
            return null;
        }

        $records = array();
        if ($this->record_cache_ordered) {
            if (null === $limit) {
                foreach ($this->record_data_cache as $id => $data) {
                    $records[] = new StorageRecord($id, $data);
                }

                return $records;
            }

            $count = 0;
            foreach ($this->record_data_cache as $id => $data) {
                $records[] = new StorageRecord($id, $data);
                $count++;
                if ($count >= $limit) {
                    return $records;
                }
            }

            return $records;
        }

        if (null === $limit) {
            foreach ($this->cached_record_ids() as $id) {
                $data = $this->record_data_cache[ $id ] ?? null;
                if (null === $data) {
                    continue;
                }

                $records[] = new StorageRecord($id, $data);
            }

            return $records;
        }

        $count = 0;
        foreach ($this->cached_record_ids() as $id) {
            $data = $this->record_data_cache[ $id ] ?? null;
            if (null === $data) {
                continue;
            }

            $records[] = new StorageRecord($id, $data);
            $count++;
            if ($count >= $limit) {
                return $records;
            }
        }

        return $records;
    }

    /**
     * @return array{store: string, plan: string, indexes: list<array<string, mixed>>, groups: int}
     */
    public function explain(?QueryBuilder $query = null): array
    {
        return $this->indexes()->explain($query ?? $this->query());
    }

    /**
     * @return array{fields: int, entries: int}
     */
    public function reindex(): array
    {
        return $this->with_write_lock(
            function (): array {
                $result = $this->indexes()->rebuild(true);
                $this->forget_all_record_caches();

                return $result;
            }
        );
    }

    /**
     * @return array{records: int, bytes: int, corrupt: int, indexes: int}
     */
    public function stats(): array
    {
        $records = 0;
        $bytes   = 0;
        $corrupt = 0;

        foreach ($this->record_paths() as $path) {
            $bytes += is_file($path) ? (int) filesize($path) : 0;
            try {
                $this->record_from_file($path, basename($path, '.jsonc'));
                $records++;
            } catch (\Throwable) {
                $corrupt++;
            }
        }

        return array(
            'records' => $records,
            'bytes'   => $bytes,
            'corrupt' => $corrupt,
            'indexes' => count($this->indexes()->definitions()),
        );
    }

    /**
     * @return array{ok: bool, errors: list<string>, stats: array<string, int>}
     */
    public function health(): array
    {
        $stats  = $this->stats();
        $errors = array();
        if ($stats['corrupt'] > 0) {
            $errors[] = 'Corrupt records: ' . $stats['corrupt'];
        }
        if (array() === $errors) {
            foreach ($this->indexes()->verify_against_store() as $error) {
                $errors[] = $error;
            }
        }

        return array(
            'ok'     => array() === $errors,
            'errors' => $errors,
            'stats'  => $stats,
        );
    }

    /**
     * @return array{ok: bool, errors: list<string>, stats: array<string, int>}
     */
    public function verify(): array
    {
        return $this->health();
    }

    /**
     * @return array{ok: bool, quarantined: int, reindexed: array{fields: int, entries: int}}
     */
    public function repair(): array
    {
        return $this->with_write_lock(
            function (): array {
                AtomicFilesystem::cleanup_temp_files($this->collection_root());
                $quarantined = $this->quarantine_corrupt_records();

                return array(
                    'ok'          => true,
                    'quarantined' => $quarantined,
                    'reindexed'   => $this->reindex(),
                );
            }
        );
    }

    private function quarantine_corrupt_records(): int
    {
        $count = 0;
        foreach ($this->record_paths_from_filesystem() as $path) {
            $id = basename($path, '.jsonc');
            try {
                $this->record_from_file($path, $id);
                continue;
            } catch (\Throwable) {
                $this->quarantine_record_file($id, $path);
                $count++;
            }
        }

        if ($count > 0) {
            $this->forget_written_record_cache();
        }

        return $count;
    }

    private function quarantine_record_file(string $id, string $path): void
    {
        $quarantine_root = $this->collection_root() . '/.storh/corrupt';
        AtomicFilesystem::ensure_directory($quarantine_root);

        $target = $quarantine_root . '/' . basename($path);
        if (file_exists($target)) {
            $target = $quarantine_root . '/' . basename($path, '.jsonc') . '-' . bin2hex(random_bytes(4)) . '.jsonc';
        }

        if (! @rename($path, $target)) {
            throw new StorageException('Could not quarantine corrupt storage record: ' . $path);
        }

        AtomicFilesystem::sync_directory(dirname($path));
        AtomicFilesystem::sync_directory($quarantine_root);

        if (null !== $this->record_path_cache) {
            unset($this->record_path_cache[ $id ]);
            $this->record_sorted_ids_cache = null;
        }
        if (null !== $this->record_data_cache) {
            unset($this->record_data_cache[ $id ]);
        }
        unset($this->validated_record_cache[ $id ]);

        if ($this->cache_enabled) {
            $this->cache_missing($id, $path);
        }
    }

    /**
     * @return array{ok: bool, records: int}
     */
    public function compact(): array
    {
        return array(
            'ok'      => true,
            'records' => $this->stats()['records'],
        );
    }

    public function importJsonl(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new StorageException('Could not open JSONL import file: ' . $path);
        }

        try {
            return $this->with_write_lock(fn(): int => $this->import_jsonl_records($handle));
        } finally {
            fclose($handle);
        }
    }

    public function exportJsonl(string $path): int
    {
        AtomicFilesystem::ensure_directory(dirname($path));
        $handle = @fopen($path, 'wb');
        if (false === $handle) {
            throw new StorageException('Could not open JSONL export file: ' . $path);
        }

        $count = 0;
        $buffer = '';
        try {
            if ($this->can_use_record_data_fast_path()) {
                if ($this->record_cache_ordered) {
                    foreach ($this->record_data_cache as $id => $data) {
                        $buffer .= '{"id":"' . $id . '","data":' . json_encode(
                            $data,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
                        ) . "}\n";
                        $count++;

                        if (strlen($buffer) >= self::JSONL_EXPORT_BUFFER_BYTES) {
                            AtomicFilesystem::write_all($handle, $buffer, $path);
                            $buffer = '';
                        }
                    }
                } else {
                    foreach ($this->cached_record_ids() as $id) {
                        $data = $this->record_data_cache[ $id ] ?? null;
                        if (null === $data) {
                            continue;
                        }

                        $buffer .= '{"id":"' . $id . '","data":' . json_encode(
                            $data,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
                        ) . "}\n";
                        $count++;

                        if (strlen($buffer) >= self::JSONL_EXPORT_BUFFER_BYTES) {
                            AtomicFilesystem::write_all($handle, $buffer, $path);
                            $buffer = '';
                        }
                    }
                }
            } else {
                foreach ($this->stream() as $record) {
                    $buffer .= '{"id":"' . $record->id() . '","data":' . json_encode(
                        $record->data(),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
                    ) . "}\n";
                    $count++;

                    if (strlen($buffer) >= self::JSONL_EXPORT_BUFFER_BYTES) {
                        AtomicFilesystem::write_all($handle, $buffer, $path);
                        $buffer = '';
                    }
                }
            }

            if ('' !== $buffer) {
                AtomicFilesystem::write_all($handle, $buffer, $path);
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    public function path_for_id(string $id): string
    {
        UuidV7::assert_valid($id);

        $this->record_path_cache = null;
        $this->record_data_cache = null;
        $this->validated_record_cache = array();
        $this->record_cache_ordered = true;
        $this->record_cache_last_id = null;

        return $this->record_path_for_id($id);
    }

    private function record_path_for_id(string $id): string
    {
        return $this->record_directory_for_id($id) . '/' . $id . '.jsonc';
    }

    private function record_directory_for_id(string $id): string
    {
        return $this->data_root() . '/' . substr($id, 24, 2);
    }

    private function assert_record_id(string $id, bool $generated): void
    {
        if ($generated && $this->trusted_generated_ids) {
            return;
        }

        UuidV7::assert_valid($id);
    }

    public function collection_root(): string
    {
        return $this->collection_path;
    }

    /**
     * @internal
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function with_write_lock(callable $callback): mixed
    {
        if ($this->write_lock_depth > 0) {
            return $callback();
        }

        $handle = $this->write_lock_handle();
        if (! flock($handle, LOCK_EX)) {
            throw new StorageException('Could not lock DocStore collection.');
        }

        $this->write_lock_depth++;
        try {
            return $callback();
        } finally {
            $this->write_lock_depth--;
            flock($handle, LOCK_UN);
        }
    }

    /**
     * @return resource
     */
    private function write_lock_handle(): mixed
    {
        if (is_resource($this->write_lock_handle)) {
            return $this->write_lock_handle;
        }

        $lock_directory = $this->collection_root() . '/.storh';
        AtomicFilesystem::ensure_directory($lock_directory);
        $handle = @fopen($lock_directory . '/write.lock', 'c+b');
        if (false === $handle) {
            throw new StorageException('Could not open DocStore collection lock.');
        }

        $this->write_lock_handle = $handle;

        return $handle;
    }

    private function data_root(): string
    {
        return $this->data_path;
    }

    /**
     * @return list<string>
     */
    public function record_paths(): array
    {
        $root = $this->data_root();
        if (! is_dir($root)) {
            return array();
        }

        if (CacheValidation::TRUST === $this->cache_validation && null !== $this->record_path_cache) {
            return $this->cached_record_paths();
        }

        return $this->record_paths_from_filesystem();
    }

    /**
     * @internal
     * @return \Generator<int, StorageRecord>
     */
    public function records_from_filesystem(): \Generator
    {
        foreach ($this->record_paths_from_filesystem() as $path) {
            yield $this->record_from_file($path, basename($path, '.jsonc'));
        }
    }

    /**
     * @return list<string>
     */
    private function cached_record_paths(): array
    {
        if (null === $this->record_path_cache) {
            return array();
        }

        if ($this->record_cache_ordered) {
            return array_values($this->record_path_cache);
        }

        $paths = array();
        foreach ($this->cached_record_ids() as $id) {
            $paths[] = $this->record_path_cache[ $id ];
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function record_paths_from_filesystem(): array
    {
        $root = $this->data_root();
        if (! is_dir($root)) {
            return array();
        }

        $paths    = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'jsonc' === $file->getExtension()) {
                $paths[ $file->getBasename('.jsonc') ] = $file->getPathname();
            }
        }

        ksort($paths);

        return array_values($paths);
    }

    private function record_from_file(string $path, string $expected_id): StorageRecord
    {
        $decoded = $this->read_record_object($path);
        $id      = isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : '';
        $data    = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : array();

        UuidV7::assert_valid($id);
        if ($id !== $expected_id) {
            throw new StorageException('Storage record id does not match its path.');
        }

        /** @var array<string, mixed> $data */
        return new StorageRecord($id, $data);
    }

    private function cache_key(string $id): string
    {
        return 'doc:' . $this->collection . ':' . $id;
    }

    private function validated_record(string $id, string $path): StorageRecord|false|null
    {
        $cached = $this->validated_record_cache[ $id ] ?? null;
        if (null === $cached) {
            return null;
        }

        clearstatcache(true, $path);
        if (! is_file($path)) {
            unset($this->validated_record_cache[ $id ]);
            return false;
        }

        $mtime = (int) filemtime($path);
        $size  = (int) filesize($path);
        if ($mtime !== $cached['mtime'] || $size !== $cached['size']) {
            unset($this->validated_record_cache[ $id ]);
            return null;
        }

        if (CacheValidation::HASH === $this->cache_validation) {
            $hash = (string) hash_file(self::CACHE_HASH_ALGORITHM, $path);
            if ($hash !== $cached['hash']) {
                unset($this->validated_record_cache[ $id ]);
                return null;
            }

            $this->last_record_content_path = $path;
            $this->last_record_content_hash = $hash;
            $this->last_record_content_mtime = $mtime;
            $this->last_record_content_size = $size;
        }

        return new StorageRecord($id, $cached['data']);
    }

    private function cached_record(string $id, string $path): StorageRecord|false|null
    {
        if (! $this->cache_enabled) {
            return null;
        }

        $cached = $this->cache->get($this->cache_key($id));
        if (
            ! is_array($cached) ||
            ! array_key_exists(0, $cached) ||
            ! array_key_exists(1, $cached) ||
            ! array_key_exists(2, $cached) ||
            ! array_key_exists(3, $cached) ||
            ! array_key_exists(4, $cached) ||
            ! is_bool($cached[0]) ||
            ! is_string($cached[1]) ||
            ! is_int($cached[2]) ||
            ! is_int($cached[3]) ||
            ! is_string($cached[4])
        ) {
            return null;
        }

        $cached_exists = $cached[0];
        $cached_scope  = $cached[1];
        $cached_mtime  = $cached[2];
        $cached_size   = $cached[3];
        $cached_hash   = $cached[4];

        if ($cached_scope !== $this->cache_scope) {
            $this->cache->delete($this->cache_key($id));
            return null;
        }

        if (CacheValidation::TRUST === $this->cache_validation) {
            if (! $cached_exists) {
                return false;
            }

            $data = $this->cached_data($cached[5] ?? null);
            $this->remember_trusted_read_record($id, $data);

            return new StorageRecord($id, $data);
        }

        clearstatcache(true, $path);
        $exists = is_file($path);
        $mtime  = $exists ? (int) filemtime($path) : 0;
        $size   = $exists ? (int) filesize($path) : -1;
        $hash   = '';
        if ($exists && CacheValidation::HASH === $this->cache_validation) {
            $hash = (string) hash_file(self::CACHE_HASH_ALGORITHM, $path);
        }

        if (
            $exists !== $cached_exists ||
            $mtime !== $cached_mtime ||
            $size !== $cached_size ||
            ( CacheValidation::HASH === $this->cache_validation && $exists && $cached_hash !== $hash )
        ) {
            $this->cache->delete($this->cache_key($id));
            return null;
        }

        if (! $cached_exists) {
            return false;
        }

        $data = $this->cached_data($cached[5] ?? null);
        $this->remember_validated_record($id, $data, $mtime, $size, $hash);
        return new StorageRecord($id, $data);
    }

    private function cache_record(StorageRecord $record, ?string $path = null): void
    {
        if (! $this->cache_enabled) {
            return;
        }

        $path ??= $this->record_path_for_id($record->id());
        if (CacheValidation::TRUST === $this->cache_validation) {
            unset($this->validated_record_cache[ $record->id() ]);
            $this->cache->set(
                $this->cache_key($record->id()),
                array(
                    true,
                    $this->cache_scope,
                    0,
                    -1,
                    '',
                    $record->data(),
                )
            );
            return;
        }

        if (
            $this->last_record_content_path === $path &&
            null !== $this->last_record_content_mtime &&
            null !== $this->last_record_content_size
        ) {
            $exists = true;
            $mtime  = $this->last_record_content_mtime;
            $size   = $this->last_record_content_size;
        } else {
            clearstatcache(true, $path);
            $exists = is_file($path);
            $mtime  = $exists ? (int) filemtime($path) : 0;
            $size   = $exists ? (int) filesize($path) : -1;
        }
        $hash   = '';

        if ($exists && CacheValidation::HASH === $this->cache_validation) {
            if ($this->last_record_content_path === $path && null !== $this->last_record_content_hash) {
                $hash = $this->last_record_content_hash;
            } else {
                $hash = (string) hash_file(self::CACHE_HASH_ALGORITHM, $path);
            }
        }

        if ($exists) {
            $this->remember_validated_record($record->id(), $record->data(), $mtime, $size, $hash);
        } else {
            unset($this->validated_record_cache[ $record->id() ]);
        }

        $this->cache->set(
            $this->cache_key($record->id()),
            array(
                true,
                $this->cache_scope,
                $mtime,
                $size,
                $hash,
                $this->cache_data_payload($record->data()),
            )
        );
    }

    private function cache_missing(string $id, string $path): void
    {
        unset($this->validated_record_cache[ $id ]);

        if (! $this->cache_enabled) {
            return;
        }

        clearstatcache(true, $path);
        $this->cache->set(
            $this->cache_key($id),
            array(
                false,
                $this->cache_scope,
                0,
                -1,
                '',
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cached_data(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return array();
            }
        }

        if (! is_array($value)) {
            return array();
        }

        foreach ($value as $key => $_field) {
            if (! is_string($key)) {
                $data = array();
                foreach ($value as $copy_key => $field) {
                    if (is_string($copy_key)) {
                        $data[ $copy_key ] = $field;
                    }
                }

                return $data;
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function cache_data_payload(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function remember_validated_record_from_path(string $id, array $data, string $path): void
    {
        if (
            $this->last_record_content_path === $path &&
            null !== $this->last_record_content_mtime &&
            null !== $this->last_record_content_size
        ) {
            $mtime = $this->last_record_content_mtime;
            $size  = $this->last_record_content_size;
        } else {
            clearstatcache(true, $path);
            if (! is_file($path)) {
                unset($this->validated_record_cache[ $id ]);
                return;
            }

            $mtime = (int) filemtime($path);
            $size  = (int) filesize($path);
        }

        $hash = '';
        if (CacheValidation::HASH === $this->cache_validation) {
            if ($this->last_record_content_path === $path && null !== $this->last_record_content_hash) {
                $hash = $this->last_record_content_hash;
            } else {
                $hash = (string) hash_file(self::CACHE_HASH_ALGORITHM, $path);
            }
        }

        $this->remember_validated_record($id, $data, $mtime, $size, $hash);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function remember_validated_record(string $id, array $data, int $mtime, int $size, string $hash): void
    {
        if (
            ! isset($this->validated_record_cache[ $id ]) &&
            ( count($this->validated_record_cache) >= self::WRITE_CACHE_LIMIT || $this->over_validated_record_cache_budget() )
        ) {
            return;
        }

        $this->validated_record_cache[ $id ] = array(
            'mtime' => $mtime,
            'size'  => $size,
            'hash'  => $hash,
            'data'  => $data,
        );

        if (null !== $this->record_data_cache && isset($this->record_data_cache[ $id ])) {
            $this->record_data_cache[ $id ] = $data;
        }
    }

    private function over_validated_record_cache_budget(): bool
    {
        return null !== $this->validated_record_cache_max_bytes &&
            memory_get_usage() >= $this->validated_record_cache_max_bytes;
    }

    private static function default_validated_record_cache_max_bytes(): ?int
    {
        $limit = ini_get('memory_limit');
        if (false === $limit || '-1' === trim($limit)) {
            return null;
        }

        $bytes = self::parse_bytes($limit);
        // @codeCoverageIgnoreStart
        if (null === $bytes) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return max(1_048_576, (int) floor($bytes * 0.60));
    }

    private static function parse_bytes(string $value): ?int
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        return match ($unit) {
            'g' => (int) floor($number * 1024 * 1024 * 1024),
            'm' => (int) floor($number * 1024 * 1024),
            'k' => (int) floor($number * 1024),
            default => (int) floor($number),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write_record_file(string $directory, string $path, string $id, array $data): void
    {
        $contents = '{"id":"' . $id . '","data":' . json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "}\n";

        $this->write_record_file_contents($directory, $path, $id, $contents);
    }

    private function write_record_file_contents(string $directory, string $path, string $id, string $contents): void
    {
        $this->ensure_known_directory($directory);
        $temp = $directory . '/.' . $this->temp_prefix . '.' . ++$this->temp_counter . '.tmp';
        $handle = @fopen($temp, 'wb');
        if (false === $handle) {
            throw new StorageException('Could not open temporary storage file for writing: ' . $temp);
        }

        $mtime = null;
        $size  = null;
        try {
            AtomicFilesystem::write_all($handle, $contents, $temp);
            AtomicFilesystem::sync_handle($handle, $temp);
            $stat = @fstat($handle);
            if (is_array($stat)) {
                $mtime = isset($stat['mtime']) ? (int) $stat['mtime'] : null;
                $size  = isset($stat['size']) ? (int) $stat['size'] : strlen($contents);
            }
        } finally {
            fclose($handle);
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);
            throw new StorageException('Could not atomically replace storage file: ' . $path);
        }
        AtomicFilesystem::sync_directory($directory);

        unset($this->validated_record_cache[ $id ]);
        $this->last_record_content_path = $path;
        $this->last_record_content_hash = CacheValidation::HASH === $this->cache_validation
            ? hash(self::CACHE_HASH_ALGORITHM, $contents)
            : null;
        $this->last_record_content_mtime = $mtime;
        $this->last_record_content_size = $size;
    }

    /**
     * @return array<string, mixed>
     */
    private function read_record_object(string $path): array
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new StorageException('Could not read storage file: ' . $path);
        }

        $this->last_record_content_path = $path;
        $this->last_record_content_hash = CacheValidation::HASH === $this->cache_validation
            ? hash(self::CACHE_HASH_ALGORITHM, $contents)
            : null;
        $this->last_record_content_mtime = null;
        $this->last_record_content_size = null;

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || ( array() !== $decoded && array_is_list($decoded) )) {
                throw new StorageException('JSONC document must decode to an object.');
            }

            foreach ($decoded as $key => $_value) {
                if (! is_string($key)) {
                    throw new StorageException('JSONC document must decode to an object.');
                }
            }

            return $decoded;
        } catch (\JsonException) {
            return AtomicFilesystem::read_jsonc_object($path);
        }
    }

    private function ensure_known_directory(string $directory): void
    {
        if (isset($this->known_directories[ $directory ])) {
            return;
        }

        AtomicFilesystem::ensure_directory($directory);
        $this->known_directories[ $directory ] = true;
    }

    private function forget_written_record_cache(): void
    {
        $this->record_path_cache = null;
        $this->record_data_cache = null;
        $this->validated_record_cache = array();
        $this->record_cache_ordered = true;
        $this->record_sorted_ids_cache = null;
        $this->record_cache_last_id = null;
    }

    private function forget_all_record_caches(): void
    {
        $this->forget_written_record_cache();
        if ($this->cache_enabled) {
            $this->cache->clear_prefix('doc:' . $this->collection . ':');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function remember_written_record(string $id, string $path, array $data, bool $cache_data = true): void
    {
        if (null !== $this->record_path_cache) {
            if (count($this->record_path_cache) >= self::WRITE_CACHE_LIMIT) {
                $this->record_path_cache = null;
                $this->record_cache_ordered = true;
                $this->record_sorted_ids_cache = null;
                $this->record_cache_last_id = null;
            } else {
                $is_new_record = ! isset($this->record_path_cache[ $id ]);
                if ($is_new_record) {
                    $this->record_sorted_ids_cache = null;
                    $comparison = null === $this->record_cache_last_id ? 1 : strcmp($id, $this->record_cache_last_id);
                    if ($comparison < 0) {
                        $this->record_cache_ordered = false;
                    } elseif ($comparison > 0) {
                        $this->record_cache_last_id = $id;
                    }
                }

                $this->record_path_cache[ $id ] = $path;
            }
        }

        if (! $cache_data) {
            $this->record_data_cache = null;
            return;
        }

        if (null !== $this->record_data_cache) {
            if (count($this->record_data_cache) >= self::WRITE_CACHE_LIMIT) {
                $this->record_data_cache = null;
            } else {
                $this->record_data_cache[ $id ] = $data;
            }
        }

        if (! $this->cache_enabled && CacheValidation::TRUST !== $this->cache_validation) {
            $this->remember_validated_record_from_path($id, $data, $path);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function remember_trusted_read_record(string $id, array $data): void
    {
        if (CacheValidation::TRUST !== $this->cache_validation) {
            return;
        }

        $this->record_data_cache ??= array();
        if (! isset($this->record_data_cache[ $id ]) && count($this->record_data_cache) >= self::WRITE_CACHE_LIMIT) {
            return;
        }

        $this->record_data_cache[ $id ] = $data;
    }

    /**
     * @return list<string>
     */
    private function cached_record_ids(): array
    {
        if (null === $this->record_path_cache) {
            return array();
        }

        if (null !== $this->record_sorted_ids_cache) {
            return $this->record_sorted_ids_cache;
        }

        $ids = array_keys($this->record_path_cache);
        if (! $this->record_cache_ordered) {
            sort($ids);
        }

        if (! $this->record_cache_ordered) {
            $this->record_sorted_ids_cache = $ids;
        }

        return $ids;
    }

    /**
     * @param resource $handle
     */
    private function import_jsonl_records(mixed $handle): int
    {
        $count = 0;
        $indexes = $this->active_indexes();
        $has_indexes = null !== $indexes;
        $this->forget_written_record_cache();

        while (false !== ( $line = fgets($handle) )) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }
            if (ctype_space($line)) {
                continue;
            }

            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new StorageException('JSONL import rows must be objects.');
            }

            if (
                2 === count($decoded) &&
                isset($decoded['id'], $decoded['data']) &&
                is_string($decoded['id']) &&
                is_array($decoded['data'])
            ) {
                $id = $decoded['id'];
                $data = $decoded['data'];
                $this->assert_record_id($id, false);

                /** @var array<string, mixed> $data */
                $this->schema?->validate($data);

                $old = ! $has_indexes ? null : $this->get($id);
                if ($has_indexes) {
                    $indexes->validate_unique($id, $data, $old?->data());
                }

                $directory = $this->record_directory_for_id($id);
                $record_path = $directory . '/' . $id . '.jsonc';
                $this->write_record_file_contents($directory, $record_path, $id, $line . "\n");

                if ($has_indexes) {
                    $indexes->update_record($id, $data, $old?->data());
                }

                if ($this->cache_enabled) {
                    $this->cache_record(new StorageRecord($id, $data), $record_path);
                }

                $count++;
                continue;
            }

            $record = array();
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $record[ $key ] = $value;
                }
            }

            $id   = isset($record['id']) && is_string($record['id']) ? $record['id'] : null;
            $data = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
            $generated = null === $id;
            $id ??= ( $this->id_generator )();
            $this->assert_record_id($id, $generated);

            /** @var array<string, mixed> $data */
            $this->schema?->validate($data);

            $old = $generated || ! $has_indexes ? null : $this->get($id);
            if ($has_indexes) {
                $indexes->validate_unique($id, $data, $old?->data());
            }

            $directory = $this->record_directory_for_id($id);
            $record_path = $directory . '/' . $id . '.jsonc';
            $this->write_record_file($directory, $record_path, $id, $data);

            if ($has_indexes) {
                $indexes->update_record($id, $data, $old?->data());
            }

            if ($this->cache_enabled) {
                $this->cache_record(new StorageRecord($id, $data), $record_path);
            }

            $count++;
        }

        return $count;
    }
}
