<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class RecordQuery
{
    private ?string $after_id = null;

    private ?int $from_ms = null;

    private ?int $until_ms = null;

    private ?string $lower_id = null;

    private ?string $upper_id = null;

    private ?int $limit = null;

    /** @var array<string, scalar|null> */
    private array $field_equals = array();

    /** @var null|callable(string, \Throwable): void */
    private mixed $error_handler = null;

    /** @var null|callable(string): void */
    private mixed $segment_open_handler = null;

    public static function all(): self
    {
        return new self();
    }

    /**
     * @internal
     * @param array<string, scalar|null> $field_equals
     */
    public static function from_query_builder(?string $after_id, ?int $limit, array $field_equals): self
    {
        $query               = new self();
        $query->after_id     = $after_id;
        $query->limit        = $limit;
        $query->field_equals = $field_equals;

        return $query;
    }

    public function after(string $id): self
    {
        UuidV7::assert_valid($id);
        $next           = clone $this;
        $next->after_id = $id;

        return $next;
    }

    public function time_range_ms(?int $from_ms, ?int $until_ms): self
    {
        $next           = clone $this;
        $next->from_ms  = $from_ms;
        $next->until_ms = $until_ms;
        $next->lower_id = null === $from_ms ? null : UuidV7::min_for_timestamp_ms($from_ms);
        $next->upper_id = null === $until_ms ? null : UuidV7::max_for_timestamp_ms($until_ms);

        return $next;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new StorageException('Record query limit must be at least 1.');
        }

        $next        = clone $this;
        $next->limit = $limit;

        return $next;
    }

    public function where_equal(string $field, mixed $value): self
    {
        if ('' === trim($field)) {
            throw new StorageException('Record query field cannot be empty.');
        }

        if (null !== $value && ! is_scalar($value)) {
            throw new StorageException('Record query value must be scalar or null.');
        }

        $next                         = clone $this;
        $next->field_equals[ $field ] = $value;

        return $next;
    }

    public function continue_on_error(callable $handler): self
    {
        $next                = clone $this;
        $next->error_handler = $handler;

        return $next;
    }

    public function on_segment_open(callable $handler): self
    {
        $next                       = clone $this;
        $next->segment_open_handler = $handler;

        return $next;
    }

    public function after_id(): ?string
    {
        return $this->after_id;
    }

    public function lower_id(): ?string
    {
        return $this->lower_id;
    }

    public function upper_id(): ?string
    {
        return $this->upper_id;
    }

    public function limit_value(): ?int
    {
        return $this->limit;
    }

    public function filters_records(): bool
    {
        return null !== $this->after_id ||
            null !== $this->from_ms ||
            null !== $this->until_ms ||
            array() !== $this->field_equals;
    }

    public function filters_data(): bool
    {
        return array() !== $this->field_equals;
    }

    public function matches(StorageRecord $record): bool
    {
        return $this->matches_data($record->id(), $record->data());
    }

    public function matches_id(string $id): bool
    {
        if (null !== $this->after_id && $id <= $this->after_id) {
            return false;
        }

        if (null !== $this->lower_id && $id < $this->lower_id) {
            return false;
        }

        if (null !== $this->upper_id && $id > $this->upper_id) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function matches_data(string $id, array $data): bool
    {
        if (null !== $this->after_id && $id <= $this->after_id) {
            return false;
        }

        if (null !== $this->lower_id && $id < $this->lower_id) {
            return false;
        }

        if (null !== $this->upper_id && $id > $this->upper_id) {
            return false;
        }

        foreach ($this->field_equals as $field => $value) {
            $actual = $data[ $field ] ?? null;
            $exists = null !== $actual || array_key_exists($field, $data);
            if (! $exists || $actual !== $value) {
                return false;
            }
        }

        return true;
    }

    public function handle_error(string $id, \Throwable $throwable): bool
    {
        if (null === $this->error_handler) {
            return false;
        }

        ( $this->error_handler )($id, $throwable);

        return true;
    }

    public function notify_segment_open(string $segment): void
    {
        if (null !== $this->segment_open_handler) {
            ( $this->segment_open_handler )($segment);
        }
    }
}
