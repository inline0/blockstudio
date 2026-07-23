<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class Jsonc
{
    /**
     * @return array<string, mixed>
     */
    public static function decode_object(string $jsonc): array
    {
        $json = trim($jsonc);
        if (str_starts_with($json, '{') && str_ends_with($json, '}')) {
            try {
                return self::object_from_decoded(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
            } catch (\JsonException) {
                // Fall through for JSONC features such as comments and trailing commas.
            }
        }

        $json = trim(self::strip_jsonc($jsonc));
        if (! str_starts_with($json, '{') || ! str_ends_with($json, '}')) {
            throw new StorageException('JSONC document must decode to an object.');
        }

        return self::object_from_decoded(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function object_from_decoded(mixed $data): array
    {
        if (! is_array($data) || ( array() !== $data && array_is_list($data) )) {
            throw new StorageException('JSONC document must decode to an object.');
        }

        foreach ($data as $key => $_value) {
            if (! is_string($key)) {
                throw new StorageException('JSONC document must decode to an object.');
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encode_object(array $data): string
    {
        if (array() === $data) {
            return "{}\n";
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );

        return $json . "\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encode_compact_object(array $data): string
    {
        if (array() === $data) {
            return "{}\n";
        }

        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    public static function strip_jsonc(string $jsonc): string
    {
        return self::strip_trailing_commas(self::strip_comments($jsonc));
    }

    private static function strip_comments(string $source): string
    {
        $output    = '';
        $length    = strlen($source);
        $in_string = false;
        $escaped   = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $source[ $index ];
            $next = $source[ $index + 1 ] ?? '';

            if ($in_string) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $char) {
                    $escaped = true;
                } elseif ('"' === $char) {
                    $in_string = false;
                }
                continue;
            }

            if ('"' === $char) {
                $in_string = true;
                $output   .= $char;
                continue;
            }

            if ('/' === $char && '/' === $next) {
                while ($index < $length && ! in_array($source[ $index ], array( "\n", "\r" ), true)) {
                    $index++;
                }
                $output .= $source[ $index ] ?? '';
                continue;
            }

            if ('/' === $char && '*' === $next) {
                $index += 2;
                while ($index < $length - 1 && ! ( '*' === $source[ $index ] && '/' === $source[ $index + 1 ] )) {
                    $output .= in_array($source[ $index ], array( "\n", "\r" ), true) ? $source[ $index ] : ' ';
                    $index++;
                }
                $index++;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private static function strip_trailing_commas(string $source): string
    {
        $output    = '';
        $length    = strlen($source);
        $in_string = false;
        $escaped   = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $source[ $index ];

            if ($in_string) {
                $output .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $char) {
                    $escaped = true;
                } elseif ('"' === $char) {
                    $in_string = false;
                }
                continue;
            }

            if ('"' === $char) {
                $in_string = true;
                $output   .= $char;
                continue;
            }

            if (',' === $char) {
                $lookahead = $index + 1;
                while ($lookahead < $length && ctype_space($source[ $lookahead ])) {
                    $lookahead++;
                }

                if ($lookahead < $length && in_array($source[ $lookahead ], array( '}', ']' ), true)) {
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }
}
