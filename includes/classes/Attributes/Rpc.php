<?php
/**
 * RPC attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Attributes;

use Blockstudio\Rpc\Access;
use Blockstudio\Rpc\Method;

/**
 * Marks a public method as a Blockstudio RPC endpoint.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
final readonly class Rpc {

	/**
	 * Constructor.
	 *
	 * @param string|null                    $name       Optional function name override.
	 * @param array<int, string|Method>      $methods    Allowed HTTP methods.
	 * @param Access|bool|string|null        $access     Access mode.
	 * @param string|array<int, string>|null $capability Optional capability restriction.
	 */
	public function __construct(
		public ?string $name = null,
		public array $methods = array( Method::Post ),
		public Access|bool|string|null $access = Access::Authenticated,
		public string|array|null $capability = null,
	) {
	}
}
