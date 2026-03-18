<?php
/**
 * Benchmark: DOMDocument vs WP_HTML_Tag_Processor vs string scanner for block tag parsing.
 *
 * Run with: php tests/bench/tag-parser-bench.php
 * Or via WP-CLI: wp eval-file tests/bench/tag-parser-bench.php
 */

// Generate test document with N block tags.
function generate_test_document( int $count ): string {
	$blocks = array(
		'<bs:acme-hero title="Welcome to our site" subtitle="A longer subtitle with more text" count="42" />',
		'<bs:acme-card title="Card Title" description="Card description text here" variant="primary" />',
		'<block name="core/paragraph">This is a paragraph with some content inside it.</block>',
		'<block name="core/heading" level="2">This is a heading block</block>',
		'<bs:acme-section layout="wide"><bs:acme-card title="Nested" count="1" /><block name="core/paragraph">Nested paragraph</block></bs:acme-section>',
		'<block name="core/group"><block name="core/paragraph">Inside group</block><block name="core/heading" level="3">Group heading</block></block>',
		'<bs:acme-cta title="Get Started" url="https://example.com" variant="primary" html-class="featured" data-track="cta-click" />',
		'<block name="core/columns"><block name="core/column"><block name="core/paragraph">Left</block></block><block name="core/column"><block name="core/paragraph">Right</block></block></block>',
		'<bs:acme-badge label="New" color="green" />',
		'<block name="core/separator" />',
	);

	$doc = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$doc .= $blocks[ $i % count( $blocks ) ] . "\n";
	}

	return $doc;
}

// Parser 1: DOMDocument.
function parse_domdocument( string $html ): array {
	$results = array();
	$doc     = new DOMDocument();

	@$doc->loadHTML(
		'<div id="root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);

	$root = $doc->getElementById( 'root' );
	if ( ! $root ) {
		return $results;
	}

	foreach ( $root->childNodes as $node ) {
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			continue;
		}

		$tag_name = $node->tagName;
		$attrs    = array();

		if ( $node->hasAttributes() ) {
			foreach ( $node->attributes as $attr ) {
				$attrs[ $attr->name ] = $attr->value;
			}
		}

		$inner = '';
		foreach ( $node->childNodes as $child ) {
			$inner .= $doc->saveHTML( $child );
		}

		$results[] = array(
			'tag'     => $tag_name,
			'attrs'   => $attrs,
			'content' => $inner,
		);
	}

	return $results;
}

// Parser 2: WP_HTML_Tag_Processor (if available).
function parse_wp_html_tag_processor( string $html ): array {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return array();
	}

	$results   = array();
	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag() ) {
		$tag_name = strtolower( $processor->get_tag() );

		$attrs = array();
		$names = $processor->get_attribute_names_with_prefix( '' );
		if ( $names ) {
			foreach ( $names as $name ) {
				$attrs[ $name ] = $processor->get_attribute( $name );
			}
		}

		$results[] = array(
			'tag'   => $tag_name,
			'attrs' => $attrs,
		);
	}

	return $results;
}

// Parser 3: Lightweight string scanner (same approach as Block_Tags).
function parse_string_scanner( string $html ): array {
	$results = array();
	$offset  = 0;
	$len     = strlen( $html );

	while ( $offset < $len ) {
		// Find next tag.
		$bs_pos    = strpos( $html, '<bs:', $offset );
		$block_pos = strpos( $html, '<block ', $offset );

		if ( false === $bs_pos && false === $block_pos ) {
			break;
		}

		// Take whichever comes first.
		if ( false === $bs_pos ) {
			$pos = $block_pos;
		} elseif ( false === $block_pos ) {
			$pos = $bs_pos;
		} else {
			$pos = min( $bs_pos, $block_pos );
		}

		$is_bs = ( $pos === $bs_pos && false !== $bs_pos );

		// Find closing >.
		$gt_pos   = $pos;
		$in_quote = false;
		$quote_ch = '';

		while ( $gt_pos < $len ) {
			$ch = $html[ $gt_pos ];
			if ( $in_quote ) {
				if ( $ch === $quote_ch ) {
					$in_quote = false;
				}
			} elseif ( '"' === $ch || "'" === $ch ) {
				$in_quote = true;
				$quote_ch = $ch;
			} elseif ( '>' === $ch ) {
				break;
			}
			++$gt_pos;
		}

		if ( $gt_pos >= $len ) {
			break;
		}

		$is_self_closing = ( '/' === $html[ $gt_pos - 1 ] );

		// Extract tag content.
		if ( $is_bs ) {
			$tag_start = $pos + 4;
			$tag_end   = $tag_start;
			while ( $tag_end < $len && preg_match( '/[a-z0-9-]/', $html[ $tag_end ] ) ) {
				++$tag_end;
			}
			$tag_name    = substr( $html, $tag_start, $tag_end - $tag_start );
			$attr_string = trim( substr( $html, $tag_end, $gt_pos - $tag_end - ( $is_self_closing ? 1 : 0 ) ) );
		} else {
			$attr_start  = $pos + 6;
			$attr_string = trim( substr( $html, $attr_start, $gt_pos - $attr_start - ( $is_self_closing ? 1 : 0 ) ) );
			$tag_name    = 'block';
		}

		// Parse attributes with regex.
		$attrs   = array();
		$pattern = '/([a-zA-Z_][\w-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/';
		if ( preg_match_all( $pattern, $attr_string, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key   = $match[1];
				$value = $match[2] ?? ( $match[3] ?? ( $match[4] ?? true ) );
				if ( '' === $value && count( $match ) <= 2 ) {
					$value = true;
				}
				$attrs[ $key ] = $value;
			}
		}

		// Find inner content for paired tags.
		$inner = '';
		if ( ! $is_self_closing ) {
			if ( $is_bs ) {
				$close_tag = '</bs:' . $tag_name . '>';
			} else {
				$close_tag = '</block>';
			}
			$close_pos = strpos( $html, $close_tag, $gt_pos + 1 );
			if ( false !== $close_pos ) {
				$inner  = substr( $html, $gt_pos + 1, $close_pos - $gt_pos - 1 );
				$offset = $close_pos + strlen( $close_tag );
			} else {
				$offset = $gt_pos + 1;
			}
		} else {
			$offset = $gt_pos + 1;
		}

		$results[] = array(
			'tag'     => $tag_name,
			'attrs'   => $attrs,
			'content' => $inner,
		);
	}

	return $results;
}

// Run benchmark.
function bench( string $name, callable $fn, string $html, int $iterations ): array {
	// Warmup.
	for ( $i = 0; $i < 3; $i++ ) {
		$fn( $html );
	}

	$times = array();
	for ( $i = 0; $i < $iterations; $i++ ) {
		$start   = hrtime( true );
		$result  = $fn( $html );
		$end     = hrtime( true );
		$times[] = ( $end - $start ) / 1e6;
	}

	sort( $times );
	$count  = count( $times );
	$median = $times[ (int) ( $count / 2 ) ];
	$mean   = array_sum( $times ) / $count;
	$min    = $times[0];
	$max    = $times[ $count - 1 ];
	$p95    = $times[ (int) ( $count * 0.95 ) ];

	return array(
		'name'       => $name,
		'median_ms'  => round( $median, 3 ),
		'mean_ms'    => round( $mean, 3 ),
		'min_ms'     => round( $min, 3 ),
		'max_ms'     => round( $max, 3 ),
		'p95_ms'     => round( $p95, 3 ),
		'tags_found' => count( $result ),
	);
}

// Generate deeply nested document.
function generate_nested_document( int $depth, int $siblings_per_level ): string {
	if ( $depth <= 0 ) {
		return '<bs:acme-card title="Leaf" count="1" />';
	}

	$content = '';
	for ( $i = 0; $i < $siblings_per_level; $i++ ) {
		$inner = generate_nested_document( $depth - 1, $siblings_per_level );
		if ( $i % 2 === 0 ) {
			$content .= '<bs:acme-section layout="level-' . $depth . '">' . $inner . '</bs:acme-section>';
		} else {
			$content .= '<block name="core/group">' . $inner . '</block>';
		}
	}

	return $content;
}

// Main.
$sizes      = array( 10, 50, 100, 500, 1000 );
$iterations = 50;

echo "Block Tag Parser Benchmark\n";
echo str_repeat( '=', 80 ) . "\n";
echo "Iterations per test: $iterations\n\n";

foreach ( $sizes as $size ) {
	$html = generate_test_document( $size );
	$html_kb = round( strlen( $html ) / 1024, 1 );

	echo "Document: $size tags ({$html_kb} KB)\n";
	echo str_repeat( '-', 80 ) . "\n";

	$parsers = array(
		'DOMDocument'          => 'parse_domdocument',
		'String Scanner'       => 'parse_string_scanner',
	);

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$parsers['WP_HTML_Tag_Processor'] = 'parse_wp_html_tag_processor';
	}

	$results = array();
	foreach ( $parsers as $name => $fn ) {
		$results[] = bench( $name, $fn, $html, $iterations );
	}

	echo sprintf(
		"%-25s %10s %10s %10s %10s %10s %6s\n",
		'Parser',
		'Median',
		'Mean',
		'Min',
		'Max',
		'P95',
		'Tags'
	);

	foreach ( $results as $r ) {
		echo sprintf(
			"%-25s %9.3fms %9.3fms %9.3fms %9.3fms %9.3fms %6d\n",
			$r['name'],
			$r['median_ms'],
			$r['mean_ms'],
			$r['min_ms'],
			$r['max_ms'],
			$r['p95_ms'],
			$r['tags_found']
		);
	}

	if ( count( $results ) > 1 ) {
		$base = $results[0]['median_ms'];
		echo "\nRelative to DOMDocument:\n";
		foreach ( $results as $r ) {
			$ratio = $base > 0 ? round( $r['median_ms'] / $base, 2 ) : 0;
			echo sprintf( "  %-25s %sx\n", $r['name'], $ratio );
		}
	}

	echo "\n";
}

// Nested document benchmarks.
echo "\n" . str_repeat( '=', 80 ) . "\n";
echo "NESTED DOCUMENTS\n";
echo str_repeat( '=', 80 ) . "\n\n";

$nested_configs = array(
	array( 'depth' => 3, 'siblings' => 2, 'desc' => '3 deep, 2 siblings' ),
	array( 'depth' => 4, 'siblings' => 2, 'desc' => '4 deep, 2 siblings' ),
	array( 'depth' => 5, 'siblings' => 2, 'desc' => '5 deep, 2 siblings' ),
	array( 'depth' => 3, 'siblings' => 4, 'desc' => '3 deep, 4 siblings' ),
	array( 'depth' => 4, 'siblings' => 3, 'desc' => '4 deep, 3 siblings' ),
);

foreach ( $nested_configs as $config ) {
	$html    = generate_nested_document( $config['depth'], $config['siblings'] );
	$html_kb = round( strlen( $html ) / 1024, 1 );
	$tag_count = substr_count( $html, '<bs:' ) + substr_count( $html, '<block ' );

	echo "Nested: {$config['desc']} (~{$tag_count} tags, {$html_kb} KB)\n";
	echo str_repeat( '-', 80 ) . "\n";

	$parsers = array(
		'DOMDocument'    => 'parse_domdocument',
		'String Scanner' => 'parse_string_scanner',
	);

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$parsers['WP_HTML_Tag_Processor'] = 'parse_wp_html_tag_processor';
	}

	$results = array();
	foreach ( $parsers as $name => $fn ) {
		$results[] = bench( $name, $fn, $html, $iterations );
	}

	echo sprintf(
		"%-25s %10s %10s %10s %10s %10s %6s\n",
		'Parser',
		'Median',
		'Mean',
		'Min',
		'Max',
		'P95',
		'Tags'
	);

	foreach ( $results as $r ) {
		echo sprintf(
			"%-25s %9.3fms %9.3fms %9.3fms %9.3fms %9.3fms %6d\n",
			$r['name'],
			$r['median_ms'],
			$r['mean_ms'],
			$r['min_ms'],
			$r['max_ms'],
			$r['p95_ms'],
			$r['tags_found']
		);
	}

	if ( count( $results ) > 1 ) {
		$base = $results[0]['median_ms'];
		echo "\nRelative to DOMDocument:\n";
		foreach ( $results as $r ) {
			$ratio = $base > 0 ? round( $r['median_ms'] / $base, 2 ) : 0;
			echo sprintf( "  %-25s %sx\n", $r['name'], $ratio );
		}
	}

	echo "\n";
}
