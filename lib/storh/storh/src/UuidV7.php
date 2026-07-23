<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class UuidV7
{
    private static int $last_timestamp_ms = -1;

    private static string $last_timestamp_hex = '';

    private static string $last_timestamp_prefix = '';

    private static string $last_entropy = '';

    /** @var list<string> */
    private static array $byte_hex = array();

    public static function generate(?int $timestamp_ms = null): string
    {
        $timestamp_ms ??= self::now_ms();

        if ($timestamp_ms < 0 || $timestamp_ms > 0xffffffffffff) {
            throw new StorageException('UUIDv7 timestamp is outside the 48-bit range.');
        }

        if ($timestamp_ms === self::$last_timestamp_ms && '' !== self::$last_entropy) {
            if ('' === self::$last_timestamp_hex) {
                self::$last_timestamp_hex = self::timestamp_hex($timestamp_ms);
            }
            if ('' === self::$last_timestamp_prefix) {
                self::$last_timestamp_prefix = substr(self::$last_timestamp_hex, 0, 8) . '-' . substr(self::$last_timestamp_hex, 8, 4) . '-';
            }
            $timestamp_prefix = self::$last_timestamp_prefix;
            $entropy = self::$last_entropy;
            $last_byte = ord($entropy[9]);
            if ($last_byte < 255) {
                $entropy[9] = chr($last_byte + 1);
                self::$last_entropy = $entropy;
            } else {
                self::$last_entropy = self::increment_entropy($entropy);
            }
        } else {
            self::$last_timestamp_ms     = $timestamp_ms;
            self::$last_timestamp_hex    = self::timestamp_hex($timestamp_ms);
            $timestamp_prefix            = substr(self::$last_timestamp_hex, 0, 8) . '-' . substr(self::$last_timestamp_hex, 8, 4) . '-';
            self::$last_timestamp_prefix = $timestamp_prefix;
            self::$last_entropy          = random_bytes(10);
        }

        $entropy  = self::$last_entropy;
        if (array() === self::$byte_hex) {
            for ($value = 0; $value < 256; $value++) {
                self::$byte_hex[] = str_pad(dechex($value), 2, '0', STR_PAD_LEFT);
            }
        }
        $byte_hex = self::$byte_hex;

        return $timestamp_prefix
            . $byte_hex[ 0x70 | ( ord($entropy[0]) & 0x0f ) ] . $byte_hex[ ord($entropy[1]) ]
            . '-' . $byte_hex[ 0x80 | ( ord($entropy[2]) & 0x3f ) ] . $byte_hex[ ord($entropy[3]) ]
            . '-' . bin2hex(substr($entropy, 4, 6));
    }

    public static function is_valid(string $uuid): bool
    {
        return 1 === preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            $uuid
        );
    }

    public static function timestamp_ms(string $uuid): int
    {
        self::assert_valid($uuid);

        return (int) hexdec(substr($uuid, 0, 8) . substr($uuid, 9, 4));
    }

    public static function min_for_timestamp_ms(int $timestamp_ms): string
    {
        return self::format_hex(self::timestamp_hex($timestamp_ms) . '70008000000000000000');
    }

    public static function max_for_timestamp_ms(int $timestamp_ms): string
    {
        return self::format_hex(self::timestamp_hex($timestamp_ms) . '7fffbfffffffffffffff');
    }

    public static function assert_valid(string $uuid): void
    {
        if (! self::is_valid($uuid)) {
            throw new StorageException('Invalid UUIDv7 value.');
        }
    }

    public static function reset_for_tests(): void
    {
        self::$last_timestamp_ms  = -1;
        self::$last_timestamp_hex = '';
        self::$last_timestamp_prefix = '';
        self::$last_entropy       = '';
    }

    private static function now_ms(): int
    {
        return (int) ( microtime(true) * 1000 );
    }

    private static function timestamp_hex(int $timestamp_ms): string
    {
        return str_pad(dechex($timestamp_ms), 12, '0', STR_PAD_LEFT);
    }

    private static function increment_entropy(string $entropy): string
    {
        $byte = ord($entropy[9]);
        if ($byte < 255) {
            $entropy[9] = chr($byte + 1);
            return $entropy;
        }

        $entropy[9] = "\0";
        for ($index = 8; $index >= 0; $index--) {
            $byte = ord($entropy[ $index ]);
            if ($byte < 255) {
                $entropy[ $index ] = chr($byte + 1);
                return $entropy;
            }

            $entropy[ $index ] = "\0";
        }

        return $entropy;
    }

    private static function format_hex(string $hex): string
    {
        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4)
            . '-' . substr($hex, 20, 12);
    }
}
