<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class QueryBuilder
{
    /** @var list<list<QueryCondition>> */
    private array $groups = array();

    private ?string $order_field = null;

    private string $order_direction = 'asc';

    private ?int $limit = null;

    private ?string $cursor = null;

    private bool $has_conditions = false;

    private bool $has_id_equality = false;

    public function __construct(private readonly FileStoreInterface $store)
    {
        $this->groups = array(array());
    }

    public function where(string $field): QueryFieldBuilder
    {
        return new QueryFieldBuilder($this, $field);
    }

    public function add_condition(QueryCondition $condition): self
    {
        $next = clone $this;
        foreach ($next->groups as $index => $group) {
            $next->groups[ $index ][] = $condition;
        }
        $next->has_conditions = true;
        if ('id' === $condition->field() && 'eq' === $condition->operator()) {
            $next->has_id_equality = true;
        }

        return $next;
    }

    public function andWhere(callable $callback): self
    {
        $branch = $callback(new self($this->store));
        if (! $branch instanceof self) {
            throw new StorageException('andWhere callback must return a QueryBuilder.');
        }

        $next = clone $this;
        foreach ($branch->flat_conditions() as $condition) {
            foreach ($next->groups as $index => $group) {
                $next->groups[ $index ][] = $condition;
            }
            if ('id' === $condition->field() && 'eq' === $condition->operator()) {
                $next->has_id_equality = true;
            }
        }
        $next->has_conditions = $next->has_conditions || $branch->has_conditions;

        return $next;
    }

    public function orWhere(callable $callback): self
    {
        $branch = $callback(new self($this->store));
        if (! $branch instanceof self) {
            throw new StorageException('orWhere callback must return a QueryBuilder.');
        }

        $next = clone $this;
        foreach ($branch->groups as $group) {
            $next->groups[] = $group;
        }
        $next->has_conditions = $next->has_conditions || $branch->has_conditions;
        $next->has_id_equality = $next->has_id_equality || $branch->has_id_equality;

        return $next;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);
        if (! in_array($direction, array( 'asc', 'desc' ), true)) {
            throw new StorageException('Query order direction must be asc or desc.');
        }

        $next                  = clone $this;
        $next->order_field     = $field;
        $next->order_direction = $direction;

        return $next;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new StorageException('Query limit must be at least 1.');
        }

        $next        = clone $this;
        $next->limit = $limit;

        return $next;
    }

    public function cursor(string $id): self
    {
        UuidV7::assert_valid($id);
        $next         = clone $this;
        $next->cursor = $id;

        return $next;
    }

    public function page(int $size): self
    {
        return $this->limit($size);
    }

    /**
     * @return list<StorageRecord>
     */
    public function get(): array
    {
        $records = $this->candidate_records();

        if (null !== $this->order_field && ! $this->candidate_records_are_ordered()) {
            usort(
                $records,
                function (StorageRecord $left, StorageRecord $right): int {
                    $result = QueryCondition::compare($this->order_value($left), $this->order_value($right));

                    return 'desc' === $this->order_direction ? -$result : $result;
                }
            );
        }

        if (null !== $this->limit) {
            $records = array_slice($records, 0, $this->limit);
        }

        return $records;
    }

    public function first(): ?StorageRecord
    {
        if ($this->has_id_equality) {
            $id_records = $this->direct_id_records();
            if (null !== $id_records) {
                return $id_records[0] ?? null;
            }
        }

        if (
            ! $this->has_conditions &&
            null === $this->cursor &&
            null === $this->order_field &&
            $this->store instanceof DocPerFileStore
        ) {
            $cached_record = $this->store->cached_first_record();
            if (false !== $cached_record) {
                return $cached_record;
            }
        }

        if (null === $this->order_field && ! $this->store instanceof DocPerFileStore) {
            $record_query = $this->record_query(1);
            if (null !== $record_query) {
                foreach ($this->store->stream($record_query) as $record) {
                    return $record;
                }

                return null;
            }

            foreach ($this->store->stream(null) as $record) {
                if ($this->matches($record)) {
                    return $record;
                }
            }

            return null;
        }

        $next = clone $this;
        $next->limit = 1;

        if (
            $this->store instanceof DocPerFileStore &&
            (
                null === $this->order_field ||
                $this->store->query_records_are_ordered($next)
            )
        ) {
            return $this->store->first_record($next);
        }

        return $next->get()[0] ?? null;
    }

    public function count(): int
    {
        if ($this->has_id_equality) {
            $id_records = $this->direct_id_records();
            if (null !== $id_records) {
                return count($id_records);
            }
        }

        if (
            ! $this->has_conditions &&
            null === $this->cursor &&
            $this->store instanceof DocPerFileStore
        ) {
            $cached_count = $this->store->cached_record_count($this->limit);
            if (null !== $cached_count) {
                return $cached_count;
            }
        }

        if ($this->store instanceof DocPerFileStore) {
            return $this->store->count_records($this);
        }

        if ($this->store instanceof SegmentedLogStore) {
            return $this->store->count_records($this);
        }

        if (! $this->has_conditions) {
            $count = 0;
            foreach ($this->store->stream(null) as $record) {
                if (null !== $this->cursor && strcmp($record->id(), $this->cursor) <= 0) {
                    continue;
                }

                $count++;
                if (null !== $this->limit && $count >= $this->limit) {
                    return $count;
                }
            }

            return $count;
        }

        $count = 0;
        foreach ($this->store->stream(null) as $record) {
            if (! $this->matches($record)) {
                continue;
            }

            $count++;
            if (null !== $this->limit && $count >= $this->limit) {
                return $count;
            }
        }

        return $count;
    }

    /**
     * @return array{store: string, plan: string, indexes: list<array<string, mixed>>, groups: int}
     */
    public function explain(): array
    {
        if ($this->store instanceof DocPerFileStore) {
            return $this->store->explain($this);
        }

        return array(
            'store'   => $this->store::class,
            'plan'    => 'full_scan',
            'indexes' => array(),
            'groups'  => count($this->groups),
        );
    }

    /**
     * @return list<list<QueryCondition>>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function has_conditions(): bool
    {
        return $this->has_conditions;
    }

    public function cursor_id(): ?string
    {
        return $this->cursor;
    }

    public function limit_value(): ?int
    {
        return $this->limit;
    }

    public function has_ordering(): bool
    {
        return null !== $this->order_field;
    }

    public function order_field(): ?string
    {
        return $this->order_field;
    }

    public function order_direction(): string
    {
        return $this->order_direction;
    }

    /**
     * @return array{field: string, value: mixed}|null
     */
    public function simple_equal_filter(): ?array
    {
        if (null !== $this->cursor || 1 !== count($this->groups) || 1 !== count($this->groups[0])) {
            return null;
        }

        $condition = $this->groups[0][0];
        if ('eq' !== $condition->operator()) {
            return null;
        }

        return array(
            'field' => $condition->field(),
            'value' => $condition->value(),
        );
    }

    public function matches(StorageRecord $record): bool
    {
        return $this->matches_data($record->id(), $record->data());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function matches_data(string $id, array $data): bool
    {
        if (null !== $this->cursor && strcmp($id, $this->cursor) <= 0) {
            return false;
        }

        foreach ($this->groups as $group) {
            $matches = true;
            foreach ($group as $condition) {
                if (! $condition->matches_data($id, $data)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<QueryCondition>
     */
    private function flat_conditions(): array
    {
        $conditions = array();
        foreach ($this->groups as $group) {
            foreach ($group as $condition) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * @return list<StorageRecord>
     */
    private function candidate_records(): array
    {
        if ($this->has_id_equality) {
            $id_records = $this->direct_id_records();
            if (null !== $id_records) {
                return $id_records;
            }
        }

        if ($this->store instanceof DocPerFileStore) {
            if (
                ! $this->has_conditions &&
                null === $this->cursor &&
                null === $this->order_field
            ) {
                $cached_records = $this->store->cached_records($this->limit);
                if (null !== $cached_records) {
                    return $cached_records;
                }
            }

            return $this->store->query_records($this);
        }

        $record_query = $this->record_query();
        if (null !== $record_query) {
            return iterator_to_array($this->store->stream($record_query), false);
        }

        if (! $this->has_conditions) {
            $records = array();
            $limit = $this->limit;
            $can_stop_early = null !== $limit && null === $this->order_field;
            $matched = 0;
            foreach ($this->store->stream(null) as $record) {
                if (null !== $this->cursor && strcmp($record->id(), $this->cursor) <= 0) {
                    continue;
                }

                $records[] = $record;
                $matched++;
                if ($can_stop_early && $matched >= $limit) {
                    break;
                }
            }

            return $records;
        }

        $records = array();
        $limit = $this->limit;
        $can_stop_early = null !== $limit && null === $this->order_field;
        $matched = 0;
        foreach ($this->store->stream(null) as $record) {
            if ($this->matches($record)) {
                $records[] = $record;
                $matched++;
                if ($can_stop_early && $matched >= $limit) {
                    break;
                }
            }
        }

        return $records;
    }

    private function candidate_records_are_ordered(): bool
    {
        return $this->store instanceof DocPerFileStore && $this->store->query_records_are_ordered($this);
    }

    /**
     * @return list<StorageRecord>|null
     */
    private function direct_id_records(): ?array
    {
        if (1 !== count($this->groups)) {
            return null;
        }

        $group = $this->groups[0];
        if (1 === count($group)) {
            $condition = $group[0];
            if ('id' !== $condition->field() || 'eq' !== $condition->operator()) {
                return null;
            }

            $id = $condition->value();
            if (! is_string($id) || ! UuidV7::is_valid($id)) {
                return array();
            }

            if (null !== $this->cursor && strcmp($id, $this->cursor) <= 0) {
                return array();
            }

            $record = $this->store->get($id);

            return null === $record ? array() : array($record);
        }

        $id = null;
        foreach ($group as $condition) {
            if ('id' !== $condition->field() || 'eq' !== $condition->operator()) {
                continue;
            }

            $candidate_id = $condition->value();
            if (! is_string($candidate_id) || ! UuidV7::is_valid($candidate_id)) {
                return array();
            }

            if (null !== $id && $id !== $candidate_id) {
                return array();
            }

            $id = $candidate_id;
        }

        if (null === $id) {
            return null;
        }

        if (null !== $this->cursor && strcmp($id, $this->cursor) <= 0) {
            return array();
        }

        $record = $this->store->get($id);
        if (null === $record || ! $this->matches($record)) {
            return array();
        }

        return array($record);
    }

    private function record_query(?int $limit_override = null): ?RecordQuery
    {
        if (null !== $this->order_field || 1 !== count($this->groups)) {
            return null;
        }

        $limit = $limit_override ?? $this->limit;

        /** @var array<string, scalar|null> $field_equals */
        $field_equals = array();
        foreach ($this->groups[0] as $condition) {
            if ('eq' !== $condition->operator() || 'id' === $condition->field()) {
                return null;
            }

            $value = $condition->value();
            if (null !== $value && ! is_scalar($value)) {
                return null;
            }

            $field = $condition->field();
            if (array_key_exists($field, $field_equals)) {
                if ($field_equals[ $field ] !== $value) {
                    return null;
                }

                continue;
            }

            $field_equals[ $field ] = $value;
        }

        return RecordQuery::from_query_builder($this->cursor, $limit, $field_equals);
    }

    private function order_value(StorageRecord $record): mixed
    {
        if ('id' === $this->order_field) {
            return $record->id();
        }

        $data = $record->data();

        return is_string($this->order_field) && array_key_exists($this->order_field, $data)
            ? $data[ $this->order_field ]
            : null;
    }
}
