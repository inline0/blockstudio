<?php

return [
    'subscribe' => function (array $params): array {
        return ['success' => true];
    },
    'configured' => [
        'callback' => function (array $params): array {
            return [];
        },
        'public' => true,
        'methods' => ['POST', 'GET'],
    ],
    'open_endpoint' => [
        'callback' => function (array $params): array {
            return [];
        },
        'public' => 'open',
        'methods' => ['POST'],
    ],
];
