<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

final readonly class DocumentResult
{
    public function __construct(
        public string $status,
        public string $path,
        public string $contents
    ) {}
}
