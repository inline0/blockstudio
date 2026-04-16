<?php

namespace Blockstudio;

final class Db_Field
{
    /**
     * @param array<int, mixed>|null $enum
     * @param array<string, mixed> $extra
     */
    public static function make(
        string $type,
        bool $required = false,
        mixed $default = null,
        ?array $enum = null,
        ?string $format = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @param array<int, mixed>|null $enum
     * @param array<string, mixed> $extra
     */
    public static function string(
        bool $required = false,
        mixed $default = null,
        ?array $enum = null,
        ?string $format = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @param array<string, mixed> $extra
     */
    public static function integer(
        bool $required = false,
        mixed $default = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @param array<string, mixed> $extra
     */
    public static function number(
        bool $required = false,
        mixed $default = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @param array<string, mixed> $extra
     */
    public static function boolean(
        bool $required = false,
        mixed $default = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @param array<string, mixed> $extra
     */
    public static function text(
        bool $required = false,
        mixed $default = null,
        $validate = null,
        array $extra = []
    ): self {}

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array {}
}
