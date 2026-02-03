<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'BlockstudioVendor',

    'finders' => [
        Finder::create()
            ->files()
            ->in('vendor/scssphp'),
        Finder::create()
            ->files()
            ->in('vendor/matthiasmullie'),
        Finder::create()
            ->files()
            ->in('vendor/league'),
        Finder::create()
            ->files()
            ->in('vendor/symfony'),
        Finder::create()
            ->files()
            ->in('vendor/tailwindphp'),
    ],

    'patchers' => [
        function (string $filePath, string $prefix, string $content): string {
            $content = str_replace(
                'implements \\ScssPhp\\ScssPhp\\',
                'implements \\' . $prefix . '\\ScssPhp\\ScssPhp\\',
                $content
            );

            return $content;
        },
    ],
];
