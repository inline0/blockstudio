<?php
/**
 * Backward compatibility alias for DB storage.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Db;

class_alias( \Blockstudio\Db\Storage::class, __NAMESPACE__ . '\Storage' );
