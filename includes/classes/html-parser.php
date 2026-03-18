<?php
/**
 * HTML Parser class.
 *
 * Thin wrapper around Block_Tags for backward compatibility.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * HTML to WordPress blocks converter.
 *
 * @since 7.0.0
 */
class Html_Parser {

	use Paragraph_Renderer;
	use Heading_Renderer;
	use List_Renderer;
	use Quote_Renderer;
	use Pullquote_Renderer;
	use Code_Renderer;
	use Preformatted_Renderer;
	use Verse_Renderer;
	use Image_Renderer;
	use Gallery_Renderer;
	use Audio_Renderer;
	use Video_Renderer;
	use Cover_Renderer;
	use Embed_Renderer;
	use Group_Renderer;
	use Columns_Renderer;
	use Separator_Renderer;
	use Spacer_Renderer;
	use Buttons_Renderer;
	use Details_Renderer;
	use Table_Renderer;
	use Social_Links_Renderer;
	use Media_Text_Renderer;
	use More_Renderer;
	use Accordion_Renderer;
	use Query_Renderer;

	/**
	 * Block renderers registry.
	 *
	 * @var array<string, callable>
	 */
	private array $block_renderers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->block_renderers = Block_Tags::get_renderers( $this );
	}

	/**
	 * Parse HTML content to serialized WordPress blocks.
	 *
	 * @param string $html The HTML content to parse.
	 *
	 * @return string Serialized WordPress blocks.
	 */
	public function parse( string $html ): string {
		$html = trim( $html );

		if ( empty( $html ) ) {
			return '';
		}

		$html = preg_replace( '/<\?php[\s\S]*?\?>/i', '', $html );

		return serialize_blocks( Block_Tags::parse_all_elements( $html ) );
	}

	/**
	 * Parse HTML content to block array.
	 *
	 * @param string $html The HTML content to parse.
	 *
	 * @return array Array of block arrays.
	 */
	public function parse_to_array( string $html ): array {
		$html = trim( $html );

		if ( empty( $html ) ) {
			return array();
		}

		$html = preg_replace( '/<\?php[\s\S]*?\?>/i', '', $html );

		return Block_Tags::parse_all_elements( $html );
	}

	/**
	 * Create parser instance.
	 *
	 * @return Html_Parser The parser instance.
	 */
	public static function from_settings(): Html_Parser {
		return new self();
	}
}
