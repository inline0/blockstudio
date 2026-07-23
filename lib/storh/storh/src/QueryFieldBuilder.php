<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class QueryFieldBuilder
{
    public function __construct(
        private readonly QueryBuilder $query,
        private readonly string $field
    ) {
    }

    public function eq(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'eq', $value));
    }

    public function neq(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'neq', $value));
    }

    /**
     * @param list<mixed> $values
     */
    public function in(array $values): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'in', array_values($values)));
    }

    /**
     * @param list<mixed> $values
     */
    public function notIn(array $values): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'notIn', array_values($values)));
    }

    public function gt(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'gt', $value));
    }

    public function gte(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'gte', $value));
    }

    public function lt(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'lt', $value));
    }

    public function lte(mixed $value): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'lte', $value));
    }

    public function between(mixed $from, mixed $until): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'between', $from, $until));
    }

    public function exists(): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'exists'));
    }

    public function missing(): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'missing'));
    }

    public function prefix(string $prefix): QueryBuilder
    {
        return $this->query->add_condition(new QueryCondition($this->field, 'prefix', $prefix));
    }
}
