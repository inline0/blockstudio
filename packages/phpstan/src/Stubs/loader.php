<?php

declare(strict_types=1);

// Bootstrap for the Blockstudio PHPStan extension.
// Loads stub class declarations so PHPStan can resolve Blockstudio\* references
// in user code without needing the real plugin to be installed.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Field_Registry.php';
require_once __DIR__ . '/Build.php';
