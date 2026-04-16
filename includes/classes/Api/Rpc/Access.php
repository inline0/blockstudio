<?php
/**
 * Backward compatibility alias for RPC access.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Rpc;

class_alias( \Blockstudio\Rpc\Access::class, __NAMESPACE__ . '\Access' );
