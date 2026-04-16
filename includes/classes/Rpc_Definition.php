<?php
/**
 * RPC definition attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Marks a public method as a Blockstudio RPC endpoint.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
final readonly class Rpc_Definition {

	/**
	 * Constructor.
	 *
	 * @param string|null                    $name       Optional function name override.
	 * @param array<int, string|Http_Method> $methods    Allowed HTTP methods.
	 * @param Rpc_Access|bool|string|null    $access     Access mode.
	 * @param string|array<int, string>|null $capability Optional capability restriction.
	 */
	public function __construct(
		public ?string $name = null,
		public array $methods = array( Http_Method::Post ),
		public Rpc_Access|bool|string|null $access = Rpc_Access::Authenticated,
		public string|array|null $capability = null,
	) {
	}
}
