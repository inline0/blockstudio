<?php

declare(strict_types=1);

use function PHPStan\Testing\assertType;

assertType('mixed', $a);
assertType('mixed', $attributes);
assertType('mixed', $b);
assertType('mixed', $content);
assertType('mixed', $isEditor);

$findings = bs_validate_block('theme/card', []);
assertType('list<array{code: string, path: string, source: string, message: string, value: mixed, expected: mixed}>|null', $findings);
if ($findings !== null) {
    foreach ($findings as $finding) {
        assertType('string', $finding['code']);
        assertType('mixed', $finding['value']);
    }
}

$schema = bs_block_schema('theme/card');
assertType('array<string, mixed>|null', $schema);

$values = bs_block_values('theme/card', []);
assertType('array<string, mixed>|null', $values);

assertType('bool', bs_is_editor());
assertType('bool', bs_is_preview());
assertType('bool', bs_is_buffering());
assertType('string', bs_strip_render_markers('<!--blockstudio/theme/card--><div></div>'));
