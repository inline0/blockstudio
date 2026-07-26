<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

final readonly class Surface
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        public string $label,
        public int $count,
        public array $directories
    ) {}
}
