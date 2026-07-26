<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

final readonly class Feature
{
    /**
     * @param list<string> $details
     */
    public function __construct(
        public string $label,
        public array $details
    ) {}
}
