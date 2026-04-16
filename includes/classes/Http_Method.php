<?php
/**
 * HTTP method enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Supported RPC HTTP methods.
 */
enum Http_Method: string {
	case Get    = 'GET';
	case Post   = 'POST';
	case Put    = 'PUT';
	case Patch  = 'PATCH';
	case Delete = 'DELETE';
}
