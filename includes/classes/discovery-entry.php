<?php
/**
 * Discovery entry value object.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Describes one logical file exposed by a discovery source.
 *
 * Logical paths are relative to the source root. The physical path is the
 * readable file selected by the source. A composed source can therefore map
 * different logical siblings to different physical roots.
 *
 * @since 7.5.0
 */
final class Discovery_Entry {

	/**
	 * Create a discovery entry.
	 *
	 * @param string $logical_path Logical path relative to the source root.
	 * @param string $physical_path Readable physical file path.
	 * @param array  $provenance Source-defined provenance metadata.
	 * @param array  $metadata Source-defined entry metadata.
	 */
	public function __construct(
		private readonly string $logical_path,
		private readonly string $physical_path,
		private readonly array $provenance = array(),
		private readonly array $metadata = array()
	) {
	}

	/**
	 * Get the logical path.
	 *
	 * @return string Logical path.
	 */
	public function logical_path(): string {
		return $this->logical_path;
	}

	/**
	 * Get the physical path.
	 *
	 * @return string Physical path.
	 */
	public function physical_path(): string {
		return $this->physical_path;
	}

	/**
	 * Get provenance metadata.
	 *
	 * @return array Provenance metadata.
	 */
	public function provenance(): array {
		return $this->provenance;
	}

	/**
	 * Get source-defined metadata.
	 *
	 * @return array Entry metadata.
	 */
	public function metadata(): array {
		return $this->metadata;
	}
}
