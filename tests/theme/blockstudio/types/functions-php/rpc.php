<?php

use Blockstudio\Api\Attributes\Rpc;
use Blockstudio\Api\Rpc\Access;
use Blockstudio\Api\Rpc\Method;

return new class {
	#[Rpc( access: Access::Session )]
	public function greet( array $params ): array {
		$name = sanitize_text_field( $params['name'] ?? 'World' );
		return array( 'message' => 'Hello from PHP, ' . $name . '!' );
	}

	#[Rpc( access: Access::Open )]
	public function webhook( array $params ): array {
		return array( 'open' => true, 'echo' => $params['value'] ?? null );
	}

	#[Rpc( capability: 'manage_options' )]
	public function admin_panel( array $params ): array {
		return array( 'admin' => true );
	}

	#[Rpc( name: 'get_status', methods: array( Method::Get, Method::Post ) )]
	public function getStatus( array $params ): array {
		return array( 'status' => 'ok' );
	}
};
