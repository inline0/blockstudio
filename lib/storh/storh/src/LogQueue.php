<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class LogQueue
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    private const LINE_HASH_ALGORITHM = 'xxh32';

    /** @var callable(): string */
    private mixed $id_generator;

    private bool $trusted_generated_ids;

    private string $queue_path;

    private string $log_file_path;

    private string $lock_file_path;

    /** @var resource|null */
    private mixed $lock_handle = null;

    /** @var resource|null */
    private mixed $log_handle = null;

    private int $log_offset = 0;

    /** @var array<string, array<string, mixed>> */
    private array $payloads = array();

    /** @var array<string, true> */
    private array $pending = array();

    /** @var array<string, int> */
    private array $processing = array();

    /** @var array<string, int> */
    private array $done = array();

    /** @var list<string> */
    private array $pending_order = array();

    private int $pending_offset = 0;

    private int $pending_deletes = 0;

    private int $processing_deletes = 0;

    private int $done_deletes = 0;

    public function __construct(
        private readonly string $root,
        private readonly string $name,
        ?callable $id_generator = null
    ) {
        $this->trusted_generated_ids = null === $id_generator;
        $this->id_generator          = $id_generator ?? static fn(): string => UuidV7::generate();
        $this->queue_path            = rtrim($this->root, '/\\') . '/' . $this->name;
        $this->log_file_path         = $this->queue_path . '/queue.log';
        $this->lock_file_path        = $this->queue_path . '/queue.lock';
        AtomicFilesystem::ensure_directory($this->queue_root());
        $this->with_lock(function (): void {
            $this->replay_log(true);
        });
    }

    public function __destruct()
    {
        if (is_resource($this->log_handle)) {
            fclose($this->log_handle);
        }

        if (is_resource($this->lock_handle)) {
            fclose($this->lock_handle);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $payload, ?string $id = null): string
    {
        $generated = null === $id;
        $id ??= ( $this->id_generator )();
        $this->assert_job_id($id, $generated);

        $this->with_lock(function () use ($id, $payload): void {
            $this->sync_from_log();
            $this->append_enqueue_event($id, $payload, time());
        });

        return $id;
    }

    /**
     * @param iterable<array<string, mixed>> $jobs
     * @return list<string>
     */
    public function enqueueMany(iterable $jobs): array
    {
        $ids = array();
        $now = time();

        $this->with_lock(function () use ($jobs, &$ids, $now): void {
            $this->sync_from_log();
            $this->append_enqueue_events($jobs, $ids, $now);
        });

        return $ids;
    }

    public function claim(): ?StorageRecord
    {
        return $this->with_lock(function (): ?StorageRecord {
            $this->sync_from_log();
            $pending_count = count($this->pending_order);

            while ($this->pending_offset < $pending_count) {
                $id = $this->pending_order[ $this->pending_offset ];
                $this->pending_offset++;
                if (! isset($this->pending[ $id ])) {
                    continue;
                }

                $this->append_claim_event($id, time());

                $record = new StorageRecord($id, $this->payload_for_id($id));
                if ($this->pending_offset >= 4096 && $this->pending_offset * 2 >= $pending_count) {
                    $this->compact_pending_order();
                }

                return $record;
            }

            if ($this->pending_offset >= 4096 && $this->pending_offset * 2 >= $pending_count) {
                $this->compact_pending_order();
            }

            return null;
        });
    }

    /**
     * @return list<StorageRecord>
     */
    public function claimMany(int $limit): array
    {
        if ($limit < 1) {
            throw new StorageException('Queue claim limit must be at least 1.');
        }

        return $this->with_lock(function () use ($limit): array {
            $this->sync_from_log();
            $records = array();
            $now = time();
            $claimed = 0;
            $pending_count = count($this->pending_order);

            while ($claimed < $limit && $this->pending_offset < $pending_count) {
                $id = $this->pending_order[ $this->pending_offset ];
                $this->pending_offset++;
                if (! isset($this->pending[ $id ])) {
                    continue;
                }

                $records[] = new StorageRecord($id, $this->payload_for_id($id));
                $claimed++;
            }

            if ($this->pending_offset >= 4096 && $this->pending_offset * 2 >= $pending_count) {
                $this->compact_pending_order();
            }
            $this->append_claim_events($records, $now);

            return $records;
        });
    }

    public function complete(string $id, bool $keep_done = true): void
    {
        UuidV7::assert_valid($id);

        $this->with_lock(function () use ($id, $keep_done): void {
            $this->sync_from_log();
            if (! isset($this->processing[ $id ])) {
                return;
            }

            $this->append_complete_event($id, $keep_done, time());
        });
    }

    /**
     * @param iterable<string> $ids
     */
    public function completeMany(iterable $ids, bool $keep_done = true): int
    {
        $validated = array();
        foreach ($ids as $id) {
            UuidV7::assert_valid($id);
            $validated[] = $id;
        }

        if (array() === $validated) {
            return 0;
        }

        return $this->with_lock(function () use ($validated, $keep_done): int {
            $this->sync_from_log();
            $completed = 0;
            $now = time();

            $this->append_complete_events($validated, $keep_done, $completed, $now);

            return $completed;
        });
    }

    public function requeue_timed_out(int $timeout_seconds): int
    {
        return $this->with_lock(function () use ($timeout_seconds): int {
            $this->sync_from_log();
            $now = time();
            $ids = array();

            foreach ($this->processing as $id => $claimed_at) {
                if ($now - $claimed_at < $timeout_seconds) {
                    continue;
                }

                $ids[] = $id;
            }

            $this->append_events($this->id_events('requeue', $ids, $now));

            return count($ids);
        });
    }

    public function purgeDone(int $olderThanSeconds = 0): int
    {
        return $this->with_lock(function () use ($olderThanSeconds): int {
            $this->sync_from_log();
            $now = time();
            $ids = array();

            foreach ($this->done as $id => $completed_at) {
                if ($olderThanSeconds > 0 && $now - $completed_at < $olderThanSeconds) {
                    continue;
                }

                $ids[] = $id;
            }

            $this->append_events($this->id_events('purge', $ids, $now));

            return count($ids);
        });
    }

    /**
     * @return array{pending: int, processing: int, done: int}
     */
    public function counts(): array
    {
        return $this->with_lock(function (): array {
            $this->sync_from_log();

            return $this->current_counts();
        });
    }

    /**
     * @return array{pending: int, processing: int, done: int, bytes: int}
     */
    public function stats(): array
    {
        return $this->with_lock(function (): array {
            $this->sync_from_log();
            $counts = $this->current_counts();

            return array(
                'pending'    => $counts['pending'],
                'processing' => $counts['processing'],
                'done'       => $counts['done'],
                'bytes'      => is_file($this->log_path()) ? (int) filesize($this->log_path()) : 0,
            );
        });
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
        return $this->with_lock(function (): array {
            $errors = array();
            $replayed = null;
            $handle = @fopen($this->log_path(), 'rb');
            if (false === $handle) {
                $errors[] = 'Could not read log queue file.';
            } else {
                try {
                    $replayed = $this->empty_replayed_state();
                    $line_number = 0;
                    while (false !== ( $line = fgets($handle) )) {
                        $line_number++;
                        try {
                            $this->apply_replayed_event($replayed, $this->decode_line($line));
                        } catch (\Throwable $throwable) {
                            $errors[] = 'line ' . $line_number . ': ' . $throwable->getMessage();
                            break;
                        }
                    }
                } finally {
                    fclose($handle);
                }
            }

            if (null !== $replayed) {
                $replayed['pendingOrder'] = $this->effective_pending_order($replayed['pending'], $replayed['pendingOrder'], 0);
            }

            $this->sync_from_log();
            if (null !== $replayed && array() === $errors && ! $this->queue_state_matches_replay($replayed)) {
                $errors[] = 'Log queue state drift.';
            }

            return array(
                'ok'     => array() === $errors,
                'errors' => $errors,
                'stats'  => $this->stats_without_lock(),
            );
        });
    }

    /**
     * @return array{ok: bool, requeued: int}
     */
    public function repair(int $processingTimeoutSeconds = 3600): array
    {
        return $this->with_lock(function () use ($processingTimeoutSeconds): array {
            $this->replay_log(true);
            $now = time();
            $ids = array();

            foreach ($this->processing as $id => $claimed_at) {
                if ($now - $claimed_at < $processingTimeoutSeconds) {
                    continue;
                }

                $ids[] = $id;
            }

            $this->append_events($this->id_events('requeue', $ids, $now));

            return array(
                'ok'       => true,
                'requeued' => count($ids),
            );
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function with_lock(callable $callback): mixed
    {
        $handle = $this->lock_handle();
        if (! flock($handle, LOCK_EX)) {
            throw new StorageException('Could not lock log queue.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
        }
    }

    /**
     * @return resource
     */
    private function lock_handle(): mixed
    {
        if (is_resource($this->lock_handle)) {
            return $this->lock_handle;
        }

        $handle = @fopen($this->lock_file_path, 'c+b');
        if (false === $handle) {
            throw new StorageException('Could not open log queue lock.');
        }

        $this->lock_handle = $handle;

        return $handle;
    }

    /**
     * @return resource
     */
    private function log_handle(): mixed
    {
        if (is_resource($this->log_handle)) {
            return $this->log_handle;
        }

        $handle = @fopen($this->log_path(), 'c+b');
        if (false === $handle) {
            throw new StorageException('Could not open log queue file.');
        }

        $this->log_handle = $handle;

        return $handle;
    }

    private function queue_root(): string
    {
        return $this->queue_path;
    }

    private function assert_job_id(string $id, bool $generated): void
    {
        if ($generated && $this->trusted_generated_ids) {
            return;
        }

        UuidV7::assert_valid($id);
    }

    private function log_path(): string
    {
        return $this->log_file_path;
    }

    private function sync_from_log(): void
    {
        $handle = $this->log_handle();
        $stat = fstat($handle);
        $size = is_array($stat) && isset($stat['size']) ? (int) $stat['size'] : 0;
        if ($size < $this->log_offset) {
            $this->replay_log(false);
            return;
        }

        if ($size === $this->log_offset) {
            return;
        }

        fseek($handle, $this->log_offset);
        $offset = $this->log_offset;
        while (false !== ( $line = fgets($handle) )) {
            $offset += strlen($line);
            try {
                $this->apply_event($this->decode_line($line));
            } catch (\Throwable) {
                break;
            }

            $this->log_offset = $offset;
        }

        $this->pending_order = $this->effective_pending_order($this->pending, $this->pending_order, 0);
        $this->pending_offset = 0;
        fseek($handle, 0, SEEK_END);
    }

    private function replay_log(bool $truncate_torn): void
    {
        $this->payloads      = array();
        $this->pending       = array();
        $this->processing    = array();
        $this->done          = array();
        $this->pending_order = array();
        $this->pending_offset = 0;
        $this->pending_deletes = 0;
        $this->processing_deletes = 0;
        $this->done_deletes = 0;
        $this->log_offset    = 0;

        $handle = $this->log_handle();
        fseek($handle, 0);
        $offset = 0;
        while (false !== ( $line = fgets($handle) )) {
            $line_start = $this->log_offset;
            $offset += strlen($line);
            try {
                $this->apply_event($this->decode_line($line));
            } catch (\Throwable) {
                if ($truncate_torn) {
                    $truncate_at = max(0, $line_start);
                    ftruncate($handle, $truncate_at);
                    AtomicFilesystem::sync_handle($handle, $this->log_path());
                }
                break;
            }

            $this->log_offset = $offset;
        }

        $this->pending_order = $this->effective_pending_order($this->pending, $this->pending_order, 0);
        $this->pending_offset = 0;
        fseek($handle, 0, SEEK_END);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function append_enqueue_event(string $id, array $payload, int $now): void
    {
        $line = $this->encode_enqueue_line($id, $payload, $now);
        $handle = $this->append_event_line($line);
        $this->apply_enqueue_event($id, $payload, $now);
        $this->finish_appended_event($handle, strlen($line));
    }

    private function append_claim_event(string $id, int $now): void
    {
        $line = $this->encode_id_line('claim', $id, $now);
        $handle = $this->append_event_line($line);
        $this->apply_claim_event($id, $now);
        $this->finish_appended_event($handle, strlen($line));
    }

    private function append_complete_event(string $id, bool $keep_done, int $now): void
    {
        $line = $this->encode_complete_line($id, $keep_done, $now);
        $handle = $this->append_event_line($line);
        $this->apply_appended_complete_event($id, $keep_done, $now);
        $this->finish_appended_event($handle, strlen($line));
    }

    /**
     * @return resource
     */
    private function append_event_line(string $line): mixed
    {
        $handle = $this->log_handle();
        AtomicFilesystem::write_all($handle, $line, $this->log_path());

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function finish_appended_event(mixed $handle, ?int $written_bytes = null): void
    {
        AtomicFilesystem::sync_handle($handle, $this->log_path());
        if (null !== $written_bytes) {
            $this->log_offset += $written_bytes;
            return;
        }

        $offset = ftell($handle);
        $this->log_offset = false === $offset ? $this->log_offset : $offset;
    }

    /**
     * @param iterable<array<string, mixed>> $events
     */
    private function append_events(iterable $events): void
    {
        $path = $this->log_path();
        $handle = $this->log_handle();
        $lines = '';
        $applied = array();
        $wrote = false;

        fseek($handle, 0, SEEK_END);
        foreach ($events as $event) {
            $lines .= $this->encode_line($event);
            $applied[] = $event;

            if (strlen($lines) < 1_048_576) {
                continue;
            }

            AtomicFilesystem::write_all($handle, $lines, $path);
            foreach ($applied as $applied_event) {
                $this->apply_event($applied_event);
            }

            $lines = '';
            $applied = array();
            $wrote = true;
        }

        if ('' !== $lines) {
            AtomicFilesystem::write_all($handle, $lines, $path);
            foreach ($applied as $applied_event) {
                $this->apply_event($applied_event);
            }

            $wrote = true;
        }

        if (! $wrote) {
            return;
        }

        AtomicFilesystem::sync_handle($handle, $path);
        $offset = ftell($handle);
        $this->log_offset = false === $offset ? $this->log_offset : $offset;
    }

    /**
     * @param iterable<array<string, mixed>> $jobs
     * @param list<string> $ids
     */
    private function append_enqueue_events(iterable $jobs, array &$ids, int $now): void
    {
        $path = $this->log_path();
        $handle = $this->log_handle();
        $lines = '';
        $pending_ids = array();
        $pending_payloads = array();
        $wrote = false;

        fseek($handle, 0, SEEK_END);
        foreach ($jobs as $job) {
            $id = isset($job['id']) && is_string($job['id']) ? $job['id'] : null;
            $payload = isset($job['payload']) && is_array($job['payload']) ? $job['payload'] : $job;
            $generated = null === $id;
            $id ??= ( $this->id_generator )();
            $this->assert_job_id($id, $generated);

            /** @var array<string, mixed> $payload */
            $ids[] = $id;
            $lines .= $this->encode_enqueue_line($id, $payload, $now);
            $pending_ids[] = $id;
            $pending_payloads[] = $payload;

            if (strlen($lines) < 1_048_576) {
                continue;
            }

            $this->flush_enqueue_event_buffer($handle, $lines, $path, $pending_ids, $pending_payloads, $now);
            $wrote = true;
        }

        if ($this->flush_enqueue_event_buffer($handle, $lines, $path, $pending_ids, $pending_payloads, $now)) {
            $wrote = true;
        }

        if ($wrote) {
            $this->finish_appended_event($handle);
        }
    }

    /**
     * @param list<StorageRecord> $records
     */
    private function append_claim_events(array $records, int $now): void
    {
        $path = $this->log_path();
        $handle = $this->log_handle();
        $lines = '';
        $pending = array();
        $wrote = false;

        fseek($handle, 0, SEEK_END);
        foreach ($records as $record) {
            $id = $record->id();
            $lines .= $this->encode_id_line('claim', $id, $now);
            $pending[] = array( $id, $now );

            if (strlen($lines) < 1_048_576) {
                continue;
            }

            $this->flush_claim_event_buffer($handle, $lines, $path, $pending);
            $wrote = true;
        }

        if ($this->flush_claim_event_buffer($handle, $lines, $path, $pending)) {
            $wrote = true;
        }

        if ($wrote) {
            $this->finish_appended_event($handle);
        }
    }

    /**
     * @param list<string> $ids
     */
    private function append_complete_events(array $ids, bool $keep_done, int &$completed, int $now): void
    {
        $seen = array();
        $path = $this->log_path();
        $handle = $this->log_handle();
        $lines = '';
        $pending = array();
        $wrote = false;

        fseek($handle, 0, SEEK_END);
        foreach ($ids as $id) {
            if (isset($seen[ $id ]) || ! isset($this->processing[ $id ])) {
                continue;
            }

            $seen[ $id ] = true;
            $completed++;
            $lines .= $this->encode_complete_line($id, $keep_done, $now);
            $pending[] = array( $id, $keep_done, $now );

            if (strlen($lines) < 1_048_576) {
                continue;
            }

            $this->flush_complete_event_buffer($handle, $lines, $path, $pending);
            $wrote = true;
        }

        if ($this->flush_complete_event_buffer($handle, $lines, $path, $pending)) {
            $wrote = true;
        }

        if ($wrote) {
            $this->finish_appended_event($handle);
        }
    }

    /**
     * @param resource $handle
     * @param list<string> $pending_ids
     * @param list<array<string, mixed>> $pending_payloads
     */
    private function flush_enqueue_event_buffer(
        mixed $handle,
        string &$lines,
        string $path,
        array &$pending_ids,
        array &$pending_payloads,
        int $now
    ): bool {
        if ('' === $lines) {
            return false;
        }

        AtomicFilesystem::write_all($handle, $lines, $path);
        foreach ($pending_ids as $index => $id) {
            $this->apply_enqueue_event($id, $pending_payloads[ $index ], $now);
        }

        $lines = '';
        $pending_ids = array();
        $pending_payloads = array();

        return true;
    }

    /**
     * @param resource $handle
     * @param list<array{0: string, 1: int}> $pending
     */
    private function flush_claim_event_buffer(mixed $handle, string &$lines, string $path, array &$pending): bool
    {
        if ('' === $lines) {
            return false;
        }

        AtomicFilesystem::write_all($handle, $lines, $path);
        foreach ($pending as $event) {
            $this->apply_claim_event($event[0], $event[1]);
        }

        $lines = '';
        $pending = array();

        return true;
    }

    /**
     * @param resource $handle
     * @param list<array{0: string, 1: bool, 2: int}> $pending
     */
    private function flush_complete_event_buffer(mixed $handle, string &$lines, string $path, array &$pending): bool
    {
        if ('' === $lines) {
            return false;
        }

        AtomicFilesystem::write_all($handle, $lines, $path);
        foreach ($pending as $event) {
            $this->apply_appended_complete_event($event[0], $event[1], $event[2]);
        }

        $lines = '';
        $pending = array();

        return true;
    }

    /**
     * @param list<string> $ids
     * @return \Generator<int, array<string, mixed>>
     */
    private function id_events(string $op, array $ids, int $now): \Generator
    {
        foreach ($ids as $id) {
            yield array(
                'op' => $op,
                'id' => $id,
                'ts' => $now,
            );
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function encode_line(array $event): string
    {
        $json = json_encode($event, self::JSON_FLAGS);

        return $this->frame_json($json);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode_enqueue_line(string $id, array $payload, int $now): string
    {
        $json = '{"op":"enqueue","id":"' . $id . '","payload":' . json_encode($payload, self::JSON_FLAGS) . ',"ts":' . $now . '}';

        return $this->frame_json($json);
    }

    private function encode_id_line(string $op, string $id, int $now): string
    {
        $json = '{"op":"' . $op . '","id":"' . $id . '","ts":' . $now . '}';

        return $this->frame_json($json);
    }

    private function encode_complete_line(string $id, bool $keep_done, int $now): string
    {
        $json = '{"op":"complete","id":"' . $id . '","done":' . ( $keep_done ? 'true' : 'false' ) . ',"ts":' . $now . '}';

        return $this->frame_json($json);
    }

    private function frame_json(string $json): string
    {
        return strlen($json) . "\t" . hash(self::LINE_HASH_ALGORITHM, $json) . "\t" . $json . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_line(string $line): array
    {
        if (! str_ends_with($line, "\n")) {
            throw new StorageException('Torn log queue line.');
        }

        $parts = explode("\t", rtrim($line, "\r\n"), 3);
        if (3 !== count($parts) || ! ctype_digit($parts[0])) {
            throw new StorageException('Malformed log queue line.');
        }

        $length = (int) $parts[0];
        $crc = $parts[1];
        $json = $parts[2];
        if ($length !== strlen($json) || $crc !== hash(self::LINE_HASH_ALGORITHM, $json)) {
            throw new StorageException('Corrupt log queue line.');
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ( array() !== $decoded && array_is_list($decoded) )) {
            throw new StorageException('Log queue event must be an object.');
        }

        foreach ($decoded as $key => $_value) {
            if (! is_string($key)) {
                throw new StorageException('Log queue event must be an object.');
            }
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function apply_event(array $event): void
    {
        $op = isset($event['op']) && is_string($event['op']) ? $event['op'] : '';
        $id = isset($event['id']) && is_string($event['id']) ? $event['id'] : '';
        if ('' === $op || ! UuidV7::is_valid($id)) {
            throw new StorageException('Invalid log queue event.');
        }

        $ts = isset($event['ts']) && is_int($event['ts']) ? $event['ts'] : time();

        if ('enqueue' === $op) {
            $this->apply_enqueue_event($id, $event['payload'] ?? array(), $ts);
            return;
        }

        if ('claim' === $op) {
            $this->apply_claim_event($id, $ts);
            return;
        }

        if ('complete' === $op) {
            $this->apply_complete_event($id, true === ( $event['done'] ?? false ), $ts);
            return;
        }

        if ('requeue' === $op) {
            if (isset($this->processing[ $id ])) {
                unset($this->processing[ $id ]);
                $this->processing_deletes++;
                $this->pending[ $id ] = true;
                $this->pending_order[] = $id;
                $this->compact_processing_map();
            }
            return;
        }

        if ('purge' === $op) {
            if (isset($this->done[ $id ])) {
                unset($this->done[ $id ]);
                $this->done_deletes++;
            }
            unset($this->payloads[ $id ]);
            $this->compact_done_map();
            return;
        }

        throw new StorageException('Unsupported log queue event: ' . $op);
    }

    /**
     * @return array{
     *     payloads: array<string, array<string, mixed>>,
     *     pending: array<string, true>,
     *     processing: array<string, int>,
     *     done: array<string, int>,
     *     pendingOrder: list<string>
     * }
     */
    private function empty_replayed_state(): array
    {
        return array(
            'payloads'     => array(),
            'pending'      => array(),
            'processing'   => array(),
            'done'         => array(),
            'pendingOrder' => array(),
        );
    }

    /**
     * @param array{
     *     payloads: array<string, array<string, mixed>>,
     *     pending: array<string, true>,
     *     processing: array<string, int>,
     *     done: array<string, int>,
     *     pendingOrder: list<string>
     * } $state
     * @param array<string, mixed> $event
     */
    private function apply_replayed_event(array &$state, array $event): void
    {
        $op = isset($event['op']) && is_string($event['op']) ? $event['op'] : '';
        $id = isset($event['id']) && is_string($event['id']) ? $event['id'] : '';
        if ('' === $op || ! UuidV7::is_valid($id)) {
            throw new StorageException('Invalid log queue event.');
        }

        $ts = isset($event['ts']) && is_int($event['ts']) ? $event['ts'] : time();

        if ('enqueue' === $op) {
            $state['payloads'][ $id ] = $this->payload_from_value($event['payload'] ?? array());
            $state['pending'][ $id ] = true;
            unset($state['processing'][ $id ], $state['done'][ $id ]);
            $state['pendingOrder'][] = $id;
            return;
        }

        if ('claim' === $op) {
            if (isset($state['pending'][ $id ])) {
                unset($state['pending'][ $id ]);
                $state['processing'][ $id ] = $ts;
            }
            return;
        }

        if ('complete' === $op) {
            unset($state['pending'][ $id ], $state['processing'][ $id ], $state['payloads'][ $id ]);
            if (true === ( $event['done'] ?? false )) {
                $state['done'][ $id ] = $ts;
            } else {
                unset($state['done'][ $id ]);
            }
            return;
        }

        if ('requeue' === $op) {
            if (isset($state['processing'][ $id ])) {
                unset($state['processing'][ $id ]);
                $state['pending'][ $id ] = true;
                $state['pendingOrder'][] = $id;
            }
            return;
        }

        if ('purge' === $op) {
            if (isset($state['done'][ $id ])) {
                unset($state['done'][ $id ]);
            }
            unset($state['payloads'][ $id ]);
            return;
        }

        throw new StorageException('Unsupported log queue event: ' . $op);
    }

    /**
     * @param array{
     *     payloads: array<string, array<string, mixed>>,
     *     pending: array<string, true>,
     *     processing: array<string, int>,
     *     done: array<string, int>,
     *     pendingOrder: list<string>
     * } $replayed
     */
    private function queue_state_matches_replay(array $replayed): bool
    {
        $payloads = $this->payloads;
        $pending = $this->pending;
        $processing = $this->processing;
        $done = $this->done;
        ksort($payloads);
        ksort($pending);
        ksort($processing);
        ksort($done);
        ksort($replayed['payloads']);
        ksort($replayed['pending']);
        ksort($replayed['processing']);
        ksort($replayed['done']);

        return $payloads === $replayed['payloads'] &&
            $pending === $replayed['pending'] &&
            $processing === $replayed['processing'] &&
            $done === $replayed['done'] &&
            $this->effective_pending_order($this->pending, $this->pending_order, $this->pending_offset) ===
                $this->effective_pending_order($replayed['pending'], $replayed['pendingOrder'], 0);
    }

    /**
     * @param array<string, true> $pending
     * @param list<string> $pending_order
     * @return list<string>
     */
    private function effective_pending_order(array $pending, array $pending_order, int $offset): array
    {
        $latest = array();
        foreach ($pending_order as $index => $id) {
            if (isset($pending[ $id ])) {
                $latest[ $id ] = $index;
            }
        }

        $order = array();
        $seen = array();
        $count = count($pending_order);
        for ($index = $offset; $index < $count; $index++) {
            $id = $pending_order[ $index ];
            if (! isset($pending[ $id ]) || isset($seen[ $id ]) || ( $latest[ $id ] ?? null ) !== $index) {
                continue;
            }

            $seen[ $id ] = true;
            $order[] = $id;
        }

        return $order;
    }

    private function apply_enqueue_event(string $id, mixed $payload, int $ts): void
    {
        $this->payloads[ $id ] = $this->payload_from_value($payload);
        $this->pending[ $id ] = true;
        unset($this->processing[ $id ], $this->done[ $id ]);
        $this->pending_order[] = $id;
    }

    private function apply_claim_event(string $id, int $ts): void
    {
        if (! isset($this->pending[ $id ])) {
            return;
        }

        unset($this->pending[ $id ]);
        $this->pending_deletes++;
        $this->processing[ $id ] = $ts;
        $this->compact_pending_map();
    }

    private function apply_complete_event(string $id, bool $keep_done, int $ts): void
    {
        $removed_pending = false;
        $removed_processing = false;
        $removed_done = false;

        if (isset($this->pending[ $id ])) {
            unset($this->pending[ $id ]);
            $this->pending_deletes++;
            $removed_pending = true;
        }

        if (isset($this->processing[ $id ])) {
            unset($this->processing[ $id ]);
            $this->processing_deletes++;
            $removed_processing = true;
        }

        unset($this->payloads[ $id ]);
        if ($keep_done) {
            $this->done[ $id ] = $ts;
        } elseif (isset($this->done[ $id ])) {
            unset($this->done[ $id ]);
            $this->done_deletes++;
            $removed_done = true;
        }

        if ($removed_pending) {
            $this->compact_pending_map();
        }
        if ($removed_processing) {
            $this->compact_processing_map();
        }
        if ($removed_done) {
            $this->compact_done_map();
        }
    }

    private function apply_appended_complete_event(string $id, bool $keep_done, int $ts): void
    {
        unset($this->processing[ $id ], $this->payloads[ $id ]);
        $this->processing_deletes++;

        if ($keep_done) {
            $this->done[ $id ] = $ts;
        }

        $this->compact_processing_map();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload_for_id(string $id): array
    {
        return $this->payloads[ $id ] ?? array();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload_from_value(mixed $value): array
    {
        if (! is_array($value)) {
            return array();
        }

        foreach ($value as $key => $_) {
            if (! is_string($key)) {
                $payload = array();
                foreach ($value as $inner_key => $field) {
                    if (is_string($inner_key)) {
                        $payload[ $inner_key ] = $field;
                    }
                }

                return $payload;
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function compact_pending_order(): void
    {
        if ($this->pending_offset < 4096 || $this->pending_offset * 2 < count($this->pending_order)) {
            return;
        }

        $this->pending_order  = array_slice($this->pending_order, $this->pending_offset);
        $this->pending_offset = 0;
    }

    private function compact_pending_map(): void
    {
        if ($this->pending_deletes < 4096 || $this->pending_deletes * 2 < count($this->pending) + $this->pending_deletes) {
            return;
        }

        $this->pending = array_slice($this->pending, 0, null, true);
        $this->pending_deletes = 0;
    }

    private function compact_processing_map(): void
    {
        if ($this->processing_deletes < 4096 || $this->processing_deletes * 2 < count($this->processing) + $this->processing_deletes) {
            return;
        }

        $this->processing = array_slice($this->processing, 0, null, true);
        $this->processing_deletes = 0;
    }

    private function compact_done_map(): void
    {
        if ($this->done_deletes < 4096 || $this->done_deletes * 2 < count($this->done) + $this->done_deletes) {
            return;
        }

        $this->done = array_slice($this->done, 0, null, true);
        $this->done_deletes = 0;
    }

    /**
     * @return array{pending: int, processing: int, done: int}
     */
    private function current_counts(): array
    {
        return array(
            'pending'    => count($this->pending),
            'processing' => count($this->processing),
            'done'       => count($this->done),
        );
    }

    /**
     * @return array{pending: int, processing: int, done: int, bytes: int}
     */
    private function stats_without_lock(): array
    {
        $counts = $this->current_counts();

        return array(
            'pending'    => $counts['pending'],
            'processing' => $counts['processing'],
            'done'       => $counts['done'],
            'bytes'      => is_file($this->log_path()) ? (int) filesize($this->log_path()) : 0,
        );
    }
}
