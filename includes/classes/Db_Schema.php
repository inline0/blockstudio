<?php
/**
 * Backward compatibility alias for DB schemas.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Db\Schema::class, __NAMESPACE__ . '\Db_Schema' );
