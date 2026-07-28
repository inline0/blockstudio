<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\GitHooks;

final readonly class HookSyncResult
{
    public function __construct(
        public string $status,
        public string $hookPath
    ) {
    }
}
