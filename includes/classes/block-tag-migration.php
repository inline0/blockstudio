<?php
/**
 * Generic block-tag migration utility.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use InvalidArgumentException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal CLI diagnostics, not HTML output.

/**
 * Rewrites caller-defined legacy prefix tags to canonical <bs:> tags.
 *
 * The utility is deliberately independent from WordPress and from any host
 * product. Callers provide the prefix map, known block names, and optional
 * aliases that describe their own legacy authoring surface.
 *
 * @since 7.6.0
 */
class Block_Tag_Migration {

	/**
	 * Analyze and optionally rewrite one source string.
	 *
	 * @param string                         $source       Source contents.
	 * @param string                         $path         Display path used in diagnostics.
	 * @param array<string,string|array>     $prefix_map   Prefix => ordered namespaces.
	 * @param array<int|string,string|array> $known_blocks Registered block names.
	 * @param array<string,string|array>     $aliases      Optional exact legacy tag aliases.
	 *
	 * @return array{
	 *   source:string,
	 *   changed:bool,
	 *   replacements:array<int,array<string,mixed>>,
	 *   unknown:array<int,array<string,mixed>>,
	 *   ambiguous:array<int,array<string,mixed>>,
	 *   manual:array<int,array<string,mixed>>
	 * }
	 */
	public static function migrate_source(
		string $source,
		string $path,
		array $prefix_map,
		array $known_blocks,
		array $aliases = array()
	): array {
		$prefixes = self::normalize_prefix_map( $prefix_map );
		$blocks   = self::normalize_known_blocks( $known_blocks );
		$aliases  = self::normalize_aliases( $aliases );
		$pattern  = self::legacy_tag_pattern( $prefixes, $aliases );
		$edits    = array();
		$report   = array(
			'replacements' => array(),
			'unknown'      => array(),
			'ambiguous'    => array(),
			'manual'       => array(),
		);

		if ( self::is_php_path( $path ) ) {
			self::scan_php_source( $source, $path, $prefixes, $blocks, $aliases, $pattern, $edits, $report );
		} else {
			self::scan_segment(
				$source,
				$source,
				$path,
				0,
				$prefixes,
				$blocks,
				$aliases,
				$pattern,
				false,
				$edits,
				$report
			);
		}

		usort(
			$edits,
			static fn( array $left, array $right ): int => $right['offset'] <=> $left['offset']
		);

		$rewritten = $source;
		foreach ( $edits as $edit ) {
			$rewritten = substr_replace(
				$rewritten,
				$edit['replacement'],
				$edit['offset'],
				$edit['length']
			);
		}

		foreach ( array_keys( $report ) as $key ) {
			usort(
				$report[ $key ],
				static function ( array $left, array $right ): int {
					$path_compare = strcmp( (string) $left['path'], (string) $right['path'] );
					if ( 0 !== $path_compare ) {
						return $path_compare;
					}

					$line_compare = (int) $left['line'] <=> (int) $right['line'];
					if ( 0 !== $line_compare ) {
						return $line_compare;
					}

					return (int) $left['column'] <=> (int) $right['column'];
				}
			);
		}

		return array(
			'source'       => $rewritten,
			'changed'      => $rewritten !== $source,
			'replacements' => $report['replacements'],
			'unknown'      => $report['unknown'],
			'ambiguous'    => $report['ambiguous'],
			'manual'       => $report['manual'],
		);
	}

	/**
	 * Return the canonical authoring tag for a block name.
	 *
	 * @param string $block_name Registered block name.
	 *
	 * @return string Canonical tag name without angle brackets.
	 *
	 * @throws InvalidArgumentException When the block name is malformed.
	 */
	public static function canonical_tag( string $block_name ): string {
		$block_name = strtolower( trim( $block_name ) );

		if ( ! preg_match( '#^[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*$#', $block_name ) ) {
			throw new InvalidArgumentException( "Invalid block name: {$block_name}" );
		}

		return 'bs:' . str_replace( '/', '-', $block_name );
	}

	/**
	 * Scan PHP tokens while preserving every byte outside tag-name edits.
	 *
	 * @param string                          $source   Full source.
	 * @param string                          $path     Display path.
	 * @param array<string,array<int,string>> $prefixes Normalized prefixes.
	 * @param array<string,true>              $blocks   Known blocks.
	 * @param array<string,array<int,string>> $aliases  Normalized aliases.
	 * @param string                          $pattern  Targeted legacy-tag pattern.
	 * @param array<int,array<string,mixed>>  $edits    Collected edits.
	 * @param array<string,array<int,array>>  $report   Collected diagnostics.
	 *
	 * @return void
	 */
	private static function scan_php_source(
		string $source,
		string $path,
		array $prefixes,
		array $blocks,
		array $aliases,
		string $pattern,
		array &$edits,
		array &$report
	): void {
		$offset = 0;

		foreach ( token_get_all( $source ) as $token ) {
			$text       = is_array( $token ) ? $token[1] : $token;
			$token_id   = is_array( $token ) ? $token[0] : null;
			$is_markup  = in_array(
				$token_id,
				array( T_INLINE_HTML, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ),
				true
			);
			$is_comment = in_array( $token_id, array( T_COMMENT, T_DOC_COMMENT ), true );

			if ( $is_markup || $is_comment ) {
				self::scan_segment(
					$source,
					$text,
					$path,
					$offset,
					$prefixes,
					$blocks,
					$aliases,
					$pattern,
					$is_comment,
					$edits,
					$report
				);
			}

			$offset += strlen( $text );
		}
	}

	/**
	 * Scan one markup-bearing segment.
	 *
	 * @param string                          $source          Full source.
	 * @param string                          $segment         Segment contents.
	 * @param string                          $path            Display path.
	 * @param int                             $base_offset     Segment offset in source.
	 * @param array<string,array<int,string>> $prefixes        Normalized prefixes.
	 * @param array<string,true>              $blocks          Known blocks.
	 * @param array<string,array<int,string>> $aliases         Normalized aliases.
	 * @param string                          $pattern         Targeted legacy-tag pattern.
	 * @param bool                            $force_manual    Whether all matches are manual.
	 * @param array<int,array<string,mixed>>  $edits           Collected edits.
	 * @param array<string,array<int,array>>  $report          Collected diagnostics.
	 *
	 * @return void
	 */
	private static function scan_segment(
		string $source,
		string $segment,
		string $path,
		int $base_offset,
		array $prefixes,
		array $blocks,
		array $aliases,
		string $pattern,
		bool $force_manual,
		array &$edits,
		array &$report
	): void {
		preg_match_all(
			$pattern,
			$segment,
			$matches,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		);

		$dynamic_matches = array();
		if ( str_contains( $segment, '<' ) && str_contains( $segment, '$' ) ) {
			preg_match_all(
				'#<\s*/?\s*(?:\{\s*\$[a-z_][a-z0-9_]*\s*\}|\$\{\s*[a-z_][a-z0-9_.]*\s*\}|\$[a-z_][a-z0-9_]*)(?:\s+[a-z_:][a-z0-9_:.-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*/?>#i',
				$segment,
				$dynamic_matches,
				PREG_OFFSET_CAPTURE
			);
		}

		if ( array() === $matches && empty( $dynamic_matches[0] ) ) {
			return;
		}

		$protected = $force_manual
			? array( array( 0, strlen( $segment ), 'comment' ) )
			: self::protected_ranges( $segment );

		foreach ( $matches as $match ) {
			$tag          = strtolower( $match['tag'][0] );
			$tag_offset   = (int) $match['tag'][1];
			$full_offset  = $base_offset + $tag_offset;
			$legacy_match = self::is_legacy_tag( $tag, $prefixes, $aliases );

			if ( ! $legacy_match ) {
				continue;
			}

			$location         = self::location( $source, $path, $full_offset );
			$protected_reason = self::protected_reason( $tag_offset, $protected );

			if ( null !== $protected_reason ) {
				$report['manual'][] = array_merge(
					$location,
					array(
						'tag'    => $tag,
						'reason' => $protected_reason,
					)
				);
				continue;
			}

			$resolution = self::resolve_tag( $tag, $prefixes, $blocks, $aliases );

			if ( 'unknown' === $resolution['status'] ) {
				$report['unknown'][] = array_merge(
					$location,
					array(
						'tag'    => $tag,
						'reason' => $resolution['reason'],
					)
				);
				continue;
			}

			if ( 'ambiguous' === $resolution['status'] ) {
				$report['ambiguous'][] = array_merge(
					$location,
					array(
						'tag'        => $tag,
						'candidates' => $resolution['candidates'],
						'reason'     => $resolution['reason'],
					)
				);
				continue;
			}

			$canonical = self::canonical_tag( $resolution['block'] );
			$edits[]   = array(
				'offset'      => $full_offset,
				'length'      => strlen( $match['tag'][0] ),
				'replacement' => $canonical,
			);

			$report['replacements'][] = array_merge(
				$location,
				array(
					'tag'       => $tag,
					'canonical' => $canonical,
					'block'     => $resolution['block'],
					'closing'   => '' !== $match['closing'][0],
					'via'       => $resolution['via'],
				)
			);
		}

		foreach ( $dynamic_matches[0] ?? array() as $dynamic_match ) {
			$local_offset       = (int) $dynamic_match[1];
			$report['manual'][] = array_merge(
				self::location( $source, $path, $base_offset + $local_offset ),
				array(
					'tag'    => trim( $dynamic_match[0] ),
					'reason' => 'dynamic-tag',
				)
			);
		}
	}

	/**
	 * Build a pattern that can only collect caller-owned legacy tag names.
	 *
	 * This keeps scans of large source or fixture files proportional to the
	 * number of migration candidates instead of every unrelated HTML tag.
	 *
	 * @param array<string,array<int,string>> $prefixes Prefix map.
	 * @param array<string,array<int,string>> $aliases  Alias map.
	 *
	 * @return string Regular expression.
	 */
	private static function legacy_tag_pattern( array $prefixes, array $aliases ): string {
		$tag_patterns = array();

		foreach ( array_keys( $prefixes ) as $prefix ) {
			$tag_patterns[] = preg_quote( $prefix, '#' ) . '-[a-z0-9-]+';
		}

		foreach ( array_keys( $aliases ) as $alias ) {
			$tag_patterns[] = preg_quote( $alias, '#' );
		}

		if ( array() === $tag_patterns ) {
			return '#(?!)#';
		}

		usort(
			$tag_patterns,
			static fn( string $left, string $right ): int => strlen( $right ) <=> strlen( $left )
		);

		return '#<(?P<closing>/)?(?P<tag>(?:' . implode( '|', $tag_patterns ) . '))(?=[\s/>])#i';
	}

	/**
	 * Resolve one legacy tag using runtime-equivalent ordered prefix rules.
	 *
	 * @param string                          $tag      Legacy tag.
	 * @param array<string,array<int,string>> $prefixes Prefix map.
	 * @param array<string,true>              $blocks   Known blocks.
	 * @param array<string,array<int,string>> $aliases  Alias map.
	 * @param array<string,true>              $seen     Recursion guard.
	 *
	 * @return array<string,mixed> Resolution.
	 */
	private static function resolve_tag(
		string $tag,
		array $prefixes,
		array $blocks,
		array $aliases,
		array $seen = array()
	): array {
		if ( isset( $seen[ $tag ] ) ) {
			return array(
				'status' => 'unknown',
				'reason' => 'recursive-prefix',
			);
		}

		$seen[ $tag ] = true;

		if ( isset( $aliases[ $tag ] ) ) {
			$candidates = array_values( array_unique( $aliases[ $tag ] ) );

			if ( 1 !== count( $candidates ) ) {
				return array(
					'status'     => 'ambiguous',
					'candidates' => $candidates,
					'reason'     => 'alias-has-multiple-targets',
				);
			}

			return array(
				'status' => 'resolved',
				'block'  => $candidates[0],
				'via'    => 'alias',
			);
		}

		$hyphen = strpos( $tag, '-' );
		if ( false === $hyphen ) {
			return array(
				'status' => 'unknown',
				'reason' => 'missing-prefix-separator',
			);
		}

		$prefix = substr( $tag, 0, $hyphen );
		$slug   = substr( $tag, $hyphen + 1 );

		if ( '' === $slug || ! isset( $prefixes[ $prefix ] ) ) {
			return array(
				'status' => 'unknown',
				'reason' => 'unregistered-prefix',
			);
		}

		foreach ( $prefixes[ $prefix ] as $namespace ) {
			$candidate = $namespace . '/' . $slug;
			if ( isset( $blocks[ $candidate ] ) ) {
				return array(
					'status' => 'resolved',
					'block'  => $candidate,
					'via'    => 'prefix:' . $prefix,
				);
			}
		}

		$nested_hyphen = strpos( $slug, '-' );
		if ( false !== $nested_hyphen ) {
			$nested_prefix = substr( $slug, 0, $nested_hyphen );
			if ( $nested_prefix !== $prefix && isset( $prefixes[ $nested_prefix ] ) ) {
				$nested = self::resolve_tag( $slug, $prefixes, $blocks, $aliases, $seen );
				if ( 'resolved' === $nested['status'] ) {
					$nested['via'] = 'nested-prefix:' . $prefix . '>' . $nested['via'];
				}
				return $nested;
			}
		}

		return array(
			'status' => 'unknown',
			'reason' => 'no-known-block',
		);
	}

	/**
	 * Find source ranges that should be reported but never rewritten.
	 *
	 * @param string $segment Segment contents.
	 *
	 * @return array<int,array{0:int,1:int,2:string}> Ranges.
	 */
	private static function protected_ranges( string $segment ): array {
		$ranges   = array();
		$patterns = array(
			'html-comment' => '#<!--.*?-->#s',
			'code-sample'  => '#<(pre|code|script|style)\b[^>]*>.*?</\1\s*>#is',
			'inline-code'  => '#(?<!`)(`{1,2})[^`\r\n]*\1(?!`)#',
			'code-fence'   => '#^[ \t]*(`{3,}|~{3,})[^\r\n]*\R.*?^[ \t]*\1[ \t]*$#ms',
		);

		foreach ( $patterns as $reason => $pattern ) {
			preg_match_all( $pattern, $segment, $matches, PREG_OFFSET_CAPTURE );
			foreach ( $matches[0] ?? array() as $match ) {
				$ranges[] = array( (int) $match[1], strlen( $match[0] ), $reason );
			}
		}

		return $ranges;
	}

	/**
	 * Return the protected reason at one offset.
	 *
	 * @param int                                    $offset Offset.
	 * @param array<int,array{0:int,1:int,2:string}> $ranges Protected ranges.
	 *
	 * @return string|null Reason.
	 */
	private static function protected_reason( int $offset, array $ranges ): ?string {
		foreach ( $ranges as $range ) {
			if ( $offset >= $range[0] && $offset < $range[0] + $range[1] ) {
				return $range[2];
			}
		}

		return null;
	}

	/**
	 * Calculate a deterministic source location.
	 *
	 * @param string $source Source.
	 * @param string $path   Display path.
	 * @param int    $offset Byte offset.
	 *
	 * @return array{path:string,line:int,column:int} Location.
	 */
	private static function location( string $source, string $path, int $offset ): array {
		$before       = substr( $source, 0, $offset );
		$line         = substr_count( $before, "\n" ) + 1;
		$last_newline = strrpos( $before, "\n" );
		$column       = false === $last_newline ? $offset + 1 : $offset - $last_newline;

		return array(
			'path'   => $path,
			'line'   => $line,
			'column' => $column,
		);
	}

	/**
	 * Whether a tag belongs to the caller-defined legacy surface.
	 *
	 * @param string                          $tag      Tag.
	 * @param array<string,array<int,string>> $prefixes Prefix map.
	 * @param array<string,array<int,string>> $aliases  Alias map.
	 *
	 * @return bool Whether the tag should be considered.
	 */
	private static function is_legacy_tag( string $tag, array $prefixes, array $aliases ): bool {
		if ( isset( $aliases[ $tag ] ) ) {
			return true;
		}

		$hyphen = strpos( $tag, '-' );
		return false !== $hyphen && isset( $prefixes[ substr( $tag, 0, $hyphen ) ] );
	}

	/**
	 * Normalize the prefix map.
	 *
	 * @param array $prefix_map Raw prefix map.
	 *
	 * @return array<string,array<int,string>> Normalized map.
	 *
	 * @throws InvalidArgumentException When a prefix or namespace is malformed.
	 */
	private static function normalize_prefix_map( array $prefix_map ): array {
		$normalized = array();

		foreach ( $prefix_map as $prefix => $namespaces ) {
			$prefix = strtolower( trim( (string) $prefix ) );

			if ( ! preg_match( '/^[a-z][a-z0-9]*$/', $prefix ) || in_array( $prefix, array( 'bs', 'block' ), true ) ) {
				throw new InvalidArgumentException( "Invalid or reserved tag prefix: {$prefix}" );
			}

			$namespaces = is_array( $namespaces ) ? $namespaces : array( $namespaces );
			$values     = array();

			foreach ( $namespaces as $namespace ) {
				$namespace = strtolower( trim( (string) $namespace ) );
				if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $namespace ) ) {
					throw new InvalidArgumentException( "Invalid block namespace for {$prefix}: {$namespace}" );
				}
				$values[] = $namespace;
			}

			if ( array() === $values ) {
				throw new InvalidArgumentException( "Prefix {$prefix} has no namespaces." );
			}

			$normalized[ $prefix ] = array_values( array_unique( $values ) );
		}

		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Normalize known block names.
	 *
	 * @param array $known_blocks Raw list or keyed map.
	 *
	 * @return array<string,true> Known block set.
	 *
	 * @throws InvalidArgumentException When a block name is malformed.
	 */
	private static function normalize_known_blocks( array $known_blocks ): array {
		$normalized = array();

		foreach ( $known_blocks as $key => $value ) {
			$block_name = is_string( $key ) && ! is_int( $key ) ? $key : $value;
			if ( ! is_string( $block_name ) ) {
				throw new InvalidArgumentException( 'Known block names must be strings.' );
			}

			$block_name = strtolower( trim( $block_name ) );
			self::canonical_tag( $block_name );
			$normalized[ $block_name ] = true;
		}

		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Normalize exact aliases.
	 *
	 * Arrays remain arrays so callers can deliberately surface ambiguity.
	 *
	 * @param array $aliases Raw aliases.
	 *
	 * @return array<string,array<int,string>> Normalized aliases.
	 *
	 * @throws InvalidArgumentException When a tag or target is malformed.
	 */
	private static function normalize_aliases( array $aliases ): array {
		$normalized = array();

		foreach ( $aliases as $tag => $targets ) {
			$tag = strtolower( trim( (string) $tag ) );
			if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $tag ) ) {
				throw new InvalidArgumentException( "Invalid legacy tag alias: {$tag}" );
			}

			$targets = is_array( $targets ) ? $targets : array( $targets );
			foreach ( $targets as $target ) {
				if ( ! is_string( $target ) ) {
					throw new InvalidArgumentException( "Alias targets for {$tag} must be strings." );
				}
				$target = strtolower( trim( $target ) );
				self::canonical_tag( $target );
				$normalized[ $tag ][] = $target;
			}

			if ( empty( $normalized[ $tag ] ) ) {
				throw new InvalidArgumentException( "Alias {$tag} has no targets." );
			}
		}

		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Whether a path uses PHP tokenization.
	 *
	 * @param string $path Path.
	 *
	 * @return bool Whether to tokenize as PHP.
	 */
	private static function is_php_path( string $path ): bool {
		return (bool) preg_match( '/\.(?:php|phtml)$/i', $path );
	}
}
