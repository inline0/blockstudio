<?php

declare(strict_types=1);

use function PHPStan\Testing\assertType;

assertType('array{title: string, featured: bool, cta: array{href: string, title: string|null, target: string|null, opensInNewTab: bool|null}, items: array<int, array{label: string}>}', $a);
assertType('array{title: string, featured: bool, cta: array{href: string, title: string|null, target: string|null, opensInNewTab: bool|null}, items: array<int, array{label: string}>}', $attributes);
assertType('string', $a['title']);
assertType('bool', $a['featured']);
assertType('string', $a['cta']['href']);
assertType('string|null', $a['cta']['target']);
assertType('array<int, array{label: string}>', $a['items']);
assertType('array<string, mixed>', $b);
assertType('array<string, mixed>', $block);
assertType('array<string, mixed>', $c);
assertType('array<string, mixed>', $context);
assertType('string', $content);
assertType('string', $inner_blocks);
assertType('string', $islandPhase);
assertType('bool', $isEditor);
assertType('bool', $isPreview);
assertType('bool', $isIsland);
assertType('bool', $isIslandPlaceholder);
assertType('bool', $isIslandFragment);
