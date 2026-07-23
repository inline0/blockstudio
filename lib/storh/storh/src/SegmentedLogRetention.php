<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class SegmentedLogRetention
{
    private ?int $older_than_ms = null;

    public function __construct(private readonly SegmentedLogStore $store)
    {
    }

    public function olderThanDays(int $days): self
    {
        if ($days < 1) {
            throw new StorageException('Retention days must be at least 1.');
        }

        $next = clone $this;
        $next->older_than_ms = (int) floor(( time() - ( $days * 86400 ) ) * 1000);

        return $next;
    }

    public function olderThanMs(int $timestamp_ms): self
    {
        $next = clone $this;
        $next->older_than_ms = $timestamp_ms;

        return $next;
    }

    public function compact(): int
    {
        if (null === $this->older_than_ms) {
            throw new StorageException('Retention cutoff was not configured.');
        }

        $deleted = 0;
        foreach ($this->store->stream(RecordQuery::all()->time_range_ms(null, $this->older_than_ms)) as $record) {
            $this->store->delete($record->id());
            $deleted++;
        }

        $this->store->compact();

        return $deleted;
    }
}
