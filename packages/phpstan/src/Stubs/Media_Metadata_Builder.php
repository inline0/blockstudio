<?php

namespace Blockstudio;

final class Media_Metadata_Builder
{
    /**
     * @return array{version:int,themeAssets:array<string, array<string, mixed>>,attachments:array<string, array<string, mixed>>}
     */
    public function build(string $theme_root, bool $include_library = true, ?string $prefix = null): array {}

    public function write(
        string $theme_root,
        bool $include_library = true,
        ?string $prefix = null,
        ?string $target_path = null
    ): string {}
}
