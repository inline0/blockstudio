<?php

namespace Blockstudio;

final class Runtime_Settings
{
    public static function current(): self {}

    public static function reset(): void {}

    /** @return array<string, mixed> */
    public function all(): array {}

    /** @param mixed $default */
    public function value(string $path, $default = null): mixed {}

    public function enabled(string $path): bool {}

    /** @return list<string> */
    public function errors(): array {}

    public function hash(): string {}
}
