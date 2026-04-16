<?php
/**
 * Backward compatibility alias for the public RPC method API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Rpc\Method::class, __NAMESPACE__ . '\Http_Method' );
