<?php
/**
 * Backward compatibility alias for the public DB field API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Db\Field::class, __NAMESPACE__ . '\Db_Field' );
