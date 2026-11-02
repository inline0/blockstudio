<?php
return static function ( array $page ): array {
	return array(
		'heading' => 'Component Page Heading',
		'name'    => (string) ( $page['name'] ?? '' ),
	);
};
