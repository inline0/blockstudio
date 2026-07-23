<?php
/**
 * Discovery source contract.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Supplies a logical file inventory to Blockstudio discovery systems.
 *
 * Implementations return their final visible inventory. A composed source is
 * responsible for applying its own shadow, addition, and deletion rules before
 * returning entries. Blockstudio owns interpretation of the resulting files.
 *
 * @since 7.5.0
 */
interface Discovery_Source {

	/**
	 * Get a stable source identifier.
	 *
	 * @return string Source identifier.
	 */
	public function id(): string;

	/**
	 * Get the compatibility root for the source.
	 *
	 * The root identifies this inventory to existing registries. It does not
	 * need to contain every physical file returned by a composed source.
	 *
	 * @return string Source root.
	 */
	public function root(): string;

	/**
	 * Get a fingerprint that changes when the visible inventory changes.
	 *
	 * @return string Content fingerprint.
	 */
	public function fingerprint(): string;

	/**
	 * List visible files in deterministic logical-path order.
	 *
	 * @param string $directory Logical directory relative to the source root.
	 * @param bool   $recursive Include descendants below the directory.
	 *
	 * @return array<int, Discovery_Entry> Visible file entries.
	 */
	public function entries( string $directory = '', bool $recursive = true ): array;

	/**
	 * Resolve one logical file.
	 *
	 * @param string $logical_path Logical path relative to the source root.
	 *
	 * @return Discovery_Entry|null Resolved entry, or null when hidden or absent.
	 */
	public function resolve( string $logical_path ): ?Discovery_Entry;

	/**
	 * List direct file children of a logical directory.
	 *
	 * @param string $directory Logical directory relative to the source root.
	 *
	 * @return array<int, Discovery_Entry> Direct file children.
	 */
	public function children( string $directory = '' ): array;

	/**
	 * List visible logical directories.
	 *
	 * @param string $directory Logical directory relative to the source root.
	 * @param bool   $recursive Include descendant directories.
	 *
	 * @return array<int, string> Logical directory paths.
	 */
	public function directories( string $directory = '', bool $recursive = true ): array;

	/**
	 * Get physical paths whose changes invalidate this source.
	 *
	 * @return array<int, string> Files or directories to watch.
	 */
	public function watch_paths(): array;
}
