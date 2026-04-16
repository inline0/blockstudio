<?php
/**
 * RPC access enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Rpc;

/**
 * Access modes for RPC definitions.
 */
enum Access: string {
	case Authenticated = 'authenticated';
	case Session       = 'session';
	case Open          = 'open';
}
