<?php

add_filter('blockstudio/blocks/attributes/schema', static fn(array $schema, string $name): array => $schema, 10, 2);
add_filter('blockstudio/blocks/attributes/scheam', static fn(array $schema): array => $schema);
