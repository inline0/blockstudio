<?php
/**
 * RPC method enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Rpc;

/**
 * Supported RPC HTTP methods.
 */
enum Method: string {
	case Get    = 'GET';
	case Post   = 'POST';
	case Put    = 'PUT';
	case Patch  = 'PATCH';
	case Delete = 'DELETE';
}
