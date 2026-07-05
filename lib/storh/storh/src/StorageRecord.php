<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class StorageRecord
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
