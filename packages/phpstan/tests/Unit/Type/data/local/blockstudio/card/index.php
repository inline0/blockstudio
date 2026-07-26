<?php

declare(strict_types=1);

use function PHPStan\Testing\assertType;

assertType('string', $content);
assertType('array{description: string, badges: string}', $a);

$content = 5;

assertType('5', $content);

foreach (array(1, 2) as $b) {
    assertType('1|2', $b);
}

$callback = static function (array $block): string {
    assertType('array', $block);

    return implode(',', $block);
};

assertType('string', $callback(array()));

$a['extra'] = 'value';

assertType("array{description: string, badges: string, extra: 'value'}", $a);
assertType("'value'", $a['extra']);
