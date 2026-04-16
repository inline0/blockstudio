<?php
/**
 * Backward compatibility alias for DB field definitions.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Db\Field::class, __NAMESPACE__ . '\Db_Field' );
