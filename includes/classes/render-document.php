<?php
/**
 * Frontend document assembly.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Internal document assembler used by the public Render API.
 *
 * Registration and discovery remain owned by Build, Pages, Patterns, and
 * Site_Templates. This class only closes the assets needed by an explicitly
 * selected rendered body.
 *
 * @since 7.6.0
 */
final class Render_Document {

	/**
	 * Build one complete frontend document from rendered HTML.
	 *
	 * @param string        $body        Rendered body HTML.
	 * @param array<string> $block_names Explicit canonical block names.
	 * @param array         $options     Document options.
	 *
	 * @return array{
	 *   schemaVersion:int,
	 *   html:string,
	 *   body:string,
	 *   blocks:array<int, string>,
	 *   assets:array{
	 *     head:string,
	 *     footer:string,
	 *     styles:string,
	 *     scripts:string,
	 *     modules:string,
	 *     interactivity:string,
	 *     ui:array{style:string, script:string},
	 *     tailwind:string
	 *   },
	 *   warnings:array<int, array{code:string, message:string}>,
	 *   errors:array<int, array{code:string, message:string}>
	 * }
	 */
	public static function from_html( string $body, array $block_names, array $options = array() ): array {
		$warnings = array();
		$errors   = array();
		$blocks   = self::registered_blocks();
		$names    = self::normalize_names( $block_names );
		$names    = self::expand_dependencies( $names, $blocks, $warnings );

		$asset_markup  = self::asset_markup( $names, $blocks, $body, $warnings );
		$ui_assets     = self::ui_assets( $names, $body, $warnings );
		$interactivity = self::interactivity_assets( $body, $warnings );

		$head   = self::option_string( $options, 'head', '' )
			. $asset_markup['head']
			. $ui_assets['style']
			. $interactivity;
		$footer = $asset_markup['footer']
			. $ui_assets['script']
			. self::option_string( $options, 'footer', '' );

		$native_blocks = self::native_blocks( $names );
		$head          = self::filter_markup( 'blockstudio/render/head', $head, $native_blocks, $warnings );
		$footer        = self::filter_markup( 'blockstudio/render/footer', $footer, $native_blocks, $warnings );

		$title      = self::option_string( $options, 'title', '' );
		$lang       = self::option_string( $options, 'lang', function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'language' ) : 'en' );
		$body_attrs = self::body_attributes( $options );
		$content    = self::content_markup( $body, $options );

		$document = '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. ( '' === $title ? '' : '<title>' . esc_html( $title ) . '</title>' )
			. $head
			. '</head><body' . $body_attrs . '>'
			. $content
			. $footer
			. '</body></html>';

		$document = self::filter_markup( 'blockstudio/render', $document, $native_blocks, $warnings );

		if ( class_exists( Tailwind::class ) && Settings::get( 'tailwind/enabled' ) ) {
			try {
				Tailwind::reset_request_state();
				$document = ( new Tailwind() )->compile( $document );
			} catch ( \Throwable $throwable ) {
				$warnings[] = self::issue( 'tailwind_compile_failed', $throwable->getMessage() );
			}
		}

		$tailwind = self::matching_markup(
			$document,
			'#<(?:style\b(?=[^>]*\bid=(["\'])blockstudio-tailwind\1)[^>]*>.*?</style>|link\b(?=[^>]*\bid=(["\'])blockstudio-tailwind\2)[^>]*>)#is'
		);
		$styles   = self::matching_markup(
			$document,
			'#<(?:style\b[^>]*>.*?</style>|link\b[^>]*\brel=(["\'])stylesheet\1[^>]*>)#is'
		);
		$scripts  = self::matching_markup(
			$document,
			'#<script\b(?![^>]*\btype=(["\'])module\1)[^>]*>.*?</script>#is'
		);
		$modules  = self::matching_markup(
			$document,
			'#<script\b[^>]*\btype=(["\'])(?:module|importmap)\1[^>]*>.*?</script>#is'
		);

		return array(
			'schemaVersion' => Render::SCHEMA_VERSION,
			'html'          => $document,
			'body'          => $body,
			'blocks'        => $names,
			'assets'        => array(
				'head'          => $head,
				'footer'        => $footer,
				'styles'        => $styles,
				'scripts'       => $scripts,
				'modules'       => $modules,
				'interactivity' => $interactivity,
				'ui'            => $ui_assets,
				'tailwind'      => $tailwind,
			),
			'warnings'      => $warnings,
			'errors'        => $errors,
		);
	}

	/**
	 * Get registered block data indexed by canonical name.
	 *
	 * @return array<string, array<string, mixed>> Blocks.
	 */
	private static function registered_blocks(): array {
		$blocks = array();

		foreach ( Build::data() as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] )
				? strtolower( trim( $block['name'] ) )
				: ( is_string( $key ) ? strtolower( trim( $key ) ) : '' );

			if ( preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
				$block['name']   = $name;
				$blocks[ $name ] = $block;
			}
		}

		foreach ( Build::blocks() as $name => $block ) {
			if ( ! is_string( $name ) || isset( $blocks[ $name ] ) ) {
				continue;
			}

			$data = is_object( $block ) && isset( $block->blockstudio ) && is_array( $block->blockstudio )
				? $block->blockstudio['data'] ?? array()
				: array();

			if ( is_array( $data ) ) {
				$data['name']    = $name;
				$blocks[ $name ] = $data;
			}
		}

		ksort( $blocks, SORT_STRING );

		return $blocks;
	}

	/**
	 * Normalize canonical names without waking registrations.
	 *
	 * @param array<string> $names Names.
	 *
	 * @return array<int, string> Names.
	 */
	private static function normalize_names( array $names ): array {
		$normalized = array();

		foreach ( $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}

			$name = strtolower( trim( $name ) );

			if ( preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) && ! in_array( $name, $normalized, true ) ) {
				$normalized[] = $name;
			}
		}

		return $normalized;
	}

	/**
	 * Expand dependencies referenced by selected block templates.
	 *
	 * @param array<int, string>                            $names    Initial names.
	 * @param array<string, array<string, mixed>>           $blocks   Registered data.
	 * @param array<int, array{code:string,message:string}> $warnings Warnings.
	 *
	 * @return array<int, string> Dependency closure.
	 */
	private static function expand_dependencies( array $names, array $blocks, array &$warnings ): array {
		$queue = $names;
		$seen  = array();

		while ( array() !== $queue ) {
			$name = array_shift( $queue );

			if ( ! is_string( $name ) || isset( $seen[ $name ] ) ) {
				continue;
			}

			$seen[ $name ] = true;

			if ( ! isset( $blocks[ $name ] ) ) {
				$warnings[] = self::issue( 'unknown_block', sprintf( 'The selected block "%s" is not registered.', $name ) );
				continue;
			}

			foreach ( self::template_dependencies( $blocks[ $name ], array_keys( $blocks ) ) as $dependency ) {
				if ( ! isset( $seen[ $dependency ] ) ) {
					$queue[] = $dependency;
				}
			}
		}

		return array_keys( $seen );
	}

	/**
	 * Read canonical block dependencies from one template.
	 *
	 * @param array<string, mixed> $block            Block data.
	 * @param array<int, string>   $registered_names Registered names.
	 *
	 * @return array<int, string> Dependencies.
	 */
	private static function template_dependencies( array $block, array $registered_names ): array {
		$paths = array_filter(
			array(
				$block['renderTemplate'] ?? null,
				$block['path'] ?? null,
			),
			static fn( mixed $path ): bool => is_string( $path ) && is_file( $path )
		);

		$registered = array_fill_keys( $registered_names, true );
		$names      = array();

		foreach ( $paths as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Selected template dependency scan.
			$contents = file_get_contents( $path );

			if ( ! is_string( $contents ) ) {
				continue;
			}

			if ( preg_match_all( '#<!--\s*wp:([a-z0-9-]+/[a-z0-9-]+)#i', $contents, $matches ) ) {
				$names = array_merge( $names, array_map( 'strtolower', $matches[1] ) );
			}

			if ( preg_match_all( '#<block\b[^>]*\bname\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s>/]+))#i', $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$matched_name = '' !== $match[1]
						? $match[1]
						: ( '' !== $match[2] ? $match[2] : $match[3] );
					$names[]      = strtolower( $matched_name );
				}
			}

			if ( preg_match_all( '#<bs:([a-z][a-z0-9-]*)\b#i', $contents, $matches ) ) {
				foreach ( $matches[1] as $tag ) {
					$resolved = self::canonical_name_for_tag( strtolower( $tag ), $registered );

					if ( null !== $resolved ) {
						$names[] = $resolved;
					}
				}
			}
		}

		return array_values(
			array_unique(
				array_filter(
					$names,
					static fn( string $name ): bool => isset( $registered[ $name ] )
				)
			)
		);
	}

	/**
	 * Resolve one hyphenated canonical tag against known block names.
	 *
	 * @param string              $tag        Tag without the `bs:` prefix.
	 * @param array<string, true> $registered Registered names.
	 *
	 * @return string|null Canonical name.
	 */
	private static function canonical_name_for_tag( string $tag, array $registered ): ?string {
		$offset = 0;

		while ( false !== $offset ) {
			$offset = strpos( $tag, '-', $offset );

			if ( false === $offset ) {
				break;
			}

			$candidate = substr_replace( $tag, '/', $offset, 1 );

			if ( isset( $registered[ $candidate ] ) ) {
				return $candidate;
			}

			++$offset;
		}

		return isset( $registered[ $tag ] ) ? $tag : null;
	}

	/**
	 * Render exact selected assets.
	 *
	 * @param array<int, string>                  $names    Names.
	 * @param array<string, array<string, mixed>> $blocks   Blocks.
	 * @param string                              $body     Rendered body.
	 * @param array                               $warnings Warnings.
	 *
	 * @return array{head:string,footer:string} Markup.
	 */
	private static function asset_markup( array $names, array $blocks, string $body, array &$warnings ): array {
		$head      = '';
		$footer    = '';
		$asset_ids = array();
		$selected  = array();

		foreach ( $names as $name ) {
			if ( ! isset( $blocks[ $name ] ) ) {
				continue;
			}

			$block      = $blocks[ $name ];
			$selected[] = $block;

			try {
				Assets::get_module_css_assets( $block, $asset_ids, $head );
			} catch ( \Throwable $throwable ) {
				$warnings[] = self::issue( 'module_css_failed', $throwable->getMessage() );
			}

			foreach ( self::frontend_assets( $block ) as $asset_id => $asset ) {
				if ( ! empty( $asset['editor'] )
					|| str_starts_with( $asset_id, 'admin' )
					|| str_starts_with( $asset_id, 'block-editor' )
					|| Ui::is_global_asset_path( $asset['path'] ?? null )
				) {
					continue;
				}

				$key = $name . ':' . $asset_id . ':' . (string) ( $asset['path'] ?? '' );

				if ( in_array( $key, $asset_ids, true ) ) {
					continue;
				}

				$asset_ids[] = $key;

				try {
					if ( 'inline' === ( $asset['type'] ?? '' ) ) {
						$markup = Assets::render_inline( $asset_id, $asset, $block, true );
					} else {
						$markup = Assets::render_tag( $asset_id, $asset, $block );
					}
				} catch ( \Throwable $throwable ) {
					$warnings[] = self::issue( 'asset_render_failed', $throwable->getMessage() );
					continue;
				}

				if ( ! is_string( $markup ) || '' === $markup ) {
					continue;
				}

				if ( Assets::is_css( $asset_id ) ) {
					$head .= $markup;
				} else {
					$footer .= $markup;
				}
			}
		}

		if ( array() !== $selected ) {
			try {
				$importmap = Assets::get_interactivity_importmap( self::native_blocks( $names ), $body );

				if ( is_string( $importmap ) ) {
					$head = $importmap . $head;
				}
			} catch ( \Throwable $throwable ) {
				$warnings[] = self::issue( 'interactivity_importmap_failed', $throwable->getMessage() );
			}
		}

		return array(
			'head'   => $head,
			'footer' => $footer,
		);
	}

	/**
	 * Get normalized frontend assets for one block.
	 *
	 * @param array<string, mixed> $block Block data.
	 *
	 * @return array<string, array<string, mixed>> Assets.
	 */
	private static function frontend_assets( array $block ): array {
		$assets = $block['assets'] ?? array();

		if ( ! is_array( $assets ) ) {
			return array();
		}

		return array_filter(
			$assets,
			static fn( mixed $asset, mixed $key ): bool => is_string( $key ) && is_array( $asset ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Get native block objects for render filters.
	 *
	 * @param array<int, string> $names Names.
	 *
	 * @return array<string, mixed> Native blocks.
	 */
	private static function native_blocks( array $names ): array {
		$registered = Build::blocks();
		$selected   = array();

		foreach ( $names as $name ) {
			if ( isset( $registered[ $name ] ) ) {
				$selected[ $name ] = $registered[ $name ];
			}
		}

		return $selected;
	}

	/**
	 * Read bundled UI globals only when selected output uses them.
	 *
	 * @param array<int, string> $names    Names.
	 * @param string             $body     Body.
	 * @param array              $warnings Warnings.
	 *
	 * @return array{style:string,script:string} UI assets.
	 */
	private static function ui_assets( array $names, string $body, array &$warnings ): array {
		if ( ! method_exists( Ui::class, 'global_assets' ) ) {
			return array(
				'style'  => '',
				'script' => '',
			);
		}

		try {
			$assets = Ui::global_assets( $names, $body );

			return array(
				'style'  => is_string( $assets['style'] ?? null ) ? $assets['style'] : '',
				'script' => is_string( $assets['script'] ?? null ) ? $assets['script'] : '',
			);
		} catch ( \Throwable $throwable ) {
			$warnings[] = self::issue( 'ui_assets_failed', $throwable->getMessage() );

			return array(
				'style'  => '',
				'script' => '',
			);
		}
	}

	/**
	 * Get WordPress interactivity runtime assets when needed.
	 *
	 * @param string $body     Body HTML.
	 * @param array  $warnings Warnings.
	 *
	 * @return string Asset markup.
	 */
	private static function interactivity_assets( string $body, array &$warnings ): string {
		if ( ! str_contains( $body, 'data-wp-interactive' ) ) {
			return '';
		}

		try {
			if ( function_exists( 'wp_script_modules' ) && function_exists( 'wp_default_script_modules' ) ) {
				$modules = wp_script_modules();

				if ( is_callable( array( $modules, 'get_registered' ) )
					&& null === $modules->get_registered( '@wordpress/interactivity' )
				) {
					wp_default_script_modules();
				}
			}

			return Assets::get_interactivity_editor_assets();
		} catch ( \Throwable $throwable ) {
			$warnings[] = self::issue( 'interactivity_assets_failed', $throwable->getMessage() );

			return '';
		}
	}

	/**
	 * Apply one render filter while preserving deterministic fallback markup.
	 *
	 * @param string $hook      Hook.
	 * @param string $markup    Markup.
	 * @param array  $blocks    Native blocks.
	 * @param array  $warnings  Warnings.
	 *
	 * @return string Filtered markup.
	 */
	private static function filter_markup( string $hook, string $markup, array $blocks, array &$warnings ): string {
		try {
			$filtered = apply_filters( $hook, $markup, $blocks );

			return is_string( $filtered ) ? $filtered : $markup;
		} catch ( \Throwable $throwable ) {
			$warnings[] = self::issue( 'render_filter_failed', sprintf( '%s: %s', $hook, $throwable->getMessage() ) );

			return $markup;
		}
	}

	/**
	 * Get a string document option.
	 *
	 * @param array  $options Options.
	 * @param string $key     Key.
	 * @param string $default Default.
	 *
	 * @return string Value.
	 */
	private static function option_string( array $options, string $key, string $default ): string {
		return isset( $options[ $key ] ) && is_scalar( $options[ $key ] )
			? (string) $options[ $key ]
			: $default;
	}

	/**
	 * Serialize safe body attributes.
	 *
	 * @param array $options Options.
	 *
	 * @return string Attribute string including leading space.
	 */
	private static function body_attributes( array $options ): string {
		return self::element_attributes(
			$options['bodyAttributes'] ?? array(),
			$options['bodyClasses'] ?? array()
		);
	}

	/**
	 * Wrap rendered content in an optional semantic document element.
	 *
	 * @param string $body    Rendered body.
	 * @param array  $options Options.
	 *
	 * @return string Content markup.
	 */
	private static function content_markup( string $body, array $options ): string {
		$element = strtolower( trim( self::option_string( $options, 'contentElement', '' ) ) );

		if ( '' === $element ) {
			return $body;
		}

		$blocked = array( 'html', 'head', 'body', 'base', 'link', 'meta', 'script', 'style', 'title' );
		if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $element ) || in_array( $element, $blocked, true ) ) {
			return $body;
		}

		$attributes = self::element_attributes(
			$options['contentAttributes'] ?? array(),
			$options['contentClasses'] ?? array()
		);

		return '<' . $element . $attributes . '>' . $body . '</' . $element . '>';
	}

	/**
	 * Serialize safe element attributes and classes.
	 *
	 * @param mixed $attributes Attributes.
	 * @param mixed $classes    Classes.
	 *
	 * @return string Attribute string including leading space.
	 */
	private static function element_attributes( mixed $attributes, mixed $classes = array() ): string {

		if ( is_string( $classes ) ) {
			$classes = preg_split( '/\s+/', trim( $classes ) );
		}

		if ( ! is_array( $attributes ) || array_is_list( $attributes ) ) {
			$attributes = array();
		}

		if ( is_array( $classes ) ) {
			$classes = array_values(
				array_unique(
					array_filter(
						array_map(
							static fn( mixed $class ): string => is_scalar( $class ) ? sanitize_html_class( (string) $class ) : '',
							$classes
						)
					)
				)
			);

			if ( array() !== $classes ) {
				$attributes['class'] = implode( ' ', $classes );
			}
		}

		$serialized = '';

		foreach ( $attributes as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', $name ) || null === $value || false === $value ) {
				continue;
			}

			$serialized .= true === $value
				? ' ' . $name
				: ' ' . $name . '="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"';
		}

		return $serialized;
	}

	/**
	 * Extract matching markup.
	 *
	 * @param string $html    HTML.
	 * @param string $pattern PCRE pattern.
	 *
	 * @return string Combined matches.
	 */
	private static function matching_markup( string $html, string $pattern ): string {
		if ( ! preg_match_all( $pattern, $html, $matches ) ) {
			return '';
		}

		return implode( '', $matches[0] );
	}

	/**
	 * Build one stable warning/error row.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 *
	 * @return array{code:string,message:string} Issue.
	 */
	private static function issue( string $code, string $message ): array {
		return array(
			'code'    => $code,
			'message' => $message,
		);
	}
}
