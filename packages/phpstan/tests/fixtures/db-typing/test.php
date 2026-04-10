<?php

declare(strict_types=1);

use Blockstudio\Db;

$db = Db::get('test/subscribers');
if ($db === null) {
    return;
}

$record = $db->create([
    'email' => 'a@b.com',
    'plan' => 'pro',
    'active' => true,
]);

if ($record instanceof \WP_Error) {
    return;
}

// Valid accesses
echo $record['id'];
echo $record['email'];
echo $record['name'];
echo $record['plan'];
echo $record['count'];
echo $record['active'];

// Invalid accesses (should error)
echo $record['typo'];
echo $record['nonexistent'];
