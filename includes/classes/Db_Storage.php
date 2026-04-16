<?php
/**
 * Backward compatibility alias for DB storage.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Db\Storage::class, __NAMESPACE__ . '\Db_Storage' );
