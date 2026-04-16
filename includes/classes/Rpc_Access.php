<?php
/**
 * Backward compatibility alias for the public RPC access API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Rpc\Access::class, __NAMESPACE__ . '\Rpc_Access' );
