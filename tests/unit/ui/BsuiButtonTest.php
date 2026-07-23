<?php

use PHPUnit\Framework\TestCase;

class BsuiButtonTest extends TestCase {

	private function render_button( array $attributes ): string {
		$a = $attributes;

		ob_start();
		include BLOCKSTUDIO_DIR . '/includes/ui/blocks/button/root/index.php';

		return ob_get_clean();
	}

	public function test_button_without_icon_does_not_render_icon_slot(): void {
		$output = $this->render_button(
			array(
				'label' => 'Button',
			)
		);

		$this->assertStringContainsString( 'data-bsui-button', $output );
		$this->assertStringContainsString( '<RichText attribute="label" tag="span" placeholder="Button" />', $output );
		$this->assertStringNotContainsString( 'data-bsui-button-icon', $output );
	}

	public function test_button_icon_renders_before_label_by_default(): void {
		$output = $this->render_button(
			array(
				'icon' => array(
					'element' => '<svg data-test-icon="left"></svg>',
				),
			)
		);

		$this->assertStringContainsString( 'data-bsui-button-icon', $output );
		$this->assertLessThan(
			strpos( $output, '<RichText' ),
			strpos( $output, 'data-test-icon="left"' )
		);
	}

	public function test_button_icon_can_render_after_label(): void {
		$output = $this->render_button(
			array(
				'icon'         => array(
					'element' => '<svg data-test-icon="right"></svg>',
				),
				'iconPosition' => 'right',
			)
		);

		$this->assertStringContainsString( 'data-bsui-button-icon', $output );
		$this->assertGreaterThan(
			strpos( $output, '<RichText' ),
			strpos( $output, 'data-test-icon="right"' )
		);
	}
}
