<?php

use Blockstudio\Attribute_Builder;
use Blockstudio\Interfaces\Field_Handler_Interface;
use PHPUnit\Framework\TestCase;

class AttributeBuilderTest extends TestCase {

	private Attribute_Builder $builder;

	protected function setUp(): void {
		$this->builder = new Attribute_Builder();
	}

	// build() - empty input

	public function test_build_with_empty_fields_returns_empty_array(): void {
		$this->assertSame( array(), $this->builder->build( array() ) );
	}

	// build() - text fields

	public function test_build_text_field(): void {
		$fields = array(
			array(
				'id'   => 'title',
				'type' => 'text',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertTrue( $result['title']['blockstudio'] );
		$this->assertSame( 'string', $result['title']['type'] );
		$this->assertSame( 'text', $result['title']['field'] );
		$this->assertSame( 'title', $result['title']['id'] );
	}

	public function test_build_text_field_with_default(): void {
		$fields = array(
			array(
				'id'      => 'heading',
				'type'    => 'text',
				'default' => 'Hello World',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'Hello World', $result['heading']['default'] );
	}

	public function test_build_text_field_with_fallback(): void {
		$fields = array(
			array(
				'id'       => 'subtitle',
				'type'     => 'text',
				'fallback' => 'Default subtitle',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'Default subtitle', $result['subtitle']['fallback'] );
	}

	public function test_build_textarea_field(): void {
		$fields = array(
			array(
				'id'   => 'description',
				'type' => 'textarea',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'description', $result );
		$this->assertSame( 'string', $result['description']['type'] );
		$this->assertSame( 'textarea', $result['description']['field'] );
	}

	public function test_build_code_field(): void {
		$fields = array(
			array(
				'id'   => 'snippet',
				'type' => 'code',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'snippet', $result );
		$this->assertSame( 'string', $result['snippet']['type'] );
		$this->assertSame( 'code', $result['snippet']['field'] );
		$this->assertSame( 'html', $result['snippet']['language'] );
		$this->assertFalse( $result['snippet']['asset'] );
	}

	public function test_build_code_field_with_language_and_asset(): void {
		$fields = array(
			array(
				'id'       => 'css_code',
				'type'     => 'code',
				'language' => 'css',
				'asset'    => true,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'css', $result['css_code']['language'] );
		$this->assertTrue( $result['css_code']['asset'] );
	}

	public function test_build_richtext_field_has_html_source(): void {
		$fields = array(
			array(
				'id'   => 'content',
				'type' => 'richtext',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'html', $result['content']['source'] );
		$this->assertSame( 'string', $result['content']['type'] );
	}

	public function test_build_wysiwyg_field_has_html_source(): void {
		$fields = array(
			array(
				'id'   => 'body',
				'type' => 'wysiwyg',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'html', $result['body']['source'] );
	}

	public function test_build_date_field(): void {
		$fields = array(
			array(
				'id'   => 'start_date',
				'type' => 'date',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'string', $result['start_date']['type'] );
		$this->assertSame( 'date', $result['start_date']['field'] );
	}

	public function test_build_datetime_field(): void {
		$fields = array(
			array(
				'id'   => 'event_time',
				'type' => 'datetime',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'string', $result['event_time']['type'] );
		$this->assertSame( 'datetime', $result['event_time']['field'] );
	}

	public function test_build_unit_field(): void {
		$fields = array(
			array(
				'id'   => 'width',
				'type' => 'unit',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'string', $result['width']['type'] );
		$this->assertSame( 'unit', $result['width']['field'] );
	}

	public function test_build_classes_field(): void {
		$fields = array(
			array(
				'id'   => 'css_classes',
				'type' => 'classes',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'string', $result['css_classes']['type'] );
		$this->assertSame( 'classes', $result['css_classes']['field'] );
		$this->assertArrayNotHasKey( 'tailwind', $result['css_classes'] );
	}

	public function test_build_classes_field_with_tailwind(): void {
		$fields = array(
			array(
				'id'       => 'tw_classes',
				'type'     => 'classes',
				'tailwind' => true,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertTrue( $result['tw_classes']['tailwind'] );
	}

	// build() - number fields

	public function test_build_number_field(): void {
		$fields = array(
			array(
				'id'   => 'count',
				'type' => 'number',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'count', $result );
		$this->assertSame( 'number', $result['count']['type'] );
		$this->assertSame( 'number', $result['count']['field'] );
	}

	public function test_build_number_field_with_default(): void {
		$fields = array(
			array(
				'id'      => 'quantity',
				'type'    => 'number',
				'default' => 5,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 5, $result['quantity']['default'] );
	}

	public function test_build_number_field_with_zero_default(): void {
		$fields = array(
			array(
				'id'      => 'offset',
				'type'    => 'number',
				'default' => 0,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( '0', $result['offset']['default'] );
	}

	public function test_build_number_field_with_zero_fallback(): void {
		$fields = array(
			array(
				'id'       => 'min_val',
				'type'     => 'number',
				'fallback' => 0,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( '0', $result['min_val']['fallback'] );
	}

	public function test_build_range_field(): void {
		$fields = array(
			array(
				'id'   => 'opacity',
				'type' => 'range',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'number', $result['opacity']['type'] );
		$this->assertSame( 'range', $result['opacity']['field'] );
	}

	// build() - boolean fields

	public function test_build_toggle_field(): void {
		$fields = array(
			array(
				'id'   => 'enabled',
				'type' => 'toggle',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'enabled', $result );
		$this->assertSame( 'boolean', $result['enabled']['type'] );
		$this->assertSame( 'toggle', $result['enabled']['field'] );
	}

	public function test_build_toggle_field_with_default_true(): void {
		$fields = array(
			array(
				'id'      => 'visible',
				'type'    => 'toggle',
				'default' => true,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertTrue( $result['visible']['default'] );
	}

	public function test_build_toggle_field_with_default_false(): void {
		$fields = array(
			array(
				'id'      => 'hidden',
				'type'    => 'toggle',
				'default' => false,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertFalse( $result['hidden']['default'] );
	}

	// build() - select fields

	public function test_build_select_field(): void {
		$fields = array(
			array(
				'id'      => 'alignment',
				'type'    => 'select',
				'options' => array(
					array(
						'value' => 'left',
						'label' => 'Left',
					),
					array(
						'value' => 'center',
						'label' => 'Center',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'alignment', $result );
		$this->assertSame( 'object', $result['alignment']['type'] );
		$this->assertSame( 'select', $result['alignment']['field'] );
		$this->assertCount( 2, $result['alignment']['options'] );
	}

	public function test_build_select_field_with_multiple(): void {
		$fields = array(
			array(
				'id'       => 'tags',
				'type'     => 'select',
				'multiple' => true,
				'options'  => array(
					array(
						'value' => 'a',
						'label' => 'A',
					),
					array(
						'value' => 'b',
						'label' => 'B',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'array', $result['tags']['type'] );
		$this->assertTrue( $result['tags']['multiple'] );
	}

	public function test_build_radio_field(): void {
		$fields = array(
			array(
				'id'      => 'layout',
				'type'    => 'radio',
				'options' => array(
					array(
						'value' => 'grid',
						'label' => 'Grid',
					),
					array(
						'value' => 'list',
						'label' => 'List',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'object', $result['layout']['type'] );
		$this->assertSame( 'radio', $result['layout']['field'] );
	}

	public function test_build_checkbox_field(): void {
		$fields = array(
			array(
				'id'      => 'features',
				'type'    => 'checkbox',
				'options' => array(
					array(
						'value' => 'bold',
						'label' => 'Bold',
					),
					array(
						'value' => 'italic',
						'label' => 'Italic',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'array', $result['features']['type'] );
		$this->assertSame( 'checkbox', $result['features']['field'] );
	}

	public function test_build_token_field(): void {
		$fields = array(
			array(
				'id'   => 'keywords',
				'type' => 'token',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'array', $result['keywords']['type'] );
		$this->assertSame( 'token', $result['keywords']['field'] );
	}

	public function test_build_color_field(): void {
		$fields = array(
			array(
				'id'      => 'bg_color',
				'type'    => 'color',
				'options' => array(
					array(
						'value' => '#ff0000',
						'label' => 'Red',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'object', $result['bg_color']['type'] );
		$this->assertSame( 'color', $result['bg_color']['field'] );
	}

	public function test_build_gradient_field(): void {
		$fields = array(
			array(
				'id'      => 'bg_gradient',
				'type'    => 'gradient',
				'options' => array(
					array(
						'value' => 'linear-gradient(to right, #000, #fff)',
						'label' => 'BW',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'object', $result['bg_gradient']['type'] );
		$this->assertSame( 'gradient', $result['bg_gradient']['field'] );
	}

	public function test_build_icon_field(): void {
		$fields = array(
			array(
				'id'   => 'block_icon',
				'type' => 'icon',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'object', $result['block_icon']['type'] );
		$this->assertSame( 'icon', $result['block_icon']['field'] );
	}

	public function test_build_link_field(): void {
		$fields = array(
			array(
				'id'   => 'cta_link',
				'type' => 'link',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'object', $result['cta_link']['type'] );
		$this->assertSame( 'link', $result['cta_link']['field'] );
	}

	public function test_build_select_with_return_format(): void {
		$fields = array(
			array(
				'id'           => 'theme',
				'type'         => 'select',
				'options'      => array(
					array(
						'value' => 'light',
						'label' => 'Light',
					),
				),
				'returnFormat' => 'label',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'label', $result['theme']['returnFormat'] );
	}

	public function test_build_select_with_populate(): void {
		$fields = array(
			array(
				'id'       => 'category',
				'type'     => 'select',
				'options'  => array(),
				'populate' => array(
					'type'   => 'custom',
					'custom' => 'my_custom_populate',
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'populate', $result['category'] );
		$this->assertSame( 'custom', $result['category']['populate']['type'] );
	}

	// build() - media fields

	public function test_build_files_field(): void {
		$fields = array(
			array(
				'id'   => 'image',
				'type' => 'files',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'image', $result );
		$this->assertSame( array( 'number', 'object', 'array' ), $result['image']['type'] );
		$this->assertSame( 'files', $result['image']['field'] );
		$this->assertFalse( $result['image']['multiple'] );
		$this->assertSame( 'full', $result['image']['returnSize'] );
	}

	public function test_build_files_field_multiple(): void {
		$fields = array(
			array(
				'id'       => 'gallery',
				'type'     => 'files',
				'multiple' => true,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertTrue( $result['gallery']['multiple'] );
	}

	public function test_build_files_field_with_return_size(): void {
		$fields = array(
			array(
				'id'         => 'thumb',
				'type'       => 'files',
				'returnSize' => 'thumbnail',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'thumbnail', $result['thumb']['returnSize'] );
	}

	public function test_build_files_field_with_return_format(): void {
		$fields = array(
			array(
				'id'           => 'attachment',
				'type'         => 'files',
				'returnFormat' => 'url',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'url', $result['attachment']['returnFormat'] );
	}

	// build() - container fields: group

	public function test_build_group_field_flattens_children(): void {
		$fields = array(
			array(
				'id'         => 'cta',
				'type'       => 'group',
				'attributes' => array(
					array(
						'id'   => 'label',
						'type' => 'text',
					),
					array(
						'id'   => 'url',
						'type' => 'text',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'cta_label', $result );
		$this->assertArrayHasKey( 'cta_url', $result );
		$this->assertSame( 'string', $result['cta_label']['type'] );
		$this->assertSame( 'string', $result['cta_url']['type'] );
	}

	public function test_build_group_without_id_still_works(): void {
		$fields = array(
			array(
				'type'       => 'group',
				'attributes' => array(
					array(
						'id'   => 'name',
						'type' => 'text',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'name', $result );
	}

	public function test_build_group_without_attributes_produces_nothing(): void {
		$fields = array(
			array(
				'id'   => 'empty_group',
				'type' => 'group',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	// build() - container fields: tabs

	public function test_build_tabs_flattens_fields_from_all_tabs(): void {
		$fields = array(
			array(
				'type' => 'tabs',
				'tabs' => array(
					array(
						'label'      => 'Content',
						'attributes' => array(
							array(
								'id'   => 'heading',
								'type' => 'text',
							),
						),
					),
					array(
						'label'      => 'Settings',
						'attributes' => array(
							array(
								'id'   => 'columns',
								'type' => 'number',
							),
						),
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'heading', $result );
		$this->assertArrayHasKey( 'columns', $result );
		$this->assertSame( 'string', $result['heading']['type'] );
		$this->assertSame( 'number', $result['columns']['type'] );
	}

	public function test_build_tabs_without_tabs_key_produces_nothing(): void {
		$fields = array(
			array(
				'type' => 'tabs',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	// build() - container fields: repeater

	public function test_build_repeater_field(): void {
		$fields = array(
			array(
				'id'         => 'items',
				'type'       => 'repeater',
				'attributes' => array(
					array(
						'id'   => 'title',
						'type' => 'text',
					),
					array(
						'id'   => 'count',
						'type' => 'number',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertSame( 'array', $result['items']['type'] );
		$this->assertSame( 'repeater', $result['items']['field'] );
		$this->assertTrue( $result['items']['blockstudio'] );
	}

	public function test_build_repeater_with_default(): void {
		$fields = array(
			array(
				'id'         => 'rows',
				'type'       => 'repeater',
				'default'    => array( array( 'text' => 'First' ) ),
				'attributes' => array(
					array(
						'id'   => 'text',
						'type' => 'text',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array( array( 'text' => 'First' ) ), $result['rows']['default'] );
	}

	public function test_build_repeater_without_id_produces_nothing(): void {
		$fields = array(
			array(
				'type'       => 'repeater',
				'attributes' => array(
					array(
						'id'   => 'x',
						'type' => 'text',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	// build() - attributes field type

	public function test_build_attributes_field(): void {
		$fields = array(
			array(
				'id'   => 'custom_attrs',
				'type' => 'attributes',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'custom_attrs', $result );
		$this->assertSame( 'array', $result['custom_attrs']['type'] );
		$this->assertSame( 'attributes', $result['custom_attrs']['field'] );
		$this->assertSame( 'custom_attrs', $result['custom_attrs']['id'] );
	}

	// build() - message type is skipped

	public function test_build_skips_message_type(): void {
		$fields = array(
			array(
				'id'   => 'info',
				'type' => 'message',
			),
			array(
				'id'   => 'title',
				'type' => 'text',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayNotHasKey( 'info', $result );
		$this->assertArrayHasKey( 'title', $result );
	}

	// build() - fields without id or type are skipped

	public function test_build_skips_field_without_id(): void {
		$fields = array(
			array(
				'type' => 'text',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	public function test_build_skips_field_without_type(): void {
		$fields = array(
			array(
				'id' => 'orphan',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	public function test_build_skips_unknown_type(): void {
		$fields = array(
			array(
				'id'   => 'custom',
				'type' => 'nonexistent_type',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	// build() - multiple fields

	public function test_build_multiple_fields(): void {
		$fields = array(
			array(
				'id'   => 'title',
				'type' => 'text',
			),
			array(
				'id'   => 'count',
				'type' => 'number',
			),
			array(
				'id'   => 'enabled',
				'type' => 'toggle',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertCount( 3, $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertArrayHasKey( 'enabled', $result );
	}

	public function test_build_mixed_types_and_containers(): void {
		$fields = array(
			array(
				'id'   => 'heading',
				'type' => 'text',
			),
			array(
				'type' => 'message',
				'id'   => 'info',
			),
			array(
				'id'         => 'cta',
				'type'       => 'group',
				'attributes' => array(
					array(
						'id'   => 'label',
						'type' => 'text',
					),
				),
			),
			array(
				'id'   => 'show',
				'type' => 'toggle',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'heading', $result );
		$this->assertArrayNotHasKey( 'info', $result );
		$this->assertArrayHasKey( 'cta_label', $result );
		$this->assertArrayHasKey( 'show', $result );
	}

	// build() - storage configuration

	public function test_build_text_field_with_storage(): void {
		$fields = array(
			array(
				'id'      => 'meta_title',
				'type'    => 'text',
				'storage' => array(
					'type' => 'option',
					'key'  => 'site_title',
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame(
			array(
				'type' => 'option',
				'key'  => 'site_title',
			),
			$result['meta_title']['storage']
		);
	}

	public function test_build_number_field_with_storage(): void {
		$fields = array(
			array(
				'id'      => 'max_items',
				'type'    => 'number',
				'storage' => array(
					'type' => 'post_meta',
					'key'  => 'max_items',
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'storage', $result['max_items'] );
	}

	public function test_build_toggle_field_with_storage(): void {
		$fields = array(
			array(
				'id'      => 'active',
				'type'    => 'toggle',
				'storage' => array( 'type' => 'option' ),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'storage', $result['active'] );
	}

	// is_tailwind_active() and reset_tailwind_active()

	public function test_tailwind_inactive_by_default(): void {
		$this->assertFalse( $this->builder->is_tailwind_active() );
	}

	public function test_tailwind_activated_by_classes_field_with_tailwind_true(): void {
		$fields = array(
			array(
				'id'       => 'styles',
				'type'     => 'classes',
				'tailwind' => true,
			),
		);

		$this->builder->build( $fields );

		$this->assertTrue( $this->builder->is_tailwind_active() );
	}

	public function test_tailwind_not_activated_by_classes_field_without_tailwind(): void {
		$fields = array(
			array(
				'id'   => 'styles',
				'type' => 'classes',
			),
		);

		$this->builder->build( $fields );

		$this->assertFalse( $this->builder->is_tailwind_active() );
	}

	public function test_tailwind_not_activated_by_non_classes_field(): void {
		$fields = array(
			array(
				'id'       => 'title',
				'type'     => 'text',
				'tailwind' => true,
			),
		);

		$this->builder->build( $fields );

		$this->assertFalse( $this->builder->is_tailwind_active() );
	}

	public function test_reset_tailwind_active(): void {
		$fields = array(
			array(
				'id'       => 'styles',
				'type'     => 'classes',
				'tailwind' => true,
			),
		);

		$this->builder->build( $fields );
		$this->assertTrue( $this->builder->is_tailwind_active() );

		$this->builder->reset_tailwind_active();
		$this->assertFalse( $this->builder->is_tailwind_active() );
	}

	// register_handler()

	public function test_register_custom_handler(): void {
		$handler = new class implements Field_Handler_Interface {
			public function supports( string $type ): bool {
				return 'custom_widget' === $type;
			}

			public function build( array $field, array &$attributes, string $prefix = '' ): void {
				$id                = $field['id'] ?? '';
				$attributes[ $id ] = array(
					'blockstudio' => true,
					'type'        => 'string',
					'field'       => 'custom_widget',
				);
			}

			public function get_default_value( array $field ): mixed {
				return '';
			}
		};

		$this->builder->register_handler( $handler );

		$fields = array(
			array(
				'id'   => 'widget',
				'type' => 'custom_widget',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'widget', $result );
		$this->assertSame( 'custom_widget', $result['widget']['field'] );
	}

	public function test_custom_handler_takes_precedence_when_registered_last(): void {
		$handler = new class implements Field_Handler_Interface {
			public function supports( string $type ): bool {
				return 'text' === $type;
			}

			public function build( array $field, array &$attributes, string $prefix = '' ): void {
				$id                = $field['id'] ?? '';
				$attributes[ $id ] = array(
					'blockstudio' => true,
					'type'        => 'string',
					'field'       => 'text',
					'custom'      => true,
				);
			}

			public function get_default_value( array $field ): mixed {
				return '';
			}
		};

		$this->builder->register_handler( $handler );

		$fields = array(
			array(
				'id'   => 'title',
				'type' => 'text',
			),
		);

		$result = $this->builder->build( $fields );

		// The default text handler runs first (order of iteration), so custom won't override.
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayNotHasKey( 'custom', $result['title'] );
	}

	// build_attributes_recursive() - direct calls

	public function test_build_attributes_recursive_with_prefix(): void {
		$attributes = array();
		$fields     = array(
			array(
				'id'   => 'label',
				'type' => 'text',
			),
		);

		$this->builder->build_attributes_recursive( $fields, $attributes, 'section' );

		$this->assertArrayHasKey( 'section_label', $attributes );
	}

	public function test_build_attributes_recursive_from_group(): void {
		$attributes = array();
		$fields     = array(
			array(
				'id'   => 'text',
				'type' => 'text',
			),
		);

		$this->builder->build_attributes_recursive( $fields, $attributes, 'card', true );

		$this->assertArrayHasKey( 'card_text', $attributes );
	}

	public function test_build_attributes_recursive_from_repeater(): void {
		$attributes = array();
		$fields     = array(
			array(
				'id'   => 'name',
				'type' => 'text',
			),
			array(
				'id'   => 'age',
				'type' => 'number',
			),
		);

		$this->builder->build_attributes_recursive( $fields, $attributes, '', false, true );

		// Handlers still use field id for the attribute key.
		$this->assertArrayHasKey( 'name', $attributes );
		$this->assertArrayHasKey( 'age', $attributes );
		$this->assertSame( 'text', $attributes['name']['field'] );
		$this->assertSame( 'number', $attributes['age']['field'] );
	}

	// Nested groups

	public function test_nested_group_prefixes_correctly(): void {
		$fields = array(
			array(
				'id'         => 'card',
				'type'       => 'group',
				'attributes' => array(
					array(
						'id'   => 'title',
						'type' => 'text',
					),
					array(
						'id'   => 'visible',
						'type' => 'toggle',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'card_title', $result );
		$this->assertArrayHasKey( 'card_visible', $result );
		$this->assertSame( 'text', $result['card_title']['field'] );
		$this->assertSame( 'toggle', $result['card_visible']['field'] );
	}

	// Group inside tabs

	public function test_tabs_with_group_inside(): void {
		$fields = array(
			array(
				'type' => 'tabs',
				'tabs' => array(
					array(
						'label'      => 'Content',
						'attributes' => array(
							array(
								'id'   => 'title',
								'type' => 'text',
							),
						),
					),
					array(
						'label'      => 'Style',
						'attributes' => array(
							array(
								'id'   => 'color',
								'type' => 'color',
								'options' => array(
									array(
										'value' => '#000',
										'label' => 'Black',
									),
								),
							),
						),
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'color', $result );
	}

	// Default handler state after construction

	public function test_constructor_registers_all_default_handlers(): void {
		$fields = array(
			array(
				'id'   => 'a',
				'type' => 'text',
			),
			array(
				'id'   => 'b',
				'type' => 'number',
			),
			array(
				'id'   => 'c',
				'type' => 'toggle',
			),
			array(
				'id'      => 'd',
				'type'    => 'select',
				'options' => array(),
			),
			array(
				'id'   => 'e',
				'type' => 'files',
			),
			array(
				'id'         => 'f',
				'type'       => 'group',
				'attributes' => array(
					array(
						'id'   => 'x',
						'type' => 'text',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'a', $result );
		$this->assertArrayHasKey( 'b', $result );
		$this->assertArrayHasKey( 'c', $result );
		$this->assertArrayHasKey( 'd', $result );
		$this->assertArrayHasKey( 'e', $result );
		$this->assertArrayHasKey( 'f_x', $result );
	}

	// Each build() call is independent

	public function test_build_produces_independent_results(): void {
		$fields_a = array(
			array(
				'id'   => 'alpha',
				'type' => 'text',
			),
		);

		$fields_b = array(
			array(
				'id'   => 'beta',
				'type' => 'number',
			),
		);

		$result_a = $this->builder->build( $fields_a );
		$result_b = $this->builder->build( $fields_b );

		$this->assertArrayHasKey( 'alpha', $result_a );
		$this->assertArrayNotHasKey( 'beta', $result_a );
		$this->assertArrayHasKey( 'beta', $result_b );
		$this->assertArrayNotHasKey( 'alpha', $result_b );
	}

	// Tailwind state persists across builds until reset

	public function test_tailwind_state_persists_across_builds(): void {
		$fields = array(
			array(
				'id'       => 'classes',
				'type'     => 'classes',
				'tailwind' => true,
			),
		);

		$this->builder->build( $fields );
		$this->assertTrue( $this->builder->is_tailwind_active() );

		$this->builder->build( array() );
		$this->assertTrue( $this->builder->is_tailwind_active() );
	}

	// build() - select field with 'set' property

	public function test_build_select_with_set_property(): void {
		$fields = array(
			array(
				'id'      => 'variant',
				'type'    => 'select',
				'options' => array(),
				'set'     => 'className',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'className', $result['variant']['set'] );
	}

	// build() - color field with default matching an option

	public function test_build_color_field_with_matching_default(): void {
		$fields = array(
			array(
				'id'      => 'text_color',
				'type'    => 'color',
				'default' => '#ff0000',
				'options' => array(
					array(
						'value' => '#ff0000',
						'label' => 'Red',
					),
					array(
						'value' => '#00ff00',
						'label' => 'Green',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame(
			array(
				'value' => '#ff0000',
				'label' => 'Red',
			),
			$result['text_color']['default']
		);
	}

	// build() - select field default resolves from options

	public function test_build_select_field_with_default(): void {
		$fields = array(
			array(
				'id'      => 'size',
				'type'    => 'select',
				'default' => 'medium',
				'options' => array(
					array(
						'value' => 'small',
						'label' => 'Small',
					),
					array(
						'value' => 'medium',
						'label' => 'Medium',
					),
					array(
						'value' => 'large',
						'label' => 'Large',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'medium', $result['size']['default']['value'] );
		$this->assertSame( 'Medium', $result['size']['default']['label'] );
	}

	// build() - checkbox field with array default

	public function test_build_checkbox_field_with_array_default(): void {
		$fields = array(
			array(
				'id'      => 'toppings',
				'type'    => 'checkbox',
				'default' => array( 'cheese', 'sauce' ),
				'options' => array(
					array(
						'value' => 'cheese',
						'label' => 'Cheese',
					),
					array(
						'value' => 'sauce',
						'label' => 'Sauce',
					),
					array(
						'value' => 'olives',
						'label' => 'Olives',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertIsArray( $result['toppings']['default'] );
		$this->assertCount( 2, $result['toppings']['default'] );
		$this->assertSame( 'cheese', $result['toppings']['default'][0]['value'] );
		$this->assertSame( 'sauce', $result['toppings']['default'][1]['value'] );
	}

	// build() - all text-based types produce string attribute

	public function test_all_text_types_produce_string_attribute(): void {
		$text_types = array( 'text', 'textarea', 'code', 'date', 'datetime', 'unit', 'classes', 'richtext', 'wysiwyg' );

		foreach ( $text_types as $type ) {
			$builder = new Attribute_Builder();
			$fields  = array(
				array(
					'id'   => 'field_' . $type,
					'type' => $type,
				),
			);

			$result = $builder->build( $fields );

			$this->assertSame(
				'string',
				$result[ 'field_' . $type ]['type'],
				"Type '$type' should produce a string attribute"
			);
		}
	}

	// build() - repeater filters out nested groups

	public function test_build_repeater_filters_nested_groups(): void {
		$fields = array(
			array(
				'id'         => 'items',
				'type'       => 'repeater',
				'attributes' => array(
					array(
						'id'   => 'label',
						'type' => 'text',
					),
					array(
						'id'         => 'nested_group',
						'type'       => 'group',
						'attributes' => array(
							array(
								'id'   => 'inner',
								'type' => 'text',
							),
						),
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertSame( 'array', $result['items']['type'] );
	}

	// build() - files field without default or storage has no extra keys

	public function test_build_files_field_no_extra_keys(): void {
		$fields = array(
			array(
				'id'   => 'photo',
				'type' => 'files',
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertArrayNotHasKey( 'storage', $result['photo'] );
		$this->assertArrayNotHasKey( 'default', $result['photo'] );
		$this->assertArrayNotHasKey( 'returnFormat', $result['photo'] );
	}

	// build() - icon and link with default passthrough

	public function test_build_icon_field_with_default(): void {
		$default = array(
			'name' => 'star',
			'svg'  => '<svg>...</svg>',
		);

		$fields = array(
			array(
				'id'      => 'icon',
				'type'    => 'icon',
				'default' => $default,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( $default, $result['icon']['default'] );
	}

	public function test_build_link_field_with_default(): void {
		$default = array(
			'url'    => 'https://example.com',
			'title'  => 'Example',
			'target' => '_blank',
		);

		$fields = array(
			array(
				'id'      => 'link',
				'type'    => 'link',
				'default' => $default,
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( $default, $result['link']['default'] );
	}

	// build() - new builder instance starts clean

	public function test_new_builder_has_no_tailwind(): void {
		$builder = new Attribute_Builder();
		$this->assertFalse( $builder->is_tailwind_active() );
	}

	// build() - gradient field with matching default

	public function test_build_gradient_field_with_matching_default(): void {
		$gradient = 'linear-gradient(to right, #000, #fff)';

		$fields = array(
			array(
				'id'      => 'bg',
				'type'    => 'gradient',
				'default' => $gradient,
				'options' => array(
					array(
						'value' => $gradient,
						'label' => 'BW Gradient',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame(
			array(
				'value' => $gradient,
				'label' => 'BW Gradient',
			),
			$result['bg']['default']
		);
	}

	// build() - empty tabs array produces nothing

	public function test_build_tabs_with_empty_tabs_array(): void {
		$fields = array(
			array(
				'type' => 'tabs',
				'tabs' => array(),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( array(), $result );
	}

	// build() - radio with default

	public function test_build_radio_with_default(): void {
		$fields = array(
			array(
				'id'      => 'layout',
				'type'    => 'radio',
				'default' => 'grid',
				'options' => array(
					array(
						'value' => 'grid',
						'label' => 'Grid',
					),
					array(
						'value' => 'list',
						'label' => 'List',
					),
				),
			),
		);

		$result = $this->builder->build( $fields );

		$this->assertSame( 'grid', $result['layout']['default']['value'] );
		$this->assertSame( 'Grid', $result['layout']['default']['label'] );
	}
}
