<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

interface FileStoreInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function put(array $data, ?string $id = null): StorageRecord;

    public function get(string $id): ?StorageRecord;

    public function delete(string $id): void;

    /**
     * @return \Generator<int, StorageRecord>
     */
    public function stream(?RecordQuery $query = null): \Generator;
}
