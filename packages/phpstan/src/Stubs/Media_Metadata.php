<?php

namespace Blockstudio;

final class Media_Metadata
{
    /** @return array<string, mixed> */
    public static function for_theme_root(string $theme_root): array {}

    /** @return array<string, mixed>|null */
    public static function theme_asset(string $theme_root, string $relative_path): ?array {}

    /** @return array<string, mixed>|null */
    public static function attachment(string $theme_root, int $attachment_id): ?array {}

    public static function reset(): void {}
}
