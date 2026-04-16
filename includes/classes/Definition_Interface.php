<?php
/**
 * Backward compatibility alias for the public API definition interface.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Definition::class, __NAMESPACE__ . '\Definition_Interface' );
