<?php

use Blockstudio\Database;
use Blockstudio\Db;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

	/**
	 * Record IDs created during tests, keyed by schema key.
	 */
	private array $created_ids = array();

	/**
	 * Post IDs created during CPT tests, cleaned up via wp_delete_post.
	 */
	private array $cpt_post_ids = array();

	protected function tearDown(): void {
		foreach ( $this->created_ids as $key => $ids ) {
			foreach ( $ids as $id ) {
				Database::execute( 'delete', $key, array( 'id' => $id ) );
			}
		}

		$this->created_ids = array();

		foreach ( $this->cpt_post_ids as $id ) {
			wp_delete_post( $id, true );
		}

		$this->cpt_post_ids = array();
	}

	private function track_record( string $key, array $record ): array {
		$this->created_ids[ $key ][] = $record['id'];
		return $record;
	}

	// Database::get_all()

	public function test_get_all_returns_array(): void {
		$schemas = Database::get_all();
		$this->assertIsArray( $schemas );
	}

	public function test_get_all_contains_table_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-table:default', $schemas );
	}

	public function test_get_all_contains_validation_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-validation:default', $schemas );
	}

	public function test_get_all_contains_jsonc_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-jsonc:default', $schemas );
	}

	public function test_get_all_contains_sqlite_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-sqlite:default', $schemas );
	}

	public function test_get_all_contains_multi_contacts_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-multi:contacts', $schemas );
	}

	public function test_get_all_contains_multi_notes_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-multi:notes', $schemas );
	}

	public function test_get_all_contains_builder_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-builder:default', $schemas );
	}

	public function test_get_all_contains_user_scoped_schema(): void {
		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-user-scoped:default', $schemas );
	}

	public function test_get_all_schema_has_fields(): void {
		$schemas = Database::get_all();
		$table   = $schemas['blockstudio/type-db-table:default'];

		$this->assertArrayHasKey( 'fields', $table );
		$this->assertArrayHasKey( 'title', $table['fields'] );
		$this->assertArrayHasKey( 'count', $table['fields'] );
		$this->assertArrayHasKey( 'price', $table['fields'] );
		$this->assertArrayHasKey( 'active', $table['fields'] );
	}

	public function test_get_all_schema_has_storage_type(): void {
		$schemas = Database::get_all();

		$this->assertSame( 'table', $schemas['blockstudio/type-db-table:default']['storage'] );
		$this->assertSame( 'jsonc', $schemas['blockstudio/type-db-jsonc:default']['storage'] );
		$this->assertSame( 'sqlite', $schemas['blockstudio/type-db-sqlite:default']['storage'] );
	}

	public function test_builder_schema_normalizes_field_builders(): void {
		$schemas  = Database::get_all();
		$builder  = $schemas['blockstudio/type-db-builder:default'];

		$this->assertSame( 'table', $builder['storage'] );
		$this->assertSame( 'string', $builder['fields']['title']['type'] );
		$this->assertTrue( $builder['fields']['title']['required'] );
		$this->assertSame( 'integer', $builder['fields']['count']['type'] );
		$this->assertSame( 0, $builder['fields']['count']['default'] );
		$this->assertSame( 'boolean', $builder['fields']['active']['type'] );
		$this->assertFalse( $builder['fields']['active']['default'] );
		$this->assertSame( 'text', $builder['fields']['notes']['type'] );
	}

	public function test_get_all_user_scoped_schema_has_user_id_field(): void {
		$schemas = Database::get_all();
		$scoped  = $schemas['blockstudio/type-db-user-scoped:default'];

		$this->assertArrayHasKey( 'user_id', $scoped['fields'] );
		$this->assertSame( 'integer', $scoped['fields']['user_id']['type'] );
	}

	// Database::has_schemas()

	public function test_has_schemas_returns_true(): void {
		$this->assertTrue( Database::has_schemas() );
	}

	// Db::get()

	public function test_db_get_returns_instance_for_valid_block(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$this->assertInstanceOf( Db::class, $db );
	}

	public function test_db_get_returns_instance_for_builder_block(): void {
		$db = Db::get( 'blockstudio/type-db-builder' );
		$this->assertInstanceOf( Db::class, $db );
	}

	public function test_db_get_returns_null_for_invalid_block(): void {
		$db = Db::get( 'nonexistent/block-name' );
		$this->assertNull( $db );
	}

	public function test_db_get_returns_null_for_invalid_schema(): void {
		$db = Db::get( 'blockstudio/type-db-table', 'nonexistent' );
		$this->assertNull( $db );
	}

	public function test_db_get_named_schema(): void {
		$db = Db::get( 'blockstudio/type-db-multi', 'contacts' );
		$this->assertInstanceOf( Db::class, $db );
	}

	public function test_db_get_second_named_schema(): void {
		$db = Db::get( 'blockstudio/type-db-multi', 'notes' );
		$this->assertInstanceOf( Db::class, $db );
	}

	// Table storage CRUD

	public function test_table_create_returns_record_with_id(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'PHPUnit test item' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'id', $record );
		$this->assertSame( 'PHPUnit test item', $record['title'] );
	}

	public function test_table_create_sets_timestamps(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Timestamps test' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$this->assertArrayHasKey( 'created_at', $record );
		$this->assertArrayHasKey( 'updated_at', $record );
		$this->assertNotEmpty( $record['created_at'] );
	}

	public function test_builder_schema_create_returns_record_with_defaults(): void {
		$db     = Db::get( 'blockstudio/type-db-builder' );
		$record = $db->create( array( 'title' => 'Builder test' ) );
		$this->track_record( 'blockstudio/type-db-builder:default', $record );

		$this->assertSame( 'Builder test', $record['title'] );
		$this->assertEquals( 0, $record['count'] );
		$this->assertEquals( 0, $record['active'] );
	}

	public function test_table_create_applies_defaults(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Defaults test' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$this->assertEquals( 0, $record['count'] );
		$this->assertEquals( 0, $record['active'] );
	}

	public function test_table_get_record(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Get test' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$fetched = $db->get_record( (int) $record['id'] );

		$this->assertIsArray( $fetched );
		$this->assertEquals( $record['id'], $fetched['id'] );
		$this->assertSame( 'Get test', $fetched['title'] );
	}

	public function test_table_get_record_nonexistent_returns_null(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$this->assertNull( $db->get_record( 999999 ) );
	}

	public function test_table_update(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Before update' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$updated = $db->update( (int) $record['id'], array( 'title' => 'After update' ) );

		$this->assertIsArray( $updated );
		$this->assertSame( 'After update', $updated['title'] );
		$this->assertEquals( $record['id'], $updated['id'] );
	}

	public function test_table_update_nonexistent_returns_null(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$this->assertNull( $db->update( 999999, array( 'title' => 'nope' ) ) );
	}

	public function test_table_delete(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Delete me' ) );

		$deleted = $db->delete( (int) $record['id'] );
		$this->assertTrue( $deleted );

		$this->assertNull( $db->get_record( (int) $record['id'] ) );
	}

	public function test_table_delete_nonexistent_returns_false(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$this->assertFalse( $db->delete( 999999 ) );
	}

	public function test_table_list_returns_array(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$this->assertIsArray( $db->list() );
	}

	public function test_table_list_includes_created_records(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$r1 = $db->create( array( 'title' => 'List item A' ) );
		$r2 = $db->create( array( 'title' => 'List item B' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $r1 );
		$this->track_record( 'blockstudio/type-db-table:default', $r2 );

		$list = $db->list();
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $r1['id'], $ids );
		$this->assertContains( $r2['id'], $ids );
	}

	public function test_table_list_with_filter(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$r1 = $db->create( array( 'title' => 'Filter A', 'count' => 10 ) );
		$r2 = $db->create( array( 'title' => 'Filter B', 'count' => 20 ) );
		$this->track_record( 'blockstudio/type-db-table:default', $r1 );
		$this->track_record( 'blockstudio/type-db-table:default', $r2 );

		$list = $db->list( array( 'count' => '10' ) );
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $r1['id'], $ids );
		$this->assertNotContains( $r2['id'], $ids );
	}

	public function test_table_list_with_limit(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$r1 = $db->create( array( 'title' => 'Limit 1' ) );
		$r2 = $db->create( array( 'title' => 'Limit 2' ) );
		$r3 = $db->create( array( 'title' => 'Limit 3' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $r1 );
		$this->track_record( 'blockstudio/type-db-table:default', $r2 );
		$this->track_record( 'blockstudio/type-db-table:default', $r3 );

		$list = $db->list( array(), 2 );
		$this->assertLessThanOrEqual( 2, count( $list ) );
	}

	public function test_table_list_with_offset(): void {
		$db = Db::get( 'blockstudio/type-db-table' );
		$r1 = $db->create( array( 'title' => 'Offset 1' ) );
		$r2 = $db->create( array( 'title' => 'Offset 2' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $r1 );
		$this->track_record( 'blockstudio/type-db-table:default', $r2 );

		$all  = $db->list( array(), 100 );
		$skip = $db->list( array(), 100, 1 );

		$this->assertLessThan( count( $all ), count( $skip ) );
	}

	// Validation: required fields

	public function test_validation_required_field_missing(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$result = $db->create( array( 'count' => 5 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'blockstudio_db_validation', $result->get_error_code() );
	}

	public function test_validation_required_field_error_has_field_details(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$result = $db->create( array( 'count' => 5 ) );

		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'errors', $data );
		$this->assertArrayHasKey( 'title', $data['errors'] );
		$this->assertSame( 'Required.', $data['errors']['title'][0] );
	}

	public function test_validation_required_field_not_checked_on_partial_update(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Partial update test' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$updated = $db->update( (int) $record['id'], array( 'count' => 42 ) );

		$this->assertIsArray( $updated );
		$this->assertEquals( 42, $updated['count'] );
	}

	// Validation: type checking

	public function test_validation_integer_type_rejects_non_numeric(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email' => 'test@example.com',
				'age'   => 'not-a-number',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'age', $data['errors'] );
		$this->assertSame( 'Must be an integer.', $data['errors']['age'][0] );
	}

	public function test_validation_number_type_rejects_non_numeric(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email' => 'test@example.com',
				'score' => 'not-a-number',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'score', $data['errors'] );
		$this->assertSame( 'Must be a number.', $data['errors']['score'][0] );
	}

	public function test_validation_boolean_type_rejects_non_boolean(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email'    => 'test@example.com',
				'verified' => 'yes',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'verified', $data['errors'] );
		$this->assertSame( 'Must be a boolean.', $data['errors']['verified'][0] );
	}

	public function test_validation_string_type_rejects_array(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email'    => 'test@example.com',
				'username' => array( 'not', 'a', 'string' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'username', $data['errors'] );
		$this->assertSame( 'Must be a string.', $data['errors']['username'][0] );
	}

	// Validation: enum

	public function test_validation_enum_rejects_invalid_value(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email' => 'test@example.com',
				'role'  => 'superadmin',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'role', $data['errors'] );
		$this->assertStringContainsString( 'Must be one of:', $data['errors']['role'][0] );
	}

	public function test_validation_enum_accepts_valid_value(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$record = $db->create(
			array(
				'email' => 'enum-test@example.com',
				'role'  => 'editor',
			)
		);
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$this->assertIsArray( $record );
		$this->assertSame( 'editor', $record['role'] );
	}

	// Validation: maxLength and minLength

	public function test_validation_max_length_rejects_long_string(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email'    => 'test@example.com',
				'username' => 'a_very_long_username_that_exceeds_twenty_chars',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'username', $data['errors'] );
		$this->assertStringContainsString( 'maximum length of 20', $data['errors']['username'][0] );
	}

	public function test_validation_min_length_rejects_short_string(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email'    => 'test@example.com',
				'username' => 'ab',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'username', $data['errors'] );
		$this->assertStringContainsString( 'at least 3 characters', $data['errors']['username'][0] );
	}

	// Validation: email format

	public function test_validation_email_format_rejects_invalid(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create( array( 'email' => 'not-an-email' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'email', $data['errors'] );
		$this->assertStringContainsString( 'valid email', $data['errors']['email'][0] );
	}

	public function test_validation_email_format_accepts_valid(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$record = $db->create( array( 'email' => 'valid@example.com' ) );
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$this->assertIsArray( $record );
	}

	// Validation: URL format

	public function test_validation_url_format_rejects_invalid(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'email' => 'test@example.com',
				'url'   => 'not a url',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'url', $data['errors'] );
		$this->assertStringContainsString( 'valid URL', $data['errors']['url'][0] );
	}

	// Validation: custom callback

	public function test_validation_custom_callback_rejects_banned_domain(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create( array( 'email' => 'test@banned.com' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'email', $data['errors'] );
		$this->assertStringContainsString( 'not allowed', $data['errors']['email'][0] );
	}

	// Validation: multiple errors

	public function test_validation_returns_multiple_field_errors(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$result = $db->create(
			array(
				'age'   => 'not-a-number',
				'score' => 'also-not-a-number',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();

		// Missing required email.
		$this->assertArrayHasKey( 'email', $data['errors'] );
		// Invalid types.
		$this->assertArrayHasKey( 'age', $data['errors'] );
		$this->assertArrayHasKey( 'score', $data['errors'] );
	}

	// WP_Error format

	public function test_error_has_status_400(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$result = $db->create( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertSame( 400, $data['status'] );
	}

	public function test_error_message_is_validation_failed(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$result = $db->create( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'Validation failed.', $result->get_error_message() );
	}

	// SQLite storage CRUD

	public function test_sqlite_create_and_get(): void {
		$db     = Db::get( 'blockstudio/type-db-sqlite' );
		$record = $db->create( array( 'title' => 'SQLite test' ) );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $record );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'id', $record );

		$fetched = $db->get_record( (int) $record['id'] );
		$this->assertSame( 'SQLite test', $fetched['title'] );
	}

	public function test_sqlite_update(): void {
		$db     = Db::get( 'blockstudio/type-db-sqlite' );
		$record = $db->create( array( 'title' => 'SQLite before' ) );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $record );

		$updated = $db->update( (int) $record['id'], array( 'title' => 'SQLite after' ) );
		$this->assertSame( 'SQLite after', $updated['title'] );
	}

	public function test_sqlite_delete(): void {
		$db     = Db::get( 'blockstudio/type-db-sqlite' );
		$record = $db->create( array( 'title' => 'SQLite delete me' ) );

		$this->assertTrue( $db->delete( (int) $record['id'] ) );
		$this->assertNull( $db->get_record( (int) $record['id'] ) );
	}

	public function test_sqlite_list(): void {
		$db = Db::get( 'blockstudio/type-db-sqlite' );
		$r1 = $db->create( array( 'title' => 'SQLite list 1' ) );
		$r2 = $db->create( array( 'title' => 'SQLite list 2' ) );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $r1 );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $r2 );

		$list = $db->list();
		$ids  = array_column( $list, 'id' );

		$this->assertContains( (int) $r1['id'], array_map( 'intval', $ids ) );
		$this->assertContains( (int) $r2['id'], array_map( 'intval', $ids ) );
	}

	public function test_sqlite_list_with_filter(): void {
		$db = Db::get( 'blockstudio/type-db-sqlite' );
		$r1 = $db->create( array( 'title' => 'SQLite filter A', 'count' => 100 ) );
		$r2 = $db->create( array( 'title' => 'SQLite filter B', 'count' => 200 ) );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $r1 );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $r2 );

		$list = $db->list( array( 'count' => 100 ) );
		$ids  = array_map( 'intval', array_column( $list, 'id' ) );

		$this->assertContains( (int) $r1['id'], $ids );
		$this->assertNotContains( (int) $r2['id'], $ids );
	}

	// JSONC storage CRUD

	public function test_jsonc_create_and_get(): void {
		$db     = Db::get( 'blockstudio/type-db-jsonc' );
		$record = $db->create( array( 'action' => 'JSONC test action' ) );
		$this->track_record( 'blockstudio/type-db-jsonc:default', $record );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'id', $record );

		$fetched = $db->get_record( (int) $record['id'] );
		$this->assertSame( 'JSONC test action', $fetched['action'] );
	}

	public function test_jsonc_update(): void {
		$db     = Db::get( 'blockstudio/type-db-jsonc' );
		$record = $db->create( array( 'action' => 'JSONC before' ) );
		$this->track_record( 'blockstudio/type-db-jsonc:default', $record );

		$updated = $db->update( (int) $record['id'], array( 'action' => 'JSONC after' ) );
		$this->assertSame( 'JSONC after', $updated['action'] );
	}

	public function test_jsonc_delete(): void {
		$db     = Db::get( 'blockstudio/type-db-jsonc' );
		$record = $db->create( array( 'action' => 'JSONC delete me' ) );

		$this->assertTrue( $db->delete( (int) $record['id'] ) );
		$this->assertNull( $db->get_record( (int) $record['id'] ) );
	}

	public function test_jsonc_list(): void {
		$db = Db::get( 'blockstudio/type-db-jsonc' );
		$r1 = $db->create( array( 'action' => 'JSONC list 1' ) );
		$r2 = $db->create( array( 'action' => 'JSONC list 2' ) );
		$this->track_record( 'blockstudio/type-db-jsonc:default', $r1 );
		$this->track_record( 'blockstudio/type-db-jsonc:default', $r2 );

		$list    = $db->list();
		$actions = array_column( $list, 'action' );

		$this->assertContains( 'JSONC list 1', $actions );
		$this->assertContains( 'JSONC list 2', $actions );
	}

	// Multi-schema block

	public function test_multi_contacts_create(): void {
		$db     = Db::get( 'blockstudio/type-db-multi', 'contacts' );
		$record = $db->create( array( 'name' => 'Multi contact' ) );
		$this->track_record( 'blockstudio/type-db-multi:contacts', $record );

		$this->assertIsArray( $record );
		$this->assertSame( 'Multi contact', $record['name'] );
	}

	public function test_multi_notes_create(): void {
		$db     = Db::get( 'blockstudio/type-db-multi', 'notes' );
		$record = $db->create( array( 'body' => 'Multi note body' ) );
		$this->track_record( 'blockstudio/type-db-multi:notes', $record );

		$this->assertIsArray( $record );
		$this->assertSame( 'Multi note body', $record['body'] );
	}

	public function test_multi_schemas_are_independent(): void {
		$contacts = Db::get( 'blockstudio/type-db-multi', 'contacts' );
		$notes    = Db::get( 'blockstudio/type-db-multi', 'notes' );

		$c = $contacts->create( array( 'name' => 'Independence test' ) );
		$n = $notes->create( array( 'body' => 'Independence note' ) );
		$this->track_record( 'blockstudio/type-db-multi:contacts', $c );
		$this->track_record( 'blockstudio/type-db-multi:notes', $n );

		$contact_list = $contacts->list();
		$note_list    = $notes->list();

		$contact_names = array_column( $contact_list, 'name' );
		$note_bodies   = array_column( $note_list, 'body' );

		$this->assertContains( 'Independence test', $contact_names );
		$this->assertNotContains( 'Independence note', $contact_names );
		$this->assertContains( 'Independence note', $note_bodies );
		$this->assertNotContains( 'Independence test', $note_bodies );
	}

	// Database::execute() direct API

	public function test_execute_create(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array( 'title' => 'Execute create' ) )
		);
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$this->assertIsArray( $record );
		$this->assertSame( 'Execute create', $record['title'] );
	}

	public function test_execute_list(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array( 'title' => 'Execute list' ) )
		);
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$list = Database::execute(
			'list',
			'blockstudio/type-db-table:default'
		);

		$this->assertIsArray( $list );
		$ids = array_column( $list, 'id' );
		$this->assertContains( $record['id'], $ids );
	}

	public function test_execute_get(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array( 'title' => 'Execute get' ) )
		);
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$fetched = Database::execute(
			'get',
			'blockstudio/type-db-table:default',
			array( 'id' => (int) $record['id'] )
		);

		$this->assertIsArray( $fetched );
		$this->assertEquals( $record['id'], $fetched['id'] );
	}

	public function test_execute_update(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array( 'title' => 'Execute update before' ) )
		);
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$updated = Database::execute(
			'update',
			'blockstudio/type-db-table:default',
			array(
				'id'   => (int) $record['id'],
				'data' => array( 'title' => 'Execute update after' ),
			)
		);

		$this->assertIsArray( $updated );
		$this->assertSame( 'Execute update after', $updated['title'] );
	}

	public function test_execute_delete(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array( 'title' => 'Execute delete' ) )
		);

		$deleted = Database::execute(
			'delete',
			'blockstudio/type-db-table:default',
			array( 'id' => (int) $record['id'] )
		);

		$this->assertTrue( $deleted );
	}

	public function test_execute_with_invalid_key_returns_fallback(): void {
		$get_result    = Database::execute( 'get', 'nonexistent/block:default' );
		$list_result   = Database::execute( 'list', 'nonexistent/block:default' );
		$delete_result = Database::execute( 'delete', 'nonexistent/block:default' );

		$this->assertNull( $get_result );
		$this->assertSame( array(), $list_result );
		$this->assertFalse( $delete_result );
	}

	public function test_execute_unknown_operation_returns_false(): void {
		$result = Database::execute( 'unknown_op', 'blockstudio/type-db-table:default' );
		$this->assertFalse( $result );
	}

	public function test_execute_create_validates_data(): void {
		$result = Database::execute(
			'create',
			'blockstudio/type-db-table:default',
			array( 'data' => array() )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_execute_update_validates_data(): void {
		$record = Database::execute(
			'create',
			'blockstudio/type-db-validation:default',
			array( 'data' => array( 'email' => 'exec-update@example.com' ) )
		);
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$result = Database::execute(
			'update',
			'blockstudio/type-db-validation:default',
			array(
				'id'   => (int) $record['id'],
				'data' => array( 'age' => 'not-a-number' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// Data sanitization

	public function test_sanitize_strips_unknown_fields(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create(
			array(
				'title'         => 'Sanitize test',
				'unknown_field' => 'should be stripped',
			)
		);
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$this->assertArrayNotHasKey( 'unknown_field', $record );
	}

	public function test_sanitize_casts_integer_type(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Cast int', 'count' => '42' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		// The value should be stored and returned (may be string from DB, but was cast before insert).
		$fetched = $db->get_record( (int) $record['id'] );
		$this->assertEquals( 42, $fetched['count'] );
	}

	public function test_sanitize_casts_number_type(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Cast num', 'price' => '19.99' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		$fetched = $db->get_record( (int) $record['id'] );
		$this->assertEqualsWithDelta( 19.99, (float) $fetched['price'], 0.01 );
	}

	// CRUD lifecycle: create, read, update, delete sequence

	public function test_full_crud_lifecycle(): void {
		$db = Db::get( 'blockstudio/type-db-table' );

		// Create.
		$record = $db->create( array( 'title' => 'Lifecycle item', 'count' => 1 ) );
		$id     = (int) $record['id'];
		$this->assertSame( 'Lifecycle item', $record['title'] );

		// Read.
		$fetched = $db->get_record( $id );
		$this->assertSame( 'Lifecycle item', $fetched['title'] );

		// Update.
		$updated = $db->update( $id, array( 'title' => 'Updated lifecycle', 'count' => 2 ) );
		$this->assertSame( 'Updated lifecycle', $updated['title'] );
		$this->assertEquals( 2, $updated['count'] );

		// Verify update persisted.
		$re_fetched = $db->get_record( $id );
		$this->assertSame( 'Updated lifecycle', $re_fetched['title'] );

		// Delete.
		$this->assertTrue( $db->delete( $id ) );
		$this->assertNull( $db->get_record( $id ) );

		// Double delete returns false.
		$this->assertFalse( $db->delete( $id ) );
	}

	// Db::list() always returns array

	public function test_db_list_returns_array_even_on_empty(): void {
		$db   = Db::get( 'blockstudio/type-db-table' );
		$list = $db->list( array( 'title' => 'absolutely_nonexistent_value_' . uniqid() ) );

		$this->assertIsArray( $list );
		$this->assertEmpty( $list );
	}

	// Multi-schema JSONC: notes

	public function test_multi_notes_jsonc_crud(): void {
		$db = Db::get( 'blockstudio/type-db-multi', 'notes' );

		$record = $db->create( array( 'body' => 'JSONC note via multi' ) );
		$this->track_record( 'blockstudio/type-db-multi:notes', $record );

		$fetched = $db->get_record( (int) $record['id'] );
		$this->assertSame( 'JSONC note via multi', $fetched['body'] );

		$updated = $db->update( (int) $record['id'], array( 'body' => 'Updated JSONC note' ) );
		$this->assertSame( 'Updated JSONC note', $updated['body'] );
	}

	// SQLite timestamps

	public function test_sqlite_timestamps(): void {
		$db     = Db::get( 'blockstudio/type-db-sqlite' );
		$record = $db->create( array( 'title' => 'SQLite timestamps' ) );
		$this->track_record( 'blockstudio/type-db-sqlite:default', $record );

		$this->assertArrayHasKey( 'created_at', $record );
		$this->assertArrayHasKey( 'updated_at', $record );
		$this->assertNotEmpty( $record['created_at'] );
	}

	// JSONC timestamps

	public function test_jsonc_timestamps(): void {
		$db     = Db::get( 'blockstudio/type-db-jsonc' );
		$record = $db->create( array( 'action' => 'JSONC timestamps' ) );
		$this->track_record( 'blockstudio/type-db-jsonc:default', $record );

		$this->assertArrayHasKey( 'created_at', $record );
		$this->assertArrayHasKey( 'updated_at', $record );
	}

	// Update changes updated_at timestamp

	public function test_table_update_changes_updated_at(): void {
		$db     = Db::get( 'blockstudio/type-db-table' );
		$record = $db->create( array( 'title' => 'Timestamp update test' ) );
		$this->track_record( 'blockstudio/type-db-table:default', $record );

		// Small delay so timestamp can differ.
		usleep( 1100000 );

		$updated = $db->update( (int) $record['id'], array( 'title' => 'Timestamp updated' ) );
		$this->assertGreaterThanOrEqual( $record['updated_at'], $updated['updated_at'] );
	}

	// Validation accepts numeric strings for integer/number fields

	public function test_validation_accepts_numeric_string_for_integer(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$record = $db->create(
			array(
				'email' => 'numeric-int@example.com',
				'age'   => '25',
			)
		);
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$this->assertIsArray( $record );
	}

	public function test_validation_accepts_numeric_string_for_number(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$record = $db->create(
			array(
				'email' => 'numeric-num@example.com',
				'score' => '9.5',
			)
		);
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$this->assertIsArray( $record );
	}

	// Validation: valid username passes

	public function test_validation_username_within_bounds(): void {
		$db     = Db::get( 'blockstudio/type-db-validation' );
		$record = $db->create(
			array(
				'email'    => 'bounds@example.com',
				'username' => 'validname',
			)
		);
		$this->track_record( 'blockstudio/type-db-validation:default', $record );

		$this->assertIsArray( $record );
	}

	// Post type (CPT) storage

	private function track_cpt_post( array $record ): array {
		$this->cpt_post_ids[] = $record['id'];
		return $record;
	}

	public function test_cpt_registered(): void {
		Database::register_post_types();

		$schemas = Database::get_all();
		$this->assertArrayHasKey( 'blockstudio/type-db-post-type:default', $schemas );
		$this->assertSame( 'post_type', $schemas['blockstudio/type-db-post-type:default']['storage'] );

		// The CPT name is derived from the schema key. Verify it exists.
		$this->assertTrue( post_type_exists( 'bs_type_db_post_type' ) || $this->cpt_exists_by_hash() );
	}

	private function cpt_exists_by_hash(): bool {
		$key  = 'blockstudio/type-db-post-type:default';
		$safe = str_replace( array( '/', '-', ':' ), '_', $key );
		$name = 'bs_' . $safe;

		if ( strlen( $name ) > 20 ) {
			$name = 'bs_' . substr( md5( $key ), 0, 17 );
		}

		return post_type_exists( $name );
	}

	public function test_cpt_create(): void {
		$db     = Db::get( 'blockstudio/type-db-post-type' );
		$record = $db->create( array( 'title' => 'CPT create test' ) );
		$this->track_record( 'blockstudio/type-db-post-type:default', $record );
		$this->track_cpt_post( $record );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'id', $record );
		$this->assertArrayHasKey( 'created_at', $record );
		$this->assertArrayHasKey( 'updated_at', $record );
		$this->assertNotEmpty( $record['created_at'] );
		$this->assertSame( 'CPT create test', $record['title'] );
		$this->assertSame( 'draft', $record['status'] );
	}

	public function test_cpt_get(): void {
		$db     = Db::get( 'blockstudio/type-db-post-type' );
		$record = $db->create( array( 'title' => 'CPT get test' ) );
		$this->track_record( 'blockstudio/type-db-post-type:default', $record );
		$this->track_cpt_post( $record );

		$fetched = $db->get_record( (int) $record['id'] );

		$this->assertIsArray( $fetched );
		$this->assertEquals( $record['id'], $fetched['id'] );
		$this->assertSame( 'CPT get test', $fetched['title'] );
	}

	public function test_cpt_list(): void {
		$db = Db::get( 'blockstudio/type-db-post-type' );
		$r1 = $db->create( array( 'title' => 'CPT list A' ) );
		$r2 = $db->create( array( 'title' => 'CPT list B' ) );
		$this->track_record( 'blockstudio/type-db-post-type:default', $r1 );
		$this->track_record( 'blockstudio/type-db-post-type:default', $r2 );
		$this->track_cpt_post( $r1 );
		$this->track_cpt_post( $r2 );

		$list = $db->list();
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $r1['id'], $ids );
		$this->assertContains( $r2['id'], $ids );
	}

	public function test_cpt_list_with_filter(): void {
		$db = Db::get( 'blockstudio/type-db-post-type' );
		$r1 = $db->create( array( 'title' => 'CPT filter A', 'status' => 'draft' ) );
		$r2 = $db->create( array( 'title' => 'CPT filter B', 'status' => 'published' ) );
		$this->track_record( 'blockstudio/type-db-post-type:default', $r1 );
		$this->track_record( 'blockstudio/type-db-post-type:default', $r2 );
		$this->track_cpt_post( $r1 );
		$this->track_cpt_post( $r2 );

		$list = $db->list( array( 'status' => 'published' ) );
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $r2['id'], $ids );
		$this->assertNotContains( $r1['id'], $ids );
	}

	public function test_cpt_update(): void {
		$db     = Db::get( 'blockstudio/type-db-post-type' );
		$record = $db->create( array( 'title' => 'CPT before update' ) );
		$this->track_record( 'blockstudio/type-db-post-type:default', $record );
		$this->track_cpt_post( $record );

		$updated = $db->update( (int) $record['id'], array( 'title' => 'CPT after update' ) );

		$this->assertIsArray( $updated );
		$this->assertSame( 'CPT after update', $updated['title'] );
		$this->assertEquals( $record['id'], $updated['id'] );
	}

	public function test_cpt_delete(): void {
		$db     = Db::get( 'blockstudio/type-db-post-type' );
		$record = $db->create( array( 'title' => 'CPT delete me' ) );

		$deleted = $db->delete( (int) $record['id'] );
		$this->assertTrue( $deleted );

		$this->assertNull( $db->get_record( (int) $record['id'] ) );
	}

	public function test_cpt_delete_nonexistent(): void {
		$db = Db::get( 'blockstudio/type-db-post-type' );
		$this->assertFalse( $db->delete( 999999 ) );
	}

	public function test_cpt_fields_stored_as_post_meta(): void {
		$db     = Db::get( 'blockstudio/type-db-post-type' );
		$record = $db->create(
			array(
				'title'  => 'CPT meta test',
				'status' => 'published',
			)
		);
		$this->track_record( 'blockstudio/type-db-post-type:default', $record );
		$this->track_cpt_post( $record );

		$post_id = (int) $record['id'];

		$this->assertSame( 'CPT meta test', get_post_meta( $post_id, 'title', true ) );
		$this->assertSame( 'published', get_post_meta( $post_id, 'status', true ) );
	}
}
