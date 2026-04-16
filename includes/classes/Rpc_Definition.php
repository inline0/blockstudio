<?php
/**
 * Backward compatibility alias for the public RPC attribute API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Attributes\Rpc::class, __NAMESPACE__ . '\Rpc_Definition' );
