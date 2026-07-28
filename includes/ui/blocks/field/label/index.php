<?php
echo bs_block(
	array(
		'name' => 'bsui/label',
		'data' => array( 'text' => (string) ( $a['text'] ?? '' ) ),
	)
);
