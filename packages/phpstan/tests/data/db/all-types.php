<?php

return [
    'storage' => 'table',
    'fields' => [
        'name' => ['type' => 'string', 'required' => true],
        'count' => ['type' => 'number', 'required' => true],
        'active' => ['type' => 'boolean', 'required' => true],
        'metadata' => ['type' => 'array', 'required' => true],
        'options' => ['type' => 'object', 'required' => true],
        'optional_str' => ['type' => 'string'],
    ],
];
