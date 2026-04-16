<?php
/**
 * Backward compatibility alias for RPC access.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Rpc\Access::class, __NAMESPACE__ . '\Rpc_Access' );
