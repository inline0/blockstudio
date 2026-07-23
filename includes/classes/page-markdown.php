<?php
/**
 * Markdown and frontmatter helpers for file-based pages.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Throwable;

/**
 * Converts markdown content and parses YAML frontmatter.
 *
 * @since 7.3.4
 */
class Page_Markdown {

	/**
	 * Split optional YAML frontmatter from a markdown document.
	 *
	 * @param string $content Markdown content.
	 *
	 * @return array{data: array, body: string, frontmatter: string}
	 */
	public static function split_frontmatter( string $content ): array {
		$content = ltrim( $content, "\xEF\xBB\xBF" );

		if ( ! preg_match( '/\A---[ \t]*\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|$)(.*)\z/s', $content, $matches ) ) {
			return array(
				'data'        => array(),
				'body'        => $content,
				'frontmatter' => '',
			);
		}

		return array(
			'data'        => self::parse_yaml( $matches[1] ),
			'body'        => $matches[2],
			'frontmatter' => $matches[1],
		);
	}

	/**
	 * Parse YAML using bundled Symfony YAML when available.
	 *
	 * @param string $yaml YAML string.
	 *
	 * @return array
	 */
	public static function parse_yaml( string $yaml ): array {
		foreach (
			array(
				'BlockstudioVendor\\Symfony\\Component\\Yaml\\Yaml',
				'Symfony\\Component\\Yaml\\Yaml',
			) as $class_name
		) {
			if ( class_exists( $class_name ) ) {
				try {
					$parsed = $class_name::parse( $yaml );
					return is_array( $parsed ) ? $parsed : array();
				} catch ( Throwable ) {
					return array();
				}
			}
		}

		return self::parse_simple_yaml( $yaml );
	}

	/**
	 * Convert markdown to HTML using bundled CommonMark when available.
	 *
	 * @param string $markdown Markdown body.
	 *
	 * @return string HTML.
	 */
	public static function to_html( string $markdown ): string {
		foreach (
			array(
				'BlockstudioVendor\\League\\CommonMark\\GithubFlavoredMarkdownConverter',
				'League\\CommonMark\\GithubFlavoredMarkdownConverter',
			) as $class_name
		) {
			if ( class_exists( $class_name ) ) {
				try {
					$converter = new $class_name(
						array(
							'html_input'         => 'allow',
							'allow_unsafe_links' => false,
						)
					);

					return self::add_heading_ids( (string) $converter->convert( $markdown ) );
				} catch ( Throwable ) {
					break;
				}
			}
		}

		return self::add_heading_ids( self::simple_markdown_to_html( $markdown ) );
	}

	/**
	 * Sanitize generated external docs HTML.
	 *
	 * @param string $html HTML.
	 *
	 * @return string Sanitized HTML.
	 */
	public static function sanitize_docs_html( string $html ): string {
		$allowed = array(
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'blockquote' => array( 'cite' => true ),
			'br'         => array(),
			'code'       => array( 'class' => true ),
			'del'        => array(),
			'div'        => array(
				'class' => true,
				'id'    => true,
			),
			'em'         => array(),
			'figure'     => array( 'class' => true ),
			'h1'         => array( 'id' => true ),
			'h2'         => array( 'id' => true ),
			'h3'         => array( 'id' => true ),
			'h4'         => array( 'id' => true ),
			'h5'         => array( 'id' => true ),
			'h6'         => array( 'id' => true ),
			'hr'         => array(),
			'img'        => array(
				'alt'    => true,
				'height' => true,
				'src'    => true,
				'title'  => true,
				'width'  => true,
			),
			'li'         => array(),
			'ol'         => array(),
			'p'          => array(),
			'pre'        => array( 'class' => true ),
			'span'       => array(
				'class' => true,
				'id'    => true,
			),
			'strong'     => array(),
			'table'      => array(),
			'tbody'      => array(),
			'td'         => array(
				'align'   => true,
				'colspan' => true,
				'rowspan' => true,
			),
			'th'         => array(
				'align'   => true,
				'colspan' => true,
				'rowspan' => true,
			),
			'thead'      => array(),
			'tr'         => array(),
			'ul'         => array(),
		);

		$allowed = apply_filters( 'blockstudio/pages/docs_allowed_html', $allowed );

		return wp_kses( $html, is_array( $allowed ) ? $allowed : array() );
	}

	/**
	 * Fallback YAML parser for simple scalar frontmatter.
	 *
	 * @param string $yaml YAML.
	 *
	 * @return array
	 */
	private static function parse_simple_yaml( string $yaml ): array {
		$data = array();

		$lines = preg_split( '/\r?\n/', $yaml );

		if ( false === $lines ) {
			$lines = array();
		}

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) || str_starts_with( ltrim( $line ), '#' ) ) {
				continue;
			}

			if ( ! preg_match( '/^([A-Za-z0-9_.-]+):\s*(.*)$/', $line, $matches ) ) {
				continue;
			}

			$data[ $matches[1] ] = self::parse_scalar( $matches[2] );
		}

		return $data;
	}

	/**
	 * Parse a simple YAML scalar.
	 *
	 * @param string $value Scalar value.
	 *
	 * @return mixed
	 */
	private static function parse_scalar( string $value ): mixed {
		$value = trim( $value );

		if ( '' === $value || 'null' === strtolower( $value ) ) {
			return null;
		}

		if ( 'true' === strtolower( $value ) ) {
			return true;
		}

		if ( 'false' === strtolower( $value ) ) {
			return false;
		}

		if ( is_numeric( $value ) ) {
			if ( str_contains( $value, '.' ) ) {
				return (float) $value;
			}

			return (int) $value;
		}

		if (
			( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) ||
			( str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) )
		) {
			return substr( $value, 1, -1 );
		}

		if ( str_starts_with( $value, '[' ) && str_ends_with( $value, ']' ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return $value;
	}

	/**
	 * Small fallback markdown converter used only when dependencies are absent.
	 *
	 * @param string $markdown Markdown.
	 *
	 * @return string HTML.
	 */
	private static function simple_markdown_to_html( string $markdown ): string {
		$blocks = preg_split( "/\n{2,}/", trim( $markdown ) );
		$html   = array();

		if ( false === $blocks ) {
			$blocks = array();
		}

		foreach ( $blocks as $block ) {
			$block = trim( $block );

			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $block, $matches ) ) {
				$level  = strlen( $matches[1] );
				$html[] = '<h' . $level . '>' . esc_html( $matches[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^```(?:[A-Za-z0-9_-]+)?\n(.*)\n```$/s', $block, $matches ) ) {
				$html[] = '<pre><code>' . esc_html( $matches[1] ) . '</code></pre>';
				continue;
			}

			if ( preg_match_all( '/^- (.+)$/m', $block, $matches ) ) {
				$items  = array_map(
					static fn ( string $item ): string => '<li>' . esc_html( $item ) . '</li>',
					$matches[1]
				);
				$html[] = '<ul>' . implode( '', $items ) . '</ul>';
				continue;
			}

			$html[] = '<p>' . nl2br( esc_html( $block ) ) . '</p>';
		}

		return implode( "\n", $html );
	}

	/**
	 * Add stable IDs to markdown headings that do not already provide one.
	 *
	 * @param string $html Rendered markdown HTML.
	 *
	 * @return string HTML with heading ids.
	 */
	private static function add_heading_ids( string $html ): string {
		$used_ids = array();

		if ( preg_match_all( '/<h[1-6]\b[^>]*\bid=(["\'])(.*?)\1/i', $html, $matches ) ) {
			foreach ( $matches[2] as $id ) {
				$used_ids[ (string) $id ] = true;
			}
		}

		return preg_replace_callback(
			'/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
			static function ( array $matches ) use ( &$used_ids ): string {
				if ( preg_match( '/\bid=(["\']).*?\1/i', $matches[2] ) ) {
					return $matches[0];
				}

				$base = sanitize_title( wp_strip_all_tags( html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );

				if ( '' === $base ) {
					return $matches[0];
				}

				$id     = $base;
				$suffix = 2;

				while ( isset( $used_ids[ $id ] ) ) {
					$id = $base . '-' . $suffix;
					++$suffix;
				}

				$used_ids[ $id ] = true;

				return '<h' . $matches[1] . $matches[2] . ' id="' . esc_attr( $id ) . '">' . $matches[3] . '</h' . $matches[1] . '>';
			},
			$html
		) ?? $html;
	}
}
