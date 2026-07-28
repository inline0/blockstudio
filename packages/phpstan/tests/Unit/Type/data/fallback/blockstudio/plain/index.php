<?php

declare(strict_types=1);

use function PHPStan\Testing\assertType;

assertType('array<string, mixed>', $a);
assertType('array<string, mixed>', $attributes);
assertType('mixed', $a['anything']);
assertType('string', $content);
