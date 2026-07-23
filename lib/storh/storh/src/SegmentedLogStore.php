<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class SegmentedLogStore implements FileStoreInterface
{
    private const CACHE_HASH_ALGORITHM = 'xxh128';

    private const CANONICAL_PUT_PREFIX = '{"op":"put","id":"';

    private const CANONICAL_DELETE_PREFIX = '{"op":"delete","id":"';

    private const LINE_HASH_ALGORITHM = 'xxh32';

    private const EQUALITY_COUNT_MAX_VALUES_PER_FIELD = 64;

    /** @var callable(): string */
    private mixed $id_generator;

    private bool $trusted_generated_ids;

    private readonly string $collection_path;

    private readonly string $collection_root_path;

    private readonly string $segments_root_path;

    private readonly string $manifest_file_path;

    private CacheInterface $cache;

    private bool $cache_enabled;

    /** @var null|array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}> */
    private ?array $state = null;

    private int $live_record_count = 0;

    private int $deleted_record_count = 0;

    /** @var array<string, array<string, int>> */
    private array $equality_counts = array();

    /** @var array<string, true> */
    private array $disabled_equality_count_fields = array();

    private bool $equality_counts_valid = true;

    private bool $equality_counts_rebuild_attempted = false;

    /** @var array<string, array{min: string|null, max: string|null, records: int, ordered: bool}> */
    private array $segment_stats = array();

    /** @var array<string, list<array{0: string, 1: int}>> */
    private array $segment_sparse_offsets = array();

    /** @var resource|null */
    private mixed $lock_handle = null;

    /** @var resource|null */
    private mixed $active_handle = null;

    private bool $active_handle_dirty = false;

    private ?string $active_handle_file = null;

    private ?string $active_handle_path = null;

    /** @var array<string, mixed>|null */
    private ?array $manifest_state = null;

    private ?int $manifest_mtime = null;

    private ?int $manifest_size = null;

    public function __construct(
        private readonly string $root,
        private readonly string $collection,
        private readonly int $max_segment_bytes = 1048576,
        private readonly int $sparse_index_interval = 64,
        ?callable $id_generator = null,
        ?CacheInterface $cache = null,
        ?string $partition = null,
        ?int $partition_timestamp_ms = null,
        private readonly string $cache_validation = CacheValidation::STAT
    ) {
        CacheValidation::assert_valid($this->cache_validation);

        if ($this->max_segment_bytes < 256) {
            throw new StorageException('Segment size must be at least 256 bytes.');
        }

        if ($this->sparse_index_interval < 1) {
            throw new StorageException('Sparse index interval must be at least 1.');
        }

        $this->trusted_generated_ids = null === $id_generator;
        $this->id_generator          = $id_generator ?? static fn(): string => UuidV7::generate();
        $this->cache                 = $cache ?? Cache::null();
        $this->cache_enabled         = ! $this->cache instanceof NullCache;
        $this->collection_path       = $this->partitioned_collection($collection, $partition, $partition_timestamp_ms);
        $this->collection_root_path  = rtrim($this->root, '/\\') . '/' . $this->collection_path;
        $this->segments_root_path    = $this->collection_root_path . '/segments';
        $this->manifest_file_path    = $this->collection_root_path . '/manifest.jsonc';
        $this->initialize();
    }

    public function __destruct()
    {
        $this->close_active_handle();
        if (is_resource($this->lock_handle)) {
            fclose($this->lock_handle);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(array $data, ?string $id = null): StorageRecord
    {
        $generated = null === $id;
        $id ??= ( $this->id_generator )();
        $this->assert_record_id($id, $generated);

        $this->with_lock(
            function () use ($id, $data): void {
                $this->append_envelope(
                    array(
                        'op'   => 'put',
                        'id'   => $id,
                        'data' => $data,
                    )
                );
            }
        );

        return new StorageRecord($id, $data);
    }

    /**
     * @param iterable<array<string, mixed>> $records
     * @return list<StorageRecord>
     */
    public function appendMany(iterable $records): array
    {
        $stored = array();

        foreach ($records as $record) {
            $id   = isset($record['id']) && is_string($record['id']) ? $record['id'] : null;
            $data = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
            $generated = null === $id;
            $id ??= ( $this->id_generator )();
            $this->assert_record_id($id, $generated);

            /** @var array<string, mixed> $data */
            $stored[] = new StorageRecord($id, $data);
        }

        if (array() !== $stored) {
            $this->with_lock(
                function () use ($stored): void {
                    $this->append_storage_records($stored);
                }
            );
        }

        return $stored;
    }

    /**
     * @param iterable<array<string, mixed>> $records
     */
    public function appendStream(iterable $records): int
    {
        $count = 0;

        $this->with_lock(
            function () use ($records, &$count): void {
                $this->append_stream_records($records, $count);
            }
        );

        return $count;
    }

    public function get(string $id): ?StorageRecord
    {
        UuidV7::assert_valid($id);
        $entry = $this->state_entry($id);

        if (null === $entry || $entry['deleted']) {
            return null;
        }

        $last_exception = null;
        foreach ($this->state_locations($entry) as $location) {
            try {
                $envelope = $this->read_envelope_at($location['file'], $location['offset']);
            } catch (StorageException $exception) {
                $last_exception = $exception;
                continue;
            }

            if ($id === $envelope['id'] && 'put' === $envelope['op']) {
                return $this->record_from_envelope($envelope);
            }

            $last_exception = new StorageException('Segmented log state index points at the wrong record.');
        }

        throw $last_exception ?? new StorageException('Segmented log state index points at the wrong record.');
    }

    public function delete(string $id): void
    {
        UuidV7::assert_valid($id);

        $this->with_lock(
            function () use ($id): void {
                $this->append_envelope(
                    array(
                        'op' => 'delete',
                        'id' => $id,
                    )
                );
            }
        );
    }

    /**
     * @return \Generator<int, StorageRecord>
     */
    public function stream(?RecordQuery $query = null): \Generator
    {
        $this->flush_active_handle();

        $query ??= RecordQuery::all();
        $count  = 0;
        $filters_records = $query->filters_records();
        $filters_id_only = $filters_records && ! $query->filters_data();
        $limit = $query->limit_value();
        $state = $this->state_index();

        foreach ($this->query_segments($query) as $segment) {
            $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
            if ('' === $file) {
                continue;
            }

            $offset = $this->seek_offset_for($segment, $this->seek_id_for_query($query));
            $query->notify_segment_open($file);
            $ordered_segment = $this->segment_is_ordered($segment);
            $upper_id = $query->upper_id();

            $handle = @fopen($this->segment_path($file), 'rb');
            if (false === $handle) {
                throw new StorageException('Could not open segment: ' . $file);
            }

            try {
                if ($offset > 0) {
                    fseek($handle, $offset);
                }

                $position = $offset;
                while (true) {
                    $line_offset = $position;
                    $line        = fgets($handle);
                    if (false === $line) {
                        break;
                    }

                    $position += strlen($line);
                    try {
                        $envelope = $this->decode_line($line);
                    } catch (\Throwable $throwable) {
                        if ($query->handle_error($file . ':' . $line_offset, $throwable)) {
                            continue;
                        }

                        throw $throwable;
                    }

                    $id    = $envelope['id'];
                    if ($ordered_segment && null !== $upper_id && strcmp($id, $upper_id) > 0) {
                        break;
                    }

                    $entry = $state[ $id ] ?? null;
                    if (null === $entry || $entry['deleted']) {
                        continue;
                    }
                    if (
                        ( $file !== $entry['file'] || $line_offset !== $entry['offset'] ) &&
                        ! $this->state_entry_matches($entry, $file, $line_offset)
                    ) {
                        continue;
                    }

                    $data = null;
                    if ($filters_id_only) {
                        UuidV7::assert_valid($id);
                        if (! $query->matches_id($id)) {
                            continue;
                        }
                    } elseif ($filters_records) {
                        UuidV7::assert_valid($id);
                        $data = $this->data_from_envelope($envelope);
                        if (! $query->matches_data($id, $data)) {
                            continue;
                        }
                    }

                    yield null === $data ? $this->record_from_envelope($envelope) : new StorageRecord($id, $data);
                    $count++;

                    if (null !== $limit && $count >= $limit) {
                        return;
                    }
                }
            } finally {
                fclose($handle);
            }
        }
    }

    public function compact(): void
    {
        $this->with_lock(
            function (): void {
                $this->recover_unlocked(false);
                $this->roll_active_segment();

                $source_segments = $this->sealed_segments();
                if (array() === $source_segments) {
                    $manifest           = $this->manifest();
                    $manifest['sealed'] = array();
                    $this->write_manifest($manifest);
                    return;
                }

                $compacted_segments = $this->write_compacted_segments($source_segments);
                $manifest           = $this->manifest();
                $manifest['sealed'] = $compacted_segments;

                $this->write_manifest($manifest);
            }
        );
    }

    public function recover(): void
    {
        $this->with_lock(
            function (): void {
                $this->recover_unlocked(false);
            }
        );
    }

    /**
     * @return array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}>
     */
    public function state_index(): array
    {
        if (null === $this->state) {
            $this->recover();
        }

        return $this->state ?? array();
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function count_records(QueryBuilder $query): int
    {
        $this->flush_active_handle();

        $count  = 0;
        $limit  = $query->limit_value();
        $cursor = $query->cursor_id();
        $groups = $query->groups();
        if ($this->query_has_no_conditions($groups)) {
            return $this->count_live_state_records($cursor, $limit);
        }

        $single_condition = $this->query_single_condition($groups);
        $indexed_equal_count = $this->count_equal_live_records($single_condition, $cursor, $limit);
        if (null !== $indexed_equal_count) {
            return $indexed_equal_count;
        }

        $match_marker = null === $single_condition ? null : $this->line_match_marker($single_condition);
        $state = $this->state_index();
        $segment_query = null === $cursor
            ? RecordQuery::all()
            : RecordQuery::from_query_builder($cursor, null, array());

        foreach ($this->query_segments($segment_query) as $segment) {
            $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
            if ('' === $file) {
                continue;
            }

            $handle = @fopen($this->segment_path($file), 'rb');
            if (false === $handle) {
                throw new StorageException('Could not open segment: ' . $file);
            }

            try {
                $offset = $this->seek_offset_for($segment, $cursor);
                if ($offset > 0) {
                    fseek($handle, $offset);
                }

                $position = $offset;
                while (true) {
                    $line_offset = $position;
                    $line        = fgets($handle);
                    if (false === $line) {
                        break;
                    }

                    $position += strlen($line);
                    if (null !== $match_marker) {
                        $json = $this->validated_line_json($line);
                        if (! str_contains($json, $match_marker)) {
                            continue;
                        }

                        $envelope = $this->decode_json_envelope($json);
                    } else {
                        $envelope = $this->decode_line($line);
                    }
                    $id       = $envelope['id'];
                    $entry    = $state[ $id ] ?? null;
                    if (null === $entry || $entry['deleted']) {
                        continue;
                    }
                    if (
                        ( $file !== $entry['file'] || $line_offset !== $entry['offset'] ) &&
                        ! $this->state_entry_matches($entry, $file, $line_offset)
                    ) {
                        continue;
                    }

                    UuidV7::assert_valid($id);
                    if (null !== $cursor && strcmp($id, $cursor) <= 0) {
                        continue;
                    }

                    $data = isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : array();
                    /** @var array<string, mixed> $data */
                    if (
                        null !== $single_condition
                            ? ! $single_condition->matches_data($id, $data)
                            : ! $query->matches_data($id, $data)
                    ) {
                        continue;
                    }

                    $count++;
                    if (null !== $limit && $count >= $limit) {
                        return $count;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return $count;
    }

    public function retain(): SegmentedLogRetention
    {
        return new SegmentedLogRetention($this);
    }

    /**
     * @return array{segments: int, records: int, deleted: int, bytes: int}
     */
    public function stats(): array
    {
        $this->flush_active_handle();

        $segments = $this->all_segments();
        $bytes    = 0;
        foreach ($segments as $segment) {
            $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
            $path = '' === $file ? '' : $this->segment_path($file);
            $bytes += is_file($path) ? (int) filesize($path) : 0;
        }

        return array(
            'segments' => count($segments),
            'records'  => $this->count_live_state_records(null, null),
            'deleted'  => $this->deleted_state_records(),
            'bytes'    => $bytes,
        );
    }

    /**
     * @return array{ok: bool, errors: list<string>, stats: array<string, int>}
     */
    public function health(): array
    {
        return $this->verify();
    }

    /**
     * @return array{ok: bool, errors: list<string>, stats: array<string, int>}
     */
    public function verify(): array
    {
        $errors = array();
        try {
            $fresh_state = $this->build_state_index(false, true, false);
            if (! $this->state_indexes_equivalent($this->state_index(), $fresh_state)) {
                $errors[] = 'Segmented log state index drift.';
            }
        } catch (\Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }

        try {
            foreach ($this->stream() as $_record) {
                // Drain the stream so decode errors surface without holding every record in memory.
            }
        } catch (\Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }

        try {
            $stats = $this->stats();
        } catch (\Throwable $throwable) {
            $errors[] = $throwable->getMessage();
            $stats = array(
                'segments' => count($this->all_segments()),
                'records'  => 0,
                'deleted'  => 0,
                'bytes'    => 0,
            );
        }

        return array(
            'ok'     => array() === $errors,
            'errors' => $errors,
            'stats'  => $stats,
        );
    }

    /**
     * @return array{ok: bool, stats: array<string, int>}
     */
    public function repair(): array
    {
        $this->recover();

        return array(
            'ok'    => true,
            'stats' => $this->stats(),
        );
    }

    private function initialize(): void
    {
        AtomicFilesystem::ensure_directory($this->collection_root());
        $this->with_lock(
            function (): void {
                AtomicFilesystem::ensure_directory($this->segments_root());
                AtomicFilesystem::cleanup_temp_files($this->collection_root());
                $this->delete_compaction_leftovers();

                if (! is_file($this->manifest_path())) {
                    $active_file = $this->segment_file_name(1);
                    @touch($this->segment_path($active_file));
                    $this->write_manifest(
                        array(
                            'nextSegment' => 2,
                            'sealed'      => array(),
                            'active'      => array(
                                'file'    => $active_file,
                                'max'     => null,
                                'min'     => null,
                                'records' => 0,
                                'ordered' => true,
                            ),
                        )
                    );
                }

                $manifest = $this->manifest();
                $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
                $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
                if (! is_file($this->segment_path($file))) {
                    @touch($this->segment_path($file));
                }

                $this->recover_unlocked(false);
            }
        );
    }

    private function recover_unlocked(bool $build_equality_counts = true): void
    {
        if (! $build_equality_counts) {
            $this->invalidate_equality_counts();
        }
        $this->replace_state_index($this->build_state_index(true, $build_equality_counts));
        if (! $build_equality_counts && array() === $this->state) {
            $this->equality_counts_valid = true;
            $this->equality_counts_rebuild_attempted = false;
        }
        $this->repair_manifest_stats_from_segments();
    }

    /**
     * @param array{id: string, op: string, data?: array<string, mixed>} $envelope
     */
    private function append_envelope(array $envelope): void
    {
        $manifest = $this->manifest();
        $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
        $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
        $path     = $this->segment_path($file);
        $handle   = $this->active_segment_handle($file, $path);

        fseek($handle, 0, SEEK_END);
        $offset = ftell($handle);
        $offset = false === $offset ? 0 : $offset;
        $line = $this->encode_line($envelope);
        AtomicFilesystem::write_all($handle, $line, $path);
        AtomicFilesystem::sync_handle($handle, $path);
        $this->active_handle_dirty = false;
        $end_offset = $offset + strlen($line);

        $id = $envelope['id'];
        $this->remember_segment_record($file, $id, $offset);
        $replaces_existing = null !== $this->state && isset($this->state[ $id ]);
        if ('delete' === $envelope['op'] || $replaces_existing) {
            $this->invalidate_equality_counts();
        }

        $this->write_state_entry_without_aliases($id, 'delete' === $envelope['op'], $file, $offset);
        if ('put' === $envelope['op'] && ! $replaces_existing) {
            $this->remember_record_equality_counts($envelope['data'] ?? array());
        }

        if ($end_offset >= $this->max_segment_bytes) {
            $this->roll_active_segment();
        }
    }

    /**
     * @param list<StorageRecord> $records
     */
    private function append_storage_records(array $records): void
    {
        $this->close_active_handle();
        $manifest = $this->manifest();
        $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
        $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
        $path     = $this->segment_path($file);
        $handle   = @fopen($path, 'c+b');

        if (false === $handle) {
            throw new StorageException('Could not open active segment.');
        }

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);
        $position = false === $position ? 0 : $position;
        $buffer = '';
        $pending = array();

        try {
            foreach ($records as $record) {
                $id     = $record->id();
                $data   = $record->data();
                $line   = $this->encode_put_line($id, $data);
                $length = strlen($line);

                $pending[] = array( $id, $file, $position, $data );
                $buffer .= $line;
                $position += $length;

                if (strlen($buffer) < 1_048_576 && $position < $this->max_segment_bytes) {
                    continue;
                }

                $this->flush_put_record_buffer($handle, $buffer, $path, $pending);

                if ($position >= $this->max_segment_bytes) {
                    AtomicFilesystem::sync_handle($handle, $path);
                    fclose($handle);

                    $this->roll_active_segment();

                    $manifest = $this->manifest();
                    $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
                    $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
                    $path     = $this->segment_path($file);
                    $handle   = @fopen($path, 'c+b');

                    if (false === $handle) {
                        throw new StorageException('Could not open active segment.');
                    }

                    fseek($handle, 0, SEEK_END);
                    $position = ftell($handle);
                    $position = false === $position ? 0 : $position;
                }
            }

            $this->flush_put_record_buffer($handle, $buffer, $path, $pending);
        } finally {
            if (is_resource($handle)) {
                AtomicFilesystem::sync_handle($handle, $path);
                fclose($handle);
            }
        }
    }

    /**
     * @param iterable<array<string, mixed>> $records
     */
    private function append_stream_records(iterable $records, int &$count): void
    {
        $this->close_active_handle();
        $manifest = $this->manifest();
        $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
        $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
        $path     = $this->segment_path($file);
        $handle   = @fopen($path, 'c+b');

        if (false === $handle) {
            throw new StorageException('Could not open active segment.');
        }

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);
        $position = false === $position ? 0 : $position;
        $buffer = '';
        $pending = array();

        try {
            foreach ($records as $record) {
                $id   = isset($record['id']) && is_string($record['id']) ? $record['id'] : null;
                $data = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
                $generated = null === $id;
                $id ??= ( $this->id_generator )();
                $this->assert_record_id($id, $generated);

                /** @var array<string, mixed> $data */
                $line   = $this->encode_put_line($id, $data);
                $length = strlen($line);

                $count++;
                $pending[] = array( $id, $file, $position, $data );
                $buffer .= $line;
                $position += $length;

                if (strlen($buffer) < 1_048_576 && $position < $this->max_segment_bytes) {
                    continue;
                }

                $this->flush_put_record_buffer($handle, $buffer, $path, $pending);

                if ($position >= $this->max_segment_bytes) {
                    AtomicFilesystem::sync_handle($handle, $path);
                    fclose($handle);

                    $this->roll_active_segment();

                    $manifest = $this->manifest();
                    $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
                    $file     = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
                    $path     = $this->segment_path($file);
                    $handle   = @fopen($path, 'c+b');

                    if (false === $handle) {
                        throw new StorageException('Could not open active segment.');
                    }

                    fseek($handle, 0, SEEK_END);
                    $position = ftell($handle);
                    $position = false === $position ? 0 : $position;
                }
            }

            $this->flush_put_record_buffer($handle, $buffer, $path, $pending);
        } finally {
            if (is_resource($handle)) {
                AtomicFilesystem::sync_handle($handle, $path);
                fclose($handle);
            }
        }
    }

    /**
     * @param resource $handle
     * @param list<array{0: string, 1: string, 2: int, 3: array<string, mixed>}> $pending
     */
    private function flush_put_record_buffer(mixed $handle, string &$buffer, string $path, array &$pending): void
    {
        if ('' === $buffer) {
            return;
        }

        AtomicFilesystem::write_all($handle, $buffer, $path);
        AtomicFilesystem::sync_handle($handle, $path);

        foreach ($pending as $entry) {
            $id = $entry[0];
            $this->remember_segment_record($entry[1], $id, $entry[2]);
            $replaces_existing = null !== $this->state && isset($this->state[ $id ]);
            if ($replaces_existing) {
                $this->invalidate_equality_counts();
            } else {
                $this->remember_record_equality_counts($entry[3]);
            }

            $this->write_state_entry_without_aliases($id, false, $entry[1], $entry[2]);
        }

        $buffer = '';
        $pending = array();
    }

    private function roll_active_segment(): void
    {
        $manifest = $this->manifest();
        $active   = isset($manifest['active']) && is_array($manifest['active']) ? $manifest['active'] : array();
        $file = isset($active['file']) && is_string($active['file']) ? $active['file'] : $this->segment_file_name(1);
        $stats = $this->segment_stats[ $file ] ?? null;
        $active_records = is_array($stats) ? $stats['records'] : ( isset($active['records']) && is_int($active['records']) ? $active['records'] : 0 );
        if (0 === $active_records) {
            return;
        }

        $path = $this->segment_path($file);
        if (! is_file($path)) {
            throw new StorageException('Could not roll missing active segment.');
        }

        $this->flush_active_handle();
        $this->close_active_handle();

        $index = $this->index_file_name_for_segment($file);
        $offsets = $this->segment_sparse_offsets[ $file ] ?? null;
        if (null === $offsets) {
            throw new StorageException('Sparse offsets missing for active segment.');
        }

        $this->write_sparse_entries_to($this->segment_path($index), $offsets);
        unset($this->segment_sparse_offsets[ $file ]);

        $sealed     = isset($manifest['sealed']) && is_array($manifest['sealed']) ? $manifest['sealed'] : array();
        $sealed[]   = array(
            'file'    => $file,
            'index'   => $index,
            'max'     => is_array($stats) ? $stats['max'] : ( $active['max'] ?? null ),
            'min'     => is_array($stats) ? $stats['min'] : ( $active['min'] ?? null ),
            'records' => $active_records,
            'ordered' => is_array($stats) ? $stats['ordered'] : true,
        );
        $next_segment = isset($manifest['nextSegment']) && is_int($manifest['nextSegment'])
            ? $manifest['nextSegment']
            : 1;
        $next       = max($next_segment, $this->next_segment_number_after($file));
        $active_new = $this->next_available_segment_file($next);

        $manifest['sealed']      = $sealed;
        $manifest['nextSegment'] = $this->next_segment_number_after($active_new);
        $manifest['active']      = array(
            'file'    => $active_new,
            'max'     => null,
            'min'     => null,
            'records' => 0,
            'ordered' => true,
        );

        @touch($this->segment_path($active_new));
        $this->segment_stats[ $active_new ] = array(
            'min'     => null,
            'max'     => null,
            'records' => 0,
            'ordered' => true,
        );
        $this->segment_sparse_offsets[ $active_new ] = array();
        $this->write_manifest($manifest);
    }

    /**
     * @param list<array<string, mixed>> $source_segments
     * @return list<array<string, mixed>>
     */
    private function write_compacted_segments(array $source_segments): array
    {
        $token           = bin2hex(random_bytes(4));
        $output_segments = array();
        $output_number   = 1;
        $output_file     = '';
        $output_handle   = null;
        $output_path     = '';
        $output_offsets  = array();
        $output_records  = 0;
        $output_min      = null;
        $output_max      = null;
        $output_ordered  = true;
        $output_last_id  = null;
        $output_buffer   = '';
        $output_position = 0;
        $pending_state_entries = array();
        $this->state_index();

        try {
            foreach ($source_segments as $segment) {
                $input_file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
                if ('' === $input_file) {
                    continue;
                }

                $input_handle = @fopen($this->segment_path($input_file), 'rb');
                if (false === $input_handle) {
                    throw new StorageException('Could not open segment: ' . $input_file);
                }

                try {
                    $input_position = 0;
                    while (true) {
                        $input_offset = $input_position;
                        $line         = fgets($input_handle);
                        if (false === $line) {
                            break;
                        }

                        $input_position += strlen($line);
                        $compaction_entry = $this->compaction_entry_from_line($line);
                        $id    = $compaction_entry['id'];
                        $entry = $this->state[ $id ] ?? null;

                        if ('delete' === $compaction_entry['op']) {
                            if (null !== $entry && $entry['deleted'] && $this->state_entry_matches($entry, $input_file, $input_offset)) {
                                $this->delete_state_entry($id);
                            }

                            continue;
                        }

                        if ('put' !== $compaction_entry['op']) {
                            continue;
                        }

                        if (null === $entry || $entry['deleted'] || ! $this->state_entry_matches($entry, $input_file, $input_offset)) {
                            continue;
                        }

                        UuidV7::assert_valid($id);
                        if (null === $output_handle) {
                            $opened        = $this->open_compaction_segment($token, $output_number);
                            $output_file   = $opened['file'];
                            $output_path   = $opened['path'];
                            $output_handle = $opened['handle'];
                            $output_number = $opened['nextNumber'];
                            $output_buffer   = '';
                            $output_position = 0;
                            $output_ordered  = true;
                            $output_last_id  = null;
                            $pending_state_entries = array();
                        }

                        $output_offset = $output_position;

                        if ($compaction_entry['copy']) {
                            $output_line = $line;
                        } else {
                            // @codeCoverageIgnoreStart
                            if (! isset($compaction_entry['envelope'])) {
                                throw new StorageException('Segmented log compaction entry is missing its envelope.');
                            }
                            // @codeCoverageIgnoreEnd

                            $output_line = $this->compaction_line($line, $compaction_entry['envelope']);
                        }
                        $output_buffer .= $output_line;
                        $output_position += strlen($output_line);

                        if (0 === $output_records % $this->sparse_index_interval) {
                            $output_offsets[] = array( $id, $output_offset );
                        }

                        $output_records++;
                        if (null !== $output_last_id && strcmp($id, $output_last_id) < 0) {
                            $output_ordered = false;
                        }
                        $output_last_id = $id;
                        $output_min = null === $output_min || strcmp($id, $output_min) < 0 ? $id : $output_min;
                        $output_max = null === $output_max || strcmp($id, $output_max) > 0 ? $id : $output_max;

                        $pending_state_entries[] = array(
                            $id,
                            $output_file,
                            $output_offset,
                            $entry
                        );

                        if ($output_position >= $this->max_segment_bytes) {
                            $this->flush_compaction_buffer($output_handle, $output_buffer, $output_path, $pending_state_entries);
                            $output_segments[] = $this->finish_compaction_segment(
                                $output_handle,
                                $output_file,
                                $output_path,
                                $output_offsets,
                                $output_records,
                                $output_min,
                                $output_max,
                                $output_ordered
                            );
                            $output_handle  = null;
                            $output_file    = '';
                            $output_path    = '';
                            $output_offsets = array();
                            $output_records = 0;
                            $output_min     = null;
                            $output_max     = null;
                            $output_ordered = true;
                            $output_last_id = null;
                            $output_buffer   = '';
                            $output_position = 0;
                            $pending_state_entries = array();
                        }
                    }
                } finally {
                    fclose($input_handle);
                }
            }
        } finally {
            if (null !== $output_handle) {
                $this->flush_compaction_buffer($output_handle, $output_buffer, $output_path, $pending_state_entries);
                $output_segments[] = $this->finish_compaction_segment(
                    $output_handle,
                    $output_file,
                    $output_path,
                    $output_offsets,
                    $output_records,
                    $output_min,
                    $output_max,
                    $output_ordered
                );
            }
        }

        return $output_segments;
    }

    /**
     * @param resource $handle
     * @param list<array{0: string, 1: string, 2: int, 3: array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}}> $pending_state_entries
     */
    private function flush_compaction_buffer(mixed $handle, string &$buffer, string $path, array &$pending_state_entries): void
    {
        if ('' === $buffer) {
            return;
        }

        AtomicFilesystem::write_all($handle, $buffer, $path);
        AtomicFilesystem::sync_handle($handle, $path);
        foreach ($pending_state_entries as $entry) {
            $this->write_compacted_state_entry($entry[0], $entry[1], $entry[2], $entry[3]);
        }

        $buffer = '';
        $pending_state_entries = array();
    }

    /**
     * @return array{file: string, path: string, handle: resource, nextNumber: int}
     */
    private function open_compaction_segment(string $token, int $number): array
    {
        do {
            $file = sprintf('compact-%s-%06d.ndjson', $token, $number);
            $number++;
            $path = $this->segment_path($file);
        } while (is_file($path));

        $handle = @fopen($path, 'c+b');
        if (false === $handle) {
            throw new StorageException('Could not open compaction segment.');
        }

        return array(
            'file'       => $file,
            'path'       => $path,
            'handle'     => $handle,
            'nextNumber' => $number,
        );
    }

    /**
     * @param resource $handle
     * @param list<array{0: string, 1: int}> $offsets
     * @return array<string, mixed>
     */
    private function finish_compaction_segment(
        mixed $handle,
        string $file,
        string $path,
        array $offsets,
        int $records,
        ?string $min,
        ?string $max,
        bool $ordered = true
    ): array {
        fclose($handle);

        if (0 === $records) {
            @unlink($path);
            $this->segment_stats[ $file ] = array(
                'min'     => $min,
                'max'     => $max,
                'records' => 0,
                'ordered' => true,
            );

            return array(
                'file'      => $file,
                'index'     => $this->index_file_name_for_segment($file),
                'max'       => $max,
                'min'       => $min,
                'records'   => 0,
                'ordered'   => true,
                'compacted' => true,
            );
        }

        $index_file = $this->index_file_name_for_segment($file);
        $this->write_sparse_entries_to($this->segment_path($index_file), $offsets);
        $this->segment_stats[ $file ] = array(
            'min'     => $min,
            'max'     => $max,
            'records' => $records,
            'ordered' => $ordered,
        );

        return array(
            'file'      => $file,
            'index'     => $index_file,
            'max'       => $max,
            'min'       => $min,
            'records'   => $records,
            'ordered'   => $ordered,
            'compacted' => true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $path = $this->manifest_path();
        clearstatcache(true, $path);
        $exists = is_file($path);
        $mtime = $exists ? (int) filemtime($path) : 0;
        $size  = $exists ? (int) filesize($path) : -1;

        if (
            null !== $this->manifest_state &&
            $this->manifest_mtime === $mtime &&
            $this->manifest_size === $size
        ) {
            return $this->manifest_state;
        }

        $this->manifest_state = AtomicFilesystem::read_jsonc_object($path);
        $this->manifest_mtime = $mtime;
        $this->manifest_size  = $size;

        return $this->manifest_state;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function write_manifest(array $manifest): void
    {
        $path = $this->manifest_path();
        AtomicFilesystem::write_atomic($path, Jsonc::encode_compact_object($manifest));
        clearstatcache(true, $path);
        $exists = is_file($path);
        $this->manifest_state = $manifest;
        $this->manifest_mtime = $exists ? (int) filemtime($path) : 0;
        $this->manifest_size  = $exists ? (int) filesize($path) : -1;
        if ($this->cache_enabled) {
            $this->cache->delete($this->manifest_cache_key());
        }
    }

    private function repair_manifest_stats_from_segments(): void
    {
        $manifest = $this->manifest();

        if (isset($manifest['sealed']) && is_array($manifest['sealed'])) {
            $sealed = array();
            foreach ($manifest['sealed'] as $index => $segment) {
                if (! is_array($segment) || ! isset($segment['file']) || ! is_string($segment['file'])) {
                    $sealed[ $index ] = $segment;
                    continue;
                }

                $stats = $this->segment_stats_for($segment['file']);
                $segment['min']     = $stats['min'];
                $segment['max']     = $stats['max'];
                $segment['records'] = $stats['records'];
                $segment['ordered'] = $stats['ordered'];
                $sealed[ $index ] = $segment;
            }
            $manifest['sealed'] = $sealed;
        }

        if (isset($manifest['active']) && is_array($manifest['active'])) {
            $active_file = isset($manifest['active']['file']) && is_string($manifest['active']['file'])
                ? $manifest['active']['file']
                : '';
            if ('' !== $active_file) {
                $stats = $this->segment_stats_for($active_file);
                $manifest['active']['min']     = $stats['min'];
                $manifest['active']['max']     = $stats['max'];
                $manifest['active']['records'] = $stats['records'];
                $manifest['active']['ordered'] = $stats['ordered'];
            }
        }

        $this->write_manifest($manifest);
    }

    /**
     * @return array{min: string|null, max: string|null, records: int, ordered: bool}
     */
    private function segment_stats_for(string $file): array
    {
        return $this->segment_stats[ $file ] ?? array( 'min' => null, 'max' => null, 'records' => 0, 'ordered' => true );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function all_segments(): array
    {
        $manifest = $this->manifest();
        $segments = $this->sealed_segments_from_manifest($manifest);

        if (isset($manifest['active']) && is_array($manifest['active'])) {
            $segments[] = $this->normalize_segment_manifest_entry($manifest['active']);
        }

        return $segments;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sealed_segments(): array
    {
        return $this->sealed_segments_from_manifest($this->manifest());
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array<string, mixed>>
     */
    private function sealed_segments_from_manifest(array $manifest): array
    {
        $segments = array();
        $sealed = isset($manifest['sealed']) && is_array($manifest['sealed']) ? $manifest['sealed'] : array();

        foreach ($sealed as $segment) {
            if (is_array($segment)) {
                $segments[] = $this->normalize_segment_manifest_entry($segment);
            }
        }

        return $segments;
    }

    /**
     * @param array<mixed> $segment
     * @return array<string, mixed>
     */
    private function normalize_segment_manifest_entry(array $segment): array
    {
        $normalized = array();
        foreach ($segment as $key => $value) {
            if (is_string($key)) {
                $normalized[ $key ] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function query_segments(RecordQuery $query): array
    {
        $after = $query->after_id();
        $lower = $query->lower_id();
        $upper = $query->upper_id();

        $segment_stats = $this->segment_stats;

        return array_values(
            array_filter(
                $this->all_segments(),
                static function (array $segment) use ($after, $lower, $upper, $segment_stats): bool {
                    $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
                    $stats = '' !== $file && isset($segment_stats[ $file ]) ? $segment_stats[ $file ] : null;
                    $min = is_array($stats) ? $stats['min'] : ( isset($segment['min']) && is_string($segment['min']) ? $segment['min'] : null );
                    $max = is_array($stats) ? $stats['max'] : ( isset($segment['max']) && is_string($segment['max']) ? $segment['max'] : null );

                    if (null === $min || null === $max) {
                        return true;
                    }

                    if (null !== $after && strcmp($max, $after) <= 0) {
                        return false;
                    }

                    if (null !== $lower && strcmp($max, $lower) < 0) {
                        return false;
                    }

                    return ! ( null !== $upper && strcmp($min, $upper) > 0 );
                }
            )
        );
    }

    /**
     * @param array<string, mixed> $segment
     */
    private function seek_offset_for(array $segment, ?string $seek_id): int
    {
        if (
            null === $seek_id ||
            ! isset($segment['index']) ||
            ! is_string($segment['index']) ||
            ! $this->segment_is_ordered($segment)
        ) {
            return 0;
        }

        $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
        $stats = '' !== $file && isset($this->segment_stats[ $file ]) ? $this->segment_stats[ $file ] : null;
        $min = is_array($stats) ? $stats['min'] : ( isset($segment['min']) && is_string($segment['min']) ? $segment['min'] : null );
        if (null !== $min && strcmp($min, $seek_id) >= 0) {
            return 0;
        }

        $path = $this->segment_path($segment['index']);
        if (! is_file($path)) {
            return 0;
        }

        $index  = $this->read_cached_jsonc_object(
            $path,
            $this->cache_enabled ? $this->sparse_cache_key($path) : null
        );
        $offset = 0;
        $entries = isset($index['entries']) && is_array($index['entries']) ? $index['entries'] : array();
        foreach ($entries as $entry) {
            if (
                is_array($entry) &&
                isset($entry['id'], $entry['offset']) &&
                is_string($entry['id']) &&
                is_int($entry['offset']) &&
                strcmp($entry['id'], $seek_id) <= 0
            ) {
                $offset = $entry['offset'];
            }
        }

        return $offset;
    }

    /**
     * @param array<string, mixed> $segment
     */
    private function segment_is_ordered(array $segment): bool
    {
        $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
        $stats = '' !== $file && isset($this->segment_stats[ $file ]) ? $this->segment_stats[ $file ] : null;
        if (is_array($stats)) {
            return $stats['ordered'];
        }

        return true === ( $segment['ordered'] ?? false );
    }

    private function seek_id_for_query(RecordQuery $query): ?string
    {
        $after = $query->after_id();
        $lower = $query->lower_id();
        if (null === $after) {
            return $lower;
        }

        if (null === $lower) {
            return $after;
        }

        return strcmp($after, $lower) >= 0 ? $after : $lower;
    }

    /**
     * @return array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}>
     */
    private function build_state_index(bool $truncate_torn = false, bool $build_equality_counts = true, bool $apply = true): array
    {
        $this->flush_active_handle();

        $state = array();
        $stats = array();
        $sparse_offsets = array();
        $equality_counts = array();
        $disabled_equality_count_fields = array();
        $equality_counts_valid = true;

        foreach ($this->all_segments() as $segment) {
            $file = isset($segment['file']) && is_string($segment['file']) ? $segment['file'] : '';
            if ('' === $file) {
                continue;
            }

            $path = $this->segment_path($file);
            if (! is_file($path)) {
                continue;
            }

            $handle = @fopen($path, $truncate_torn ? 'c+b' : 'rb');
            if (false === $handle) {
                continue;
            }

            try {
                $min     = null;
                $max     = null;
                $records = 0;
                $ordered = true;
                $last_id = null;
                $offsets = array();
                $last_good_offset = 0;
                $position = 0;
                while (true) {
                    $offset = $position;
                    $line   = fgets($handle);
                    if (false === $line) {
                        break;
                    }

                    $position += strlen($line);
                    try {
                        $envelope = $build_equality_counts
                            ? $this->decode_line($line)
                            : $this->state_index_entry_from_line($line);
                    } catch (\Throwable $throwable) {
                        if ($truncate_torn) {
                            ftruncate($handle, max(0, $last_good_offset));
                            AtomicFilesystem::sync_handle($handle, $path);
                            break;
                        }

                        throw $throwable;
                    }

                    $last_good_offset = $position;
                    $id            = $envelope['id'];
                    if (null !== $last_id && strcmp($id, $last_id) < 0) {
                        $ordered = false;
                    }
                    $last_id       = $id;
                    $min           = null === $min || strcmp($id, $min) < 0 ? $id : $min;
                    $max           = null === $max || strcmp($id, $max) > 0 ? $id : $max;
                    if (0 === $records % $this->sparse_index_interval) {
                        $offsets[] = array( $id, $offset );
                    }
                    $records++;
                    if ($build_equality_counts && 'put' === $envelope['op']) {
                        if (isset($state[ $id ])) {
                            $equality_counts_valid = false;
                            $equality_counts = array();
                            $disabled_equality_count_fields = array();
                        } elseif ($equality_counts_valid) {
                            $data = isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : array();
                            /** @var array<string, mixed> $data */
                            $this->remember_record_equality_counts_in($equality_counts, $disabled_equality_count_fields, $data);
                        }
                    } elseif ('delete' === $envelope['op']) {
                        $equality_counts_valid = false;
                        $equality_counts = array();
                        $disabled_equality_count_fields = array();
                    }

                    $state[ $id ] = array(
                        'deleted' => 'delete' === $envelope['op'],
                        'file'    => $file,
                        'offset'  => $offset,
                        'aliases' => array(),
                    );
                }

                $stats[ $file ] = array(
                    'min'     => $min,
                    'max'     => $max,
                    'records' => $records,
                    'ordered' => $ordered,
                );
                $sparse_offsets[ $file ] = $offsets;
            } finally {
                fclose($handle);
            }
        }

        ksort($state);
        if ($apply) {
            $this->segment_stats          = $stats;
            $this->segment_sparse_offsets = $sparse_offsets;
            if ($build_equality_counts) {
                $this->equality_counts        = $equality_counts;
                $this->disabled_equality_count_fields = $disabled_equality_count_fields;
                $this->equality_counts_valid = $equality_counts_valid;
            }
        }

        return $state;
    }

    /**
     * @param array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}> $left
     * @param array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}> $right
     */
    private function state_indexes_equivalent(array $left, array $right): bool
    {
        $left_keys = array_keys($left);
        $right_keys = array_keys($right);
        sort($left_keys, SORT_STRING);
        sort($right_keys, SORT_STRING);

        if ($left_keys !== $right_keys) {
            return false;
        }

        foreach ($left_keys as $id) {
            $entry = $left[ $id ];
            $other = $right[ $id ];
            if (
                $entry['deleted'] !== $other['deleted'] ||
                $entry['file'] !== $other['file'] ||
                $entry['offset'] !== $other['offset']
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id: string, op: string, data?: mixed}
     */
    private function read_envelope_at(string $file, int $offset): array
    {
        $this->flush_active_handle();

        $handle = @fopen($this->segment_path($file), 'rb');
        if (false === $handle) {
            throw new StorageException('Could not open segment: ' . $file);
        }

        try {
            fseek($handle, $offset);
            $line = fgets($handle);
            if (false === $line) {
                throw new StorageException('Could not read segment record.');
            }

            return $this->decode_line($line);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return null|array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}
     */
    private function state_entry(string $id): ?array
    {
        if (null === $this->state) {
            $this->recover();
        }

        return $this->state[ $id ] ?? null;
    }

    private function write_state_entry_without_aliases(string $id, bool $deleted, string $file, int $offset): void
    {
        $this->state ??= array();
        if (isset($this->state[ $id ])) {
            $previous_deleted = $this->state[ $id ]['deleted'];
            if ($previous_deleted !== $deleted) {
                $previous_deleted ? $this->deleted_record_count-- : $this->live_record_count--;
                $deleted ? $this->deleted_record_count++ : $this->live_record_count++;
            }
        } else {
            $deleted ? $this->deleted_record_count++ : $this->live_record_count++;
        }

        $this->state[ $id ] = array(
            'deleted' => $deleted,
            'file'    => $file,
            'offset'  => $offset,
            'aliases' => array(),
        );
    }

    /**
     * @param array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>} $source
     */
    private function write_compacted_state_entry(string $id, string $file, int $offset, array $source): void
    {
        $this->state ??= array();
        if (isset($this->state[ $id ])) {
            if ($this->state[ $id ]['deleted']) {
                $this->deleted_record_count--;
                $this->live_record_count++;
            }
        } else {
            $this->live_record_count++;
        }

        $aliases = array() === $source['aliases']
            ? array( array( 'file' => $source['file'], 'offset' => $source['offset'] ) )
            : $this->state_locations($source);

        $this->state[ $id ] = array(
            'deleted' => false,
            'file'    => $file,
            'offset'  => $offset,
            'aliases' => $aliases,
        );
    }

    /**
     * @param array<string, array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>}> $state
     */
    private function replace_state_index(array $state): void
    {
        $this->state = $state;
        $this->refresh_state_counts();
    }

    /**
     * @param array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>} $entry
     * @return list<array{file: string, offset: int}>
     */
    private function state_locations(array $entry): array
    {
        return $this->dedupe_locations(
            array_merge(
                array(
                    array(
                        'file'   => $entry['file'],
                        'offset' => $entry['offset'],
                    ),
                ),
                $entry['aliases']
            )
        );
    }

    /**
     * @param array{deleted: bool, file: string, offset: int, aliases: list<array{file: string, offset: int}>} $entry
     */
    private function state_entry_matches(array $entry, string $file, int $offset): bool
    {
        if ($file === $entry['file'] && $offset === $entry['offset']) {
            return true;
        }

        foreach ($entry['aliases'] as $location) {
            if ($file === $location['file'] && $offset === $location['offset']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{file: string, offset: int}> $locations
     * @return list<array{file: string, offset: int}>
     */
    private function dedupe_locations(array $locations, ?string $exclude_file = null, ?int $exclude_offset = null): array
    {
        $seen   = array();
        $result = array();

        foreach ($locations as $location) {
            $file   = $location['file'];
            $offset = $location['offset'];
            if ($exclude_file === $file && $exclude_offset === $offset) {
                continue;
            }

            $key = $file . ':' . $offset;
            if (isset($seen[ $key ])) {
                continue;
            }

            $seen[ $key ] = true;
            $result[]     = array(
                'file'   => $file,
                'offset' => $offset,
            );
        }

        return $result;
    }

    /**
     * @param array{id: string, op: string, data?: mixed} $envelope
     */
    private function record_from_envelope(array $envelope): StorageRecord
    {
        $id   = $envelope['id'];
        UuidV7::assert_valid($id);

        return new StorageRecord($id, $this->data_from_envelope($envelope));
    }

    /**
     * @param array{id: string, op: string, data?: mixed} $envelope
     * @return array<string, mixed>
     */
    private function data_from_envelope(array $envelope): array
    {
        if (! isset($envelope['data']) || ! is_array($envelope['data'])) {
            return array();
        }

        $data = $envelope['data'];
        foreach ($data as $key => $_value) {
            if (! is_string($key)) {
                $filtered = array();
                foreach ($data as $copy_key => $value) {
                    if (is_string($copy_key)) {
                        $filtered[ $copy_key ] = $value;
                    }
                }

                return $filtered;
            }
        }

        return $data;
    }

    private function line_match_marker(QueryCondition $condition): ?string
    {
        if ('eq' !== $condition->operator() || 'id' === $condition->field()) {
            return null;
        }

        $value = $condition->value();
        if (null !== $value && ! is_scalar($value)) {
            return null;
        }

        return json_encode(
            $condition->field(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . ':' . json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function count_equal_live_records(?QueryCondition $condition, ?string $cursor, ?int $limit): ?int
    {
        if (null === $condition || null !== $cursor || 'eq' !== $condition->operator()) {
            return null;
        }

        $this->state_index();

        if ('id' === $condition->field()) {
            $id = $condition->value();
            if (! is_string($id)) {
                return 0;
            }

            $entry = $this->state[ $id ] ?? null;
            $count = null !== $entry && ! $entry['deleted'] ? 1 : 0;

            return null === $limit ? $count : min($count, $limit);
        }

        $value = $condition->value();
        if (! $this->countable_equality_value($value)) {
            return null;
        }

        if (! $this->equality_counts_valid && ! $this->equality_counts_rebuild_attempted) {
            $this->rebuild_equality_counts();
        }

        if (! $this->equality_counts_valid || isset($this->disabled_equality_count_fields[ $condition->field() ])) {
            return null;
        }

        $count = $this->equality_counts[ $condition->field() ][ $this->equality_value_key($value) ] ?? 0;

        return null === $limit ? $count : min($count, $limit);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function remember_record_equality_counts(array $data): void
    {
        if (! $this->equality_counts_valid) {
            return;
        }

        $this->remember_record_equality_counts_in($this->equality_counts, $this->disabled_equality_count_fields, $data);
    }

    /**
     * @param array<string, array<string, int>> $counts
     * @param array<string, true> $disabled_fields
     * @param array<string, mixed> $data
     */
    private function remember_record_equality_counts_in(array &$counts, array &$disabled_fields, array $data): void
    {
        foreach ($data as $field => $value) {
            if (isset($disabled_fields[ $field ]) || ! $this->countable_equality_value($value)) {
                continue;
            }

            $key = $this->equality_value_key($value);
            if (! isset($counts[ $field ][ $key ]) && count($counts[ $field ] ?? array()) >= self::EQUALITY_COUNT_MAX_VALUES_PER_FIELD) {
                $disabled_fields[ $field ] = true;
                unset($counts[ $field ]);
                continue;
            }

            $counts[ $field ][ $key ] = ( $counts[ $field ][ $key ] ?? 0 ) + 1;
        }
    }

    private function invalidate_equality_counts(): void
    {
        $this->equality_counts = array();
        $this->disabled_equality_count_fields = array();
        $this->equality_counts_valid = false;
        $this->equality_counts_rebuild_attempted = false;
    }

    private function rebuild_equality_counts(): void
    {
        $this->equality_counts_rebuild_attempted = true;
        $this->replace_state_index($this->build_state_index(false, true));
    }

    private function countable_equality_value(mixed $value): bool
    {
        return null === $value || is_scalar($value);
    }

    private function equality_value_key(mixed $value): string
    {
        if (is_string($value)) {
            return 's:' . $value;
        }

        if (is_int($value)) {
            return 'i:' . $value;
        }

        if (is_bool($value)) {
            return 'b:' . ( $value ? '1' : '0' );
        }

        if (is_float($value)) {
            return 'f:' . sprintf('%.17G', $value);
        }

        return 'n:';
    }

    /**
     * @param list<list<QueryCondition>> $groups
     */
    private function query_has_no_conditions(array $groups): bool
    {
        return 1 === count($groups) && array() === $groups[0];
    }

    /**
     * @param list<list<QueryCondition>> $groups
     */
    private function query_single_condition(array $groups): ?QueryCondition
    {
        return 1 === count($groups) && 1 === count($groups[0]) ? $groups[0][0] : null;
    }

    private function count_live_state_records(?string $cursor, ?int $limit): int
    {
        if (null === $cursor) {
            $this->state_index();

            return null === $limit ? $this->live_record_count : min($this->live_record_count, $limit);
        }

        $count = 0;
        foreach ($this->state_index() as $id => $entry) {
            if ($entry['deleted']) {
                continue;
            }

            if ($id <= $cursor) {
                continue;
            }

            $count++;
            if (null !== $limit && $count >= $limit) {
                return $count;
            }
        }

        return $count;
    }

    private function deleted_state_records(): int
    {
        $this->state_index();

        return $this->deleted_record_count;
    }

    private function refresh_state_counts(): void
    {
        $live = 0;
        $deleted = 0;
        foreach ($this->state ?? array() as $entry) {
            if ($entry['deleted']) {
                $deleted++;
                continue;
            }

            $live++;
        }

        $this->live_record_count = $live;
        $this->deleted_record_count = $deleted;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function encode_line(array $envelope): string
    {
        $json = json_encode(
            $envelope,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );

        return strlen($json) . "\t" . hash(self::LINE_HASH_ALGORITHM, $json) . "\t" . $json . "\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode_put_line(string $id, array $data): string
    {
        $json = '{"op":"put","id":"' . $id . '","data":' . json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . '}';

        return strlen($json) . "\t" . hash(self::LINE_HASH_ALGORITHM, $json) . "\t" . $json . "\n";
    }

    /**
     * @param array{id: string, op: string, data?: mixed} $envelope
     */
    private function compaction_line(string $line, array $envelope): string
    {
        if (
            'put' === $envelope['op'] &&
            isset($envelope['data']) &&
            is_array($envelope['data']) &&
            str_ends_with($line, "\n")
        ) {
            return $line;
        }

        return $this->encode_line(
            array(
                'op'   => 'put',
                'id'   => $envelope['id'],
                'data' => $this->data_from_envelope($envelope),
            )
        );
    }

    /**
     * @return array{id: string, op: string, copy: bool, envelope?: array{id: string, op: string, data?: mixed}}
     */
    private function compaction_entry_from_line(string $line): array
    {
        $json = $this->validated_line_json($line);
        if (str_ends_with($line, "\n") && str_starts_with($json, self::CANONICAL_PUT_PREFIX)) {
            $id_start = strlen(self::CANONICAL_PUT_PREFIX);
            $id       = substr($json, $id_start, 36);
            if (
                36 === strlen($id) &&
                str_starts_with(substr($json, $id_start + 36), '","data":') &&
                str_ends_with($json, '}')
            ) {
                return array(
                    'id'   => $id,
                    'op'   => 'put',
                    'copy' => true,
                );
            }
        }

        if (str_starts_with($json, self::CANONICAL_DELETE_PREFIX)) {
            $id_start = strlen(self::CANONICAL_DELETE_PREFIX);
            $id       = substr($json, $id_start, 36);
            if (36 === strlen($id) && '"}' === substr($json, $id_start + 36)) {
                return array(
                    'id'   => $id,
                    'op'   => 'delete',
                    'copy' => false,
                );
            }
        }

        $envelope = $this->decode_json_envelope($json);

        return array(
            'id'       => $envelope['id'],
            'op'       => $envelope['op'],
            'copy'     => 'put' === $envelope['op'] &&
                isset($envelope['data']) &&
                is_array($envelope['data']) &&
                str_ends_with($line, "\n"),
            'envelope' => $envelope,
        );
    }

    /**
     * @return array{id: string, op: string}
     */
    private function state_index_entry_from_line(string $line): array
    {
        $json = $this->validated_line_json($line);
        if (str_starts_with($json, self::CANONICAL_PUT_PREFIX)) {
            $id_start = strlen(self::CANONICAL_PUT_PREFIX);
            $id       = substr($json, $id_start, 36);
            if (
                36 === strlen($id) &&
                str_starts_with(substr($json, $id_start + 36), '","data":') &&
                str_ends_with($json, '}')
            ) {
                return array(
                    'id' => $id,
                    'op' => 'put',
                );
            }
        }

        if (str_starts_with($json, self::CANONICAL_DELETE_PREFIX)) {
            $id_start = strlen(self::CANONICAL_DELETE_PREFIX);
            $id       = substr($json, $id_start, 36);
            if (36 === strlen($id) && '"}' === substr($json, $id_start + 36)) {
                return array(
                    'id' => $id,
                    'op' => 'delete',
                );
            }
        }

        $envelope = $this->decode_json_envelope($json);

        return array(
            'id' => $envelope['id'],
            'op' => $envelope['op'],
        );
    }

    /**
     * @return array{id: string, op: string, data?: mixed}
     */
    private function decode_line(string $line): array
    {
        $parts = explode("\t", rtrim($line, "\r\n"), 3);
        if (3 !== count($parts)) {
            throw new StorageException('Malformed segmented log line.');
        }

        $json = $parts[2];
        if ((int) $parts[0] !== strlen($json) || $parts[1] !== hash(self::LINE_HASH_ALGORITHM, $json)) {
            throw new StorageException('Corrupt segmented log line.');
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($decoded) ||
            ! isset($decoded['id'], $decoded['op']) ||
            ! is_string($decoded['id']) ||
            ! is_string($decoded['op'])
        ) {
            throw new StorageException('Segmented log line does not contain a record envelope.');
        }

        $envelope = array(
            'id' => $decoded['id'],
            'op' => $decoded['op'],
        );

        if (array_key_exists('data', $decoded)) {
            $envelope['data'] = $decoded['data'];
        }

        return $envelope;
    }

    private function validated_line_json(string $line): string
    {
        $length = strlen($line);
        if ($length > 0 && "\n" === $line[ $length - 1 ]) {
            $line = substr($line, 0, $length - 1);
            $length--;
        }
        if ($length > 0 && "\r" === $line[ $length - 1 ]) {
            $line = substr($line, 0, $length - 1);
        }

        $first = strpos($line, "\t");
        $second = false === $first ? false : strpos($line, "\t", $first + 1);
        if (false === $first || false === $second) {
            throw new StorageException('Malformed segmented log line.');
        }

        $json = substr($line, $second + 1);
        $checksum = substr($line, $first + 1, $second - $first - 1);
        if ((int) substr($line, 0, $first) !== strlen($json) || $checksum !== hash(self::LINE_HASH_ALGORITHM, $json)) {
            throw new StorageException('Corrupt segmented log line.');
        }

        return $json;
    }

    /**
     * @return array{id: string, op: string, data?: mixed}
     */
    private function decode_json_envelope(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($decoded) ||
            ! isset($decoded['id'], $decoded['op']) ||
            ! is_string($decoded['id']) ||
            ! is_string($decoded['op'])
        ) {
            throw new StorageException('Segmented log line does not contain a record envelope.');
        }

        $envelope = array(
            'id' => $decoded['id'],
            'op' => $decoded['op'],
        );

        if (array_key_exists('data', $decoded)) {
            $envelope['data'] = $decoded['data'];
        }

        return $envelope;
    }

    /**
     * @param list<array{0: string, 1: int}> $entries
     */
    private function write_sparse_entries_to(string $path, array $entries): void
    {
        $encoded = array();
        foreach ($entries as $entry) {
            $encoded[] = array(
                'id'     => $entry[0],
                'offset' => $entry[1],
            );
        }

        AtomicFilesystem::write_atomic($path, Jsonc::encode_compact_object(array( 'entries' => $encoded )));
        if ($this->cache_enabled) {
            $this->cache->delete($this->sparse_cache_key($path));
        }
    }

    private function with_lock(callable $callback): mixed
    {
        if (! is_resource($this->lock_handle)) {
            AtomicFilesystem::ensure_directory($this->collection_root());
            $handle = @fopen($this->collection_root() . '/collection.lock', 'c');
            if (false !== $handle) {
                $this->lock_handle = $handle;
            }
        }

        if (! is_resource($this->lock_handle)) {
            throw new StorageException('Could not open collection lock.');
        }

        try {
            if (! flock($this->lock_handle, LOCK_EX)) {
                // @codeCoverageIgnoreStart
                throw new StorageException('Could not acquire collection lock.');
                // @codeCoverageIgnoreEnd
            }

            return $callback();
        } finally {
            flock($this->lock_handle, LOCK_UN);
        }
    }

    private function collection_root(): string
    {
        return $this->collection_root_path;
    }

    private function assert_record_id(string $id, bool $generated): void
    {
        if ($generated && $this->trusted_generated_ids) {
            return;
        }

        UuidV7::assert_valid($id);
    }

    private function segments_root(): string
    {
        return $this->segments_root_path;
    }

    private function segment_path(string $file): string
    {
        return $this->segments_root() . '/' . $file;
    }

    /**
     * @return resource
     */
    private function active_segment_handle(string $file, string $path): mixed
    {
        if (
            is_resource($this->active_handle) &&
            $this->active_handle_file === $file &&
            $this->active_handle_path === $path
        ) {
            return $this->active_handle;
        }

        $this->close_active_handle();
        $handle = @fopen($path, 'c+b');
        if (false === $handle) {
            throw new StorageException('Could not open active segment.');
        }

        $this->active_handle      = $handle;
        $this->active_handle_file = $file;
        $this->active_handle_path = $path;

        return $handle;
    }

    private function close_active_handle(): void
    {
        $handle = $this->active_handle;
        if (is_resource($handle)) {
            $this->flush_active_handle();
            fclose($handle);
        }

        $this->active_handle      = null;
        $this->active_handle_dirty = false;
        $this->active_handle_file = null;
        $this->active_handle_path = null;
    }

    private function flush_active_handle(): void
    {
        if (! is_resource($this->active_handle) || ! $this->active_handle_dirty) {
            return;
        }

        AtomicFilesystem::sync_handle($this->active_handle, $this->active_handle_path ?? 'active segment');
        $this->active_handle_dirty = false;
    }

    private function remember_segment_record(string $file, string $id, int $offset): void
    {
        $stats = $this->segment_stats[ $file ] ?? array( 'min' => null, 'max' => null, 'records' => 0, 'ordered' => true );

        if (null !== $stats['max'] && strcmp($id, $stats['max']) < 0) {
            $stats['ordered'] = false;
        }
        $stats['min'] = null === $stats['min'] || strcmp($id, $stats['min']) < 0 ? $id : $stats['min'];
        $stats['max'] = null === $stats['max'] || strcmp($id, $stats['max']) > 0 ? $id : $stats['max'];
        if (0 === $stats['records'] % $this->sparse_index_interval) {
            $this->segment_sparse_offsets[ $file ][] = array( $id, $offset );
        }
        $stats['records']++;

        $this->segment_stats[ $file ] = $stats;
    }

    private function manifest_path(): string
    {
        return $this->manifest_file_path;
    }

    private function delete_state_entry(string $id): void
    {
        if (null !== $this->state) {
            if (isset($this->state[ $id ])) {
                $previous = $this->state[ $id ];
                $previous['deleted'] ? $this->deleted_record_count-- : $this->live_record_count--;
                if (! $previous['deleted']) {
                    $this->invalidate_equality_counts();
                }
            }

            unset($this->state[ $id ]);
        }
    }

    private function segment_file_name(int $number): string
    {
        return sprintf('seg-%06d.ndjson', max(1, $number));
    }

    private function index_file_name_for_segment(string $file): string
    {
        return preg_replace('/\.ndjson$/', '.idx.jsonc', $file) ?? ( $file . '.idx.jsonc' );
    }

    private function next_segment_number_after(string $file): int
    {
        if (1 === preg_match('/^seg-(\d+)\.ndjson$/', $file, $matches)) {
            return (int) $matches[1] + 1;
        }

        return 2;
    }

    private function next_available_segment_file(int $start): string
    {
        $number = max(1, $start);
        do {
            $file = $this->segment_file_name($number);
            $number++;
        } while (is_file($this->segment_path($file)));

        return $file;
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

    private function delete_compaction_leftovers(): void
    {
        foreach (glob($this->collection_root() . '/segments.compact-*') ?: array() as $directory) {
            if (is_dir($directory)) {
                $this->delete_directory($directory);
            }
        }

        foreach (glob($this->collection_root() . '/index.rebuild-*') ?: array() as $directory) {
            if (is_dir($directory)) {
                $this->delete_directory($directory);
            }
        }

        foreach (glob($this->collection_root() . '/index.backup-*') ?: array() as $directory) {
            if (is_dir($directory)) {
                $this->delete_directory($directory);
            }
        }

        $referenced = $this->referenced_segment_artifacts();
        if (null === $referenced) {
            return;
        }

        foreach (glob($this->segments_root() . '/compact-*.ndjson') ?: array() as $path) {
            if (is_file($path) && ! isset($referenced[ basename($path) ])) {
                @unlink($path);
            }
        }

        foreach (glob($this->segments_root() . '/compact-*.idx.jsonc') ?: array() as $path) {
            if (is_file($path) && ! isset($referenced[ basename($path) ])) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array<string, true>|null
     */
    private function referenced_segment_artifacts(): ?array
    {
        if (! is_file($this->manifest_path())) {
            return array();
        }

        try {
            $manifest = AtomicFilesystem::read_jsonc_object($this->manifest_path());
        } catch (\Throwable) {
            return null;
        }

        $referenced = array();
        $segments = isset($manifest['sealed']) && is_array($manifest['sealed']) ? $manifest['sealed'] : array();
        if (isset($manifest['active']) && is_array($manifest['active'])) {
            $segments[] = $manifest['active'];
        }

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $file = $segment['file'] ?? null;
            if (is_string($file) && '' !== $file) {
                $referenced[ $file ] = true;
                $referenced[ $this->index_file_name_for_segment($file) ] = true;
            }

            $index = $segment['index'] ?? null;
            if (is_string($index) && '' !== $index) {
                $referenced[ $index ] = true;
            }
        }

        return $referenced;
    }

    private function partitioned_collection(string $collection, ?string $partition, ?int $timestamp_ms): string
    {
        if (null === $partition) {
            return $collection;
        }

        $timestamp_ms ??= (int) ( microtime(true) * 1000 );
        $seconds = intdiv($timestamp_ms, 1000);

        return match ($partition) {
            'daily' => $collection . '/partitions/' . gmdate('Y-m-d', $seconds),
            'monthly' => $collection . '/partitions/' . gmdate('Y-m', $seconds),
            default => throw new StorageException('Unsupported log partition: ' . $partition),
        };
    }

    private function manifest_cache_key(): string
    {
        return 'log:manifest:' . $this->collection_path;
    }

    private function sparse_cache_key(string $path): string
    {
        return 'log:sparse:' . $this->collection_path . ':' . basename($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function read_cached_jsonc_object(string $path, ?string $key): array
    {
        if (null === $key) {
            return AtomicFilesystem::read_jsonc_object($path);
        }

        $cached = $this->cache->get($key);
        if (CacheValidation::TRUST === $this->cache_validation && is_array($cached) && is_array($cached['data'] ?? null)) {
            /** @var array<string, mixed> $data */
            $data = $cached['data'];
            return $data;
        }

        clearstatcache(true, $path);
        $exists = is_file($path);
        $mtime  = $exists ? (int) filemtime($path) : 0;
        $size   = $exists ? (int) filesize($path) : -1;
        $hash   = $exists && CacheValidation::HASH === $this->cache_validation
            ? (string) hash_file(self::CACHE_HASH_ALGORITHM, $path)
            : '';

        if (
            is_array($cached) &&
            isset($cached['mtime'], $cached['size'], $cached['data']) &&
            $cached['mtime'] === $mtime &&
            $cached['size'] === $size &&
            (
                CacheValidation::HASH !== $this->cache_validation ||
                ( isset($cached['hash']) && $cached['hash'] === $hash )
            ) &&
            is_array($cached['data'])
        ) {
            /** @var array<string, mixed> $data */
            $data = $cached['data'];
            return $data;
        }

        $data = AtomicFilesystem::read_jsonc_object($path);
        $this->cache->set(
            $key,
            array(
                'mtime' => $mtime,
                'size'  => $size,
                'hash'  => $hash,
                'data'  => $data,
            )
        );

        return $data;
    }
}
