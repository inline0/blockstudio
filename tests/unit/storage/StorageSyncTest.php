<?php

use Blockstudio\Storage_Sync;
use Blockstudio\Storage_Registry;
use Blockstudio\Storage_Handlers\Option_Storage;
use PHPUnit\Framework\TestCase;

class StorageSyncTest extends TestCase {

	private array $option_keys = array();

	protected function setUp(): void {
		$registry = Storage_Registry::instance();
		$registry->register_handler( new Option_Storage() );
	}

	protected function tearDown(): void {
		foreach ( $this->option_keys as $key ) {
			delete_option( $key );
		}
		$this->option_keys = array();
	}

	private function sync( array $fields, array $attrs ): void {
		$sync = new Storage_Sync();
		$ref  = new ReflectionMethod( $sync, 'sync_fields' );
		$ref->setAccessible( true );
		$ref->invoke( $sync, 1, 'test/block', $fields, $attrs );
	}

	public function test_sync_top_level_option_field(): void {
		$this->option_keys[] = 'test_sync_logo';

		$this->sync(
			array(
				array(
					'id'      => 'logo',
					'type'    => 'text',
					'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_logo' ),
				),
			),
			array( 'logo' => 'my-logo.png' )
		);

		$this->assertSame( 'my-logo.png', get_option( 'test_sync_logo' ) );
	}

	public function test_sync_field_nested_in_group(): void {
		$this->option_keys[] = 'test_sync_group_logo';

		$this->sync(
			array(
				array(
					'type'       => 'group',
					'attributes' => array(
						array(
							'id'      => 'logo',
							'type'    => 'text',
							'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_group_logo' ),
						),
					),
				),
			),
			array( 'logo' => 'group-logo.png' )
		);

		$this->assertSame( 'group-logo.png', get_option( 'test_sync_group_logo' ) );
	}

	public function test_sync_field_nested_in_tabs(): void {
		$this->option_keys[] = 'test_sync_tabs_title';

		$this->sync(
			array(
				array(
					'type' => 'tabs',
					'tabs' => array(
						array(
							'title'      => 'Content',
							'attributes' => array(
								array(
									'id'      => 'title',
									'type'    => 'text',
									'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_tabs_title' ),
								),
							),
						),
					),
				),
			),
			array( 'title' => 'Tab Title' )
		);

		$this->assertSame( 'Tab Title', get_option( 'test_sync_tabs_title' ) );
	}

	public function test_sync_field_nested_in_tabs_and_group(): void {
		$this->option_keys[] = 'test_sync_deep_logo';

		$this->sync(
			array(
				array(
					'type' => 'tabs',
					'tabs' => array(
						array(
							'title'      => 'Content',
							'attributes' => array(
								array(
									'type'       => 'group',
									'attributes' => array(
										array(
											'id'      => 'logo',
											'type'    => 'text',
											'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_deep_logo' ),
										),
									),
								),
							),
						),
					),
				),
			),
			array( 'logo' => 'deep-logo.png' )
		);

		$this->assertSame( 'deep-logo.png', get_option( 'test_sync_deep_logo' ) );
	}

	public function test_sync_field_nested_in_repeater(): void {
		$this->option_keys[] = 'test_sync_rep_label';

		$this->sync(
			array(
				array(
					'id'         => 'items',
					'type'       => 'repeater',
					'attributes' => array(
						array(
							'id'      => 'label',
							'type'    => 'text',
							'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_rep_label' ),
						),
					),
				),
			),
			array(
				'items' => array(
					array( 'label' => 'First label' ),
					array( 'label' => 'Second label' ),
				),
			)
		);

		$this->assertSame( array( 'First label', 'Second label' ), get_option( 'test_sync_rep_label' ) );
	}

	public function test_sync_field_nested_in_nested_repeater(): void {
		$this->option_keys[] = 'test_sync_nested_rep_label';

		$this->sync(
			array(
				array(
					'id'         => 'items',
					'type'       => 'repeater',
					'attributes' => array(
						array(
							'id'         => 'links',
							'type'       => 'repeater',
							'attributes' => array(
								array(
									'id'      => 'label',
									'type'    => 'text',
									'storage' => array( 'type' => 'option', 'optionKey' => 'test_sync_nested_rep_label' ),
								),
							),
						),
					),
				),
			),
			array(
				'items' => array(
					array(
						'links' => array(
							array( 'label' => 'One' ),
							array( 'label' => 'Two' ),
						),
					),
					array(
						'links' => array(
							array( 'label' => 'Three' ),
						),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array( 'One', 'Two' ),
				array( 'Three' ),
			),
			get_option( 'test_sync_nested_rep_label' )
		);
	}
}
