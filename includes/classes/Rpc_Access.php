<?php
/**
 * RPC access enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Access modes for RPC definitions.
 */
enum Rpc_Access: string {
	case Authenticated = 'authenticated';
	case Session       = 'session';
	case Open          = 'open';
}
