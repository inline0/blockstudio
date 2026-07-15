<?php
/**
 * Batch render state reset.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Resets request-scoped Blockstudio state between in-process page renders.
 */
final class Batch_Render {

	/**
	 * Reset render state without rebuilding discovery or registrations.
	 *
	 * @return void
	 */
	public static function reset(): void {
		Block::reset_request_state();
		Assets::reset_request_state();
		Build::reset_request_state();
		Islands::reset_request_state();
		Pages::reset_request_state();
		Tailwind::reset_request_state();
	}
}
