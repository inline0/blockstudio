<?php
/**
 * Backward compatibility alias for DB schemas.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Db;

class_alias( \Blockstudio\Db\Schema::class, __NAMESPACE__ . '\Schema' );
