<?php

use Blockstudio\Http_Method;
use Blockstudio\Rpc_Access;
use Blockstudio\Rpc_Definition as Rpc;

return new class {
	#[Rpc( access: Rpc_Access::Session )]
	public function greet( array $params ): array {
		$name = sanitize_text_field( $params['name'] ?? 'World' );
		return array( 'message' => 'Hello from PHP, ' . $name . '!' );
	}

	#[Rpc( access: Rpc_Access::Open )]
	public function webhook( array $params ): array {
		return array( 'open' => true, 'echo' => $params['value'] ?? null );
	}

	#[Rpc( capability: 'manage_options' )]
	public function admin_panel( array $params ): array {
		return array( 'admin' => true );
	}

	#[Rpc( name: 'get_status', methods: array( Http_Method::Get, Http_Method::Post ) )]
	public function getStatus( array $params ): array {
		return array( 'status' => 'ok' );
	}
};
