<?php

declare(strict_types=1);

// Bootstrap for the Blockstudio PHPStan extension.
// Loads stub class declarations so PHPStan can resolve Blockstudio\* references
// in user code without needing the real plugin to be installed.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Definition_Interface.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Field_Registry.php';
require_once __DIR__ . '/Build.php';
require_once __DIR__ . '/Db_Storage.php';
require_once __DIR__ . '/Db_Field.php';
require_once __DIR__ . '/Db_Schema.php';
require_once __DIR__ . '/Http_Method.php';
require_once __DIR__ . '/Rpc_Access.php';
require_once __DIR__ . '/Rpc_Definition.php';
require_once __DIR__ . '/Cron_Schedule.php';
require_once __DIR__ . '/Cron_Definition.php';
