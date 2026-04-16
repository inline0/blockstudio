<?php
/**
 * Backward compatibility alias for the RPC attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Attributes\Rpc::class, __NAMESPACE__ . '\Rpc_Definition' );
