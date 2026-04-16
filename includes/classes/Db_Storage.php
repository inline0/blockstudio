<?php
/**
 * Backward compatibility alias for the public DB storage API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Db\Storage::class, __NAMESPACE__ . '\Db_Storage' );
