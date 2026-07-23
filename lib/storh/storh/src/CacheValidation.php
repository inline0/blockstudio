<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class CacheValidation
{
    public const HASH = 'hash';
    public const STAT = 'stat';
    public const TRUST = 'trust';

    public static function assert_valid(string $mode): string
    {
        if (! in_array($mode, array( self::HASH, self::STAT, self::TRUST ), true)) {
            throw new StorageException('Unsupported cache validation mode: ' . $mode);
        }

        return $mode;
    }
}
