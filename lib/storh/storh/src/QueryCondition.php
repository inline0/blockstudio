<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class QueryCondition
{
    public function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value = null,
        private readonly mixed $second_value = null
    ) {
        if ('' === trim($field)) {
            throw new StorageException('Query field cannot be empty.');
        }
    }

    public function field(): string
    {
        return $this->field;
    }

    public function operator(): string
    {
        return $this->operator;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function second_value(): mixed
    {
        return $this->second_value;
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
        if ('id' === $this->field) {
            $exists = true;
            $actual = $id;
        } else {
            $actual = $data[ $this->field ] ?? null;
            $exists = null !== $actual || array_key_exists($this->field, $data);
        }

        if ('eq' === $this->operator) {
            return $exists && $actual === $this->value;
        }

        if ('neq' === $this->operator) {
            return ! $exists || $actual !== $this->value;
        }

        return match ($this->operator) {
            'in' => $exists && is_array($this->value) && in_array($actual, $this->value, true),
            'notIn' => ! $exists || is_array($this->value) && ! in_array($actual, $this->value, true),
            'gt' => $exists && self::compare($actual, $this->value) > 0,
            'gte' => $exists && self::compare($actual, $this->value) >= 0,
            'lt' => $exists && self::compare($actual, $this->value) < 0,
            'lte' => $exists && self::compare($actual, $this->value) <= 0,
            'between' => $exists
                && self::compare($actual, $this->value) >= 0
                && self::compare($actual, $this->second_value) <= 0,
            'exists' => $exists,
            'missing' => ! $exists,
            'prefix' => $exists && is_string($actual) && is_string($this->value) && str_starts_with($actual, $this->value),
            default => throw new StorageException('Unsupported query operator: ' . $this->operator),
        };
    }

    public static function compare(mixed $left, mixed $right): int
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        if (is_string($left) && is_string($right)) {
            return strcmp($left, $right);
        }

        if (is_bool($left) && is_bool($right)) {
            return (int) $left <=> (int) $right;
        }

        if (null === $left && null === $right) {
            return 0;
        }

        return strcmp(Jsonc::encode_object(array( 'left' => $left )), Jsonc::encode_object(array( 'right' => $right )));
    }
}
