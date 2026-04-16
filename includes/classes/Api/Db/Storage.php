<?php
/**
 * Database storage enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Db;

/**
 * Supported database storage backends.
 */
enum Storage: string {
	case Table    = 'table';
	case Sqlite   = 'sqlite';
	case Jsonc    = 'jsonc';
	case Meta     = 'meta';
	case PostType = 'post_type';
}
