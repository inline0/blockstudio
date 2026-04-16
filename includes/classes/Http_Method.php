<?php
/**
 * Backward compatibility alias for RPC methods.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Rpc\Method::class, __NAMESPACE__ . '\Http_Method' );
