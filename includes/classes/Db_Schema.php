<?php
/**
 * Backward compatibility alias for the public DB schema API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Db\Schema::class, __NAMESPACE__ . '\Db_Schema' );
