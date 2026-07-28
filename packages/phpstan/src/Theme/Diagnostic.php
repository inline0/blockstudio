<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

final readonly class Diagnostic
{
    public function __construct(
        public string $message,
        public string $identifier,
        public string $file,
        public int $line = 1
    ) {}
}
