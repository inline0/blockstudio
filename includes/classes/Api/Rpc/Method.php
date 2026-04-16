<?php
/**
 * Backward compatibility alias for RPC methods.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Rpc;

class_alias( \Blockstudio\Rpc\Method::class, __NAMESPACE__ . '\Method' );
