<?php

return [
    'storage' => 'table',
    'fields' => [
        'email' => [
            'type' => 'string',
            'required' => true,
        ],
        'name' => [
            'type' => 'string',
        ],
        'plan' => [
            'type' => 'string',
            'required' => true,
        ],
        'count' => [
            'type' => 'number',
        ],
        'active' => [
            'type' => 'boolean',
            'required' => true,
        ],
    ],
];
