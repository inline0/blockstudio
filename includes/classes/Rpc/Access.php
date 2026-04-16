<?php
/**
 * RPC access enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Rpc;

/**
 * Access modes for RPC definitions.
 */
enum Access: string {
	case Authenticated = 'authenticated';
	case Session       = 'session';
	case Open          = 'open';
}
