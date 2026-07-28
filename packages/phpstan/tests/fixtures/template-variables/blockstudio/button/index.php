<?php

$href = trim( $a['cta']['href'] ?? '' );
$label = strtolower( $a['label'] ?? '' );

if ( $a['featured'] ?? false ) {
	$label = strtoupper( $label );
}

foreach ( $a['items'] ?? array() as $item ) {
	$label .= trim( $item['label'] );
}

echo $href . $label . ( $content ?? '' ) . count( $b ?? array() );
