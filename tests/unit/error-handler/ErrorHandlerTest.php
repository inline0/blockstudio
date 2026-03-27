<?php

use Blockstudio\Error_Handler;
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase {

	protected function setUp(): void {
		Error_Handler::disable_debug();
		remove_all_actions( 'blockstudio/error/logged' );
		remove_all_actions( 'blockstudio/error/exception' );
	}

	protected function tearDown(): void {
		Error_Handler::disable_debug();
		remove_all_actions( 'blockstudio/error/logged' );
		remove_all_actions( 'blockstudio/error/exception' );
	}

	// Constants

	public function test_level_debug_constant(): void {
		$this->assertSame( 'debug', Error_Handler::LEVEL_DEBUG );
	}

	public function test_level_info_constant(): void {
		$this->assertSame( 'info', Error_Handler::LEVEL_INFO );
	}

	public function test_level_warning_constant(): void {
		$this->assertSame( 'warning', Error_Handler::LEVEL_WARNING );
	}

	public function test_level_error_constant(): void {
		$this->assertSame( 'error', Error_Handler::LEVEL_ERROR );
	}

	// Debug mode toggling

	public function test_enable_debug_enables_debug_mode(): void {
		Error_Handler::enable_debug();

		$ref  = new ReflectionClass( Error_Handler::class );
		$prop = $ref->getProperty( 'debug_mode' );
		$prop->setAccessible( true );

		$this->assertTrue( $prop->getValue() );
	}

	public function test_disable_debug_disables_debug_mode(): void {
		Error_Handler::enable_debug();
		Error_Handler::disable_debug();

		$ref  = new ReflectionClass( Error_Handler::class );
		$prop = $ref->getProperty( 'debug_mode' );
		$prop->setAccessible( true );

		$this->assertFalse( $prop->getValue() );
	}

	// should_log() behavior

	public function test_error_level_always_logs(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'should_log' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, Error_Handler::LEVEL_ERROR ) );
	}

	public function test_warning_level_always_logs(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'should_log' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, Error_Handler::LEVEL_WARNING ) );
	}

	public function test_debug_level_logs_when_debug_mode_enabled(): void {
		Error_Handler::enable_debug();

		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'should_log' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, Error_Handler::LEVEL_DEBUG ) );
	}

	public function test_info_level_logs_when_debug_mode_enabled(): void {
		Error_Handler::enable_debug();

		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'should_log' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, Error_Handler::LEVEL_INFO ) );
	}

	public function test_debug_level_logs_when_wp_debug_is_true(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'should_log' );
		$method->setAccessible( true );

		// WP_DEBUG is typically true in wp-env test environment.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->assertTrue( $method->invoke( null, Error_Handler::LEVEL_DEBUG ) );
		} else {
			$this->assertFalse( $method->invoke( null, Error_Handler::LEVEL_DEBUG ) );
		}
	}

	// format_message()

	public function test_format_message_includes_prefix(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Test message', 'error', array() );
		$this->assertStringContainsString( '[Blockstudio]', $result );
	}

	public function test_format_message_includes_timestamp(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Test', 'error', array() );
		$this->assertMatchesRegularExpression( '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $result );
	}

	public function test_format_message_includes_uppercased_level(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Test', 'warning', array() );
		$this->assertStringContainsString( '[WARNING]', $result );
	}

	public function test_format_message_includes_message_text(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Block registration failed', 'error', array() );
		$this->assertStringContainsString( 'Block registration failed', $result );
	}

	public function test_format_message_without_context_has_no_context_suffix(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Test', 'error', array() );
		$this->assertStringNotContainsString( '| Context:', $result );
	}

	public function test_format_message_with_context_appends_json(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'Test', 'error', array( 'path' => '/test' ) );
		$this->assertStringContainsString( '| Context:', $result );
		$this->assertStringContainsString( '"path"', $result );
		$this->assertStringContainsString( 'test', $result );
	}

	public function test_format_message_all_levels(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_message' );
		$method->setAccessible( true );

		$levels = array(
			'debug'   => 'DEBUG',
			'info'    => 'INFO',
			'warning' => 'WARNING',
			'error'   => 'ERROR',
		);

		foreach ( $levels as $level => $expected ) {
			$result = $method->invoke( null, 'Test', $level, array() );
			$this->assertStringContainsString( "[{$expected}]", $result );
		}
	}

	// format_exception()

	public function test_format_exception_includes_class_name(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_exception' );
		$method->setAccessible( true );

		$exception = new \RuntimeException( 'Something broke' );
		$result    = $method->invoke( null, $exception, '' );

		$this->assertStringContainsString( 'RuntimeException', $result );
	}

	public function test_format_exception_includes_message(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_exception' );
		$method->setAccessible( true );

		$exception = new \RuntimeException( 'Something broke' );
		$result    = $method->invoke( null, $exception, '' );

		$this->assertStringContainsString( 'Something broke', $result );
	}

	public function test_format_exception_includes_file_and_line(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_exception' );
		$method->setAccessible( true );

		$exception = new \RuntimeException( 'Test' );
		$result    = $method->invoke( null, $exception, '' );

		$this->assertStringContainsString( $exception->getFile(), $result );
		$this->assertStringContainsString( (string) $exception->getLine(), $result );
	}

	public function test_format_exception_with_context_prepends_context(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_exception' );
		$method->setAccessible( true );

		$exception = new \RuntimeException( 'Test' );
		$result    = $method->invoke( null, $exception, 'SCSS Compilation' );

		$this->assertStringContainsString( '[SCSS Compilation]', $result );
	}

	public function test_format_exception_without_context_has_no_brackets(): void {
		$ref    = new ReflectionClass( Error_Handler::class );
		$method = $ref->getMethod( 'format_exception' );
		$method->setAccessible( true );

		$exception = new \RuntimeException( 'Test' );
		$result    = $method->invoke( null, $exception, '' );

		$this->assertStringStartsWith( 'Exception [RuntimeException]', $result );
	}

	// log() fires action

	public function test_log_fires_logged_action_for_error(): void {
		$captured = array();
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured ) {
			$captured = array(
				'message' => $message,
				'level'   => $level,
				'context' => $context,
			);
		}, 10, 3 );

		Error_Handler::log( 'Test error', Error_Handler::LEVEL_ERROR );

		$this->assertSame( 'Test error', $captured['message'] );
		$this->assertSame( 'error', $captured['level'] );
		$this->assertSame( array(), $captured['context'] );
	}

	public function test_log_fires_logged_action_for_warning(): void {
		$fired = false;
		add_action( 'blockstudio/error/logged', function () use ( &$fired ) {
			$fired = true;
		} );

		Error_Handler::log( 'Warn', Error_Handler::LEVEL_WARNING );

		$this->assertTrue( $fired );
	}

	public function test_log_fires_logged_action_with_context(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::log( 'Test', Error_Handler::LEVEL_ERROR, array( 'key' => 'value' ) );

		$this->assertSame( array( 'key' => 'value' ), $captured_context );
	}

	public function test_log_does_not_fire_action_for_debug_without_debug_mode(): void {
		Error_Handler::disable_debug();

		// Only skip if WP_DEBUG is also off.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->markTestSkipped( 'WP_DEBUG is enabled in this environment.' );
		}

		$fired = false;
		add_action( 'blockstudio/error/logged', function () use ( &$fired ) {
			$fired = true;
		} );

		Error_Handler::log( 'Debug msg', Error_Handler::LEVEL_DEBUG );

		$this->assertFalse( $fired );
	}

	public function test_log_fires_action_for_debug_with_debug_mode(): void {
		Error_Handler::enable_debug();

		$fired = false;
		add_action( 'blockstudio/error/logged', function () use ( &$fired ) {
			$fired = true;
		} );

		Error_Handler::log( 'Debug msg', Error_Handler::LEVEL_DEBUG );

		$this->assertTrue( $fired );
	}

	// Convenience methods

	public function test_error_method_fires_at_error_level(): void {
		$captured_level = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level ) use ( &$captured_level ) {
			$captured_level = $level;
		}, 10, 2 );

		Error_Handler::error( 'Critical failure' );

		$this->assertSame( 'error', $captured_level );
	}

	public function test_warning_method_fires_at_warning_level(): void {
		$captured_level = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level ) use ( &$captured_level ) {
			$captured_level = $level;
		}, 10, 2 );

		Error_Handler::warning( 'Something odd' );

		$this->assertSame( 'warning', $captured_level );
	}

	public function test_info_method_fires_at_info_level(): void {
		Error_Handler::enable_debug();

		$captured_level = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level ) use ( &$captured_level ) {
			$captured_level = $level;
		}, 10, 2 );

		Error_Handler::info( 'General info' );

		$this->assertSame( 'info', $captured_level );
	}

	public function test_debug_method_fires_at_debug_level(): void {
		Error_Handler::enable_debug();

		$captured_level = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level ) use ( &$captured_level ) {
			$captured_level = $level;
		}, 10, 2 );

		Error_Handler::debug( 'Diagnostic data' );

		$this->assertSame( 'debug', $captured_level );
	}

	public function test_error_method_passes_context(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::error( 'Fail', array( 'code' => 500 ) );

		$this->assertSame( array( 'code' => 500 ), $captured_context );
	}

	public function test_warning_method_passes_context(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::warning( 'Warn', array( 'path' => '/missing' ) );

		$this->assertSame( array( 'path' => '/missing' ), $captured_context );
	}

	public function test_debug_method_default_context_is_empty_array(): void {
		Error_Handler::enable_debug();

		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::debug( 'Test' );

		$this->assertSame( array(), $captured_context );
	}

	// handle_exception()

	public function test_handle_exception_fires_logged_action(): void {
		$captured_message = null;
		add_action( 'blockstudio/error/logged', function ( $message ) use ( &$captured_message ) {
			$captured_message = $message;
		} );

		$exception = new \RuntimeException( 'Boom' );
		Error_Handler::handle_exception( $exception );

		$this->assertStringContainsString( 'Boom', $captured_message );
		$this->assertStringContainsString( 'RuntimeException', $captured_message );
	}

	public function test_handle_exception_fires_exception_action(): void {
		$captured_exception = null;
		$captured_context   = null;
		add_action( 'blockstudio/error/exception', function ( $exception, $context ) use ( &$captured_exception, &$captured_context ) {
			$captured_exception = $exception;
			$captured_context   = $context;
		}, 10, 2 );

		$exception = new \RuntimeException( 'Test exception' );
		Error_Handler::handle_exception( $exception, 'Block Render' );

		$this->assertSame( $exception, $captured_exception );
		$this->assertSame( 'Block Render', $captured_context );
	}

	public function test_handle_exception_logs_at_error_level(): void {
		$captured_level = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level ) use ( &$captured_level ) {
			$captured_level = $level;
		}, 10, 2 );

		Error_Handler::handle_exception( new \RuntimeException( 'Test' ) );

		$this->assertSame( 'error', $captured_level );
	}

	public function test_handle_exception_context_includes_exception_class(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::handle_exception( new \InvalidArgumentException( 'Bad arg' ) );

		$this->assertSame( 'InvalidArgumentException', $captured_context['exception_class'] );
	}

	public function test_handle_exception_context_includes_file(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		$exception = new \RuntimeException( 'Test' );
		Error_Handler::handle_exception( $exception );

		$this->assertSame( $exception->getFile(), $captured_context['file'] );
	}

	public function test_handle_exception_context_includes_line(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		$exception = new \RuntimeException( 'Test' );
		Error_Handler::handle_exception( $exception );

		$this->assertSame( $exception->getLine(), $captured_context['line'] );
	}

	public function test_handle_exception_context_includes_trace(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		$exception = new \RuntimeException( 'Test' );
		Error_Handler::handle_exception( $exception );

		$this->assertSame( $exception->getTraceAsString(), $captured_context['trace'] );
	}

	public function test_handle_exception_with_empty_context_string(): void {
		$captured_message = null;
		add_action( 'blockstudio/error/logged', function ( $message ) use ( &$captured_message ) {
			$captured_message = $message;
		} );

		Error_Handler::handle_exception( new \RuntimeException( 'Test' ), '' );

		$this->assertStringStartsWith( 'Exception [RuntimeException]', $captured_message );
	}

	public function test_handle_exception_with_context_string(): void {
		$captured_message = null;
		add_action( 'blockstudio/error/logged', function ( $message ) use ( &$captured_message ) {
			$captured_message = $message;
		} );

		Error_Handler::handle_exception( new \RuntimeException( 'Test' ), 'SCSS Compilation' );

		$this->assertStringStartsWith( '[SCSS Compilation]', $captured_message );
	}

	// try_catch()

	public function test_try_catch_returns_callback_result_on_success(): void {
		$result = Error_Handler::try_catch( function () {
			return 42;
		} );

		$this->assertSame( 42, $result );
	}

	public function test_try_catch_returns_string_from_callback(): void {
		$result = Error_Handler::try_catch( function () {
			return 'hello';
		} );

		$this->assertSame( 'hello', $result );
	}

	public function test_try_catch_returns_array_from_callback(): void {
		$result = Error_Handler::try_catch( function () {
			return array( 'a', 'b' );
		} );

		$this->assertSame( array( 'a', 'b' ), $result );
	}

	public function test_try_catch_returns_null_default_on_exception(): void {
		$result = Error_Handler::try_catch( function () {
			throw new \RuntimeException( 'Fail' );
		} );

		$this->assertNull( $result );
	}

	public function test_try_catch_returns_custom_default_on_exception(): void {
		$result = Error_Handler::try_catch(
			function () {
				throw new \RuntimeException( 'Fail' );
			},
			'Test',
			'fallback'
		);

		$this->assertSame( 'fallback', $result );
	}

	public function test_try_catch_returns_array_default_on_exception(): void {
		$result = Error_Handler::try_catch(
			function () {
				throw new \RuntimeException( 'Fail' );
			},
			'Test',
			array()
		);

		$this->assertSame( array(), $result );
	}

	public function test_try_catch_returns_false_default_on_exception(): void {
		$result = Error_Handler::try_catch(
			function () {
				throw new \RuntimeException( 'Fail' );
			},
			'Test',
			false
		);

		$this->assertFalse( $result );
	}

	public function test_try_catch_handles_exception_on_failure(): void {
		$exception_fired = false;
		add_action( 'blockstudio/error/exception', function () use ( &$exception_fired ) {
			$exception_fired = true;
		} );

		Error_Handler::try_catch( function () {
			throw new \RuntimeException( 'Fail' );
		}, 'Context' );

		$this->assertTrue( $exception_fired );
	}

	public function test_try_catch_passes_context_to_handler(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/exception', function ( $exception, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 2 );

		Error_Handler::try_catch( function () {
			throw new \RuntimeException( 'Fail' );
		}, 'My Operation' );

		$this->assertSame( 'My Operation', $captured_context );
	}

	public function test_try_catch_does_not_fire_exception_action_on_success(): void {
		$exception_fired = false;
		add_action( 'blockstudio/error/exception', function () use ( &$exception_fired ) {
			$exception_fired = true;
		} );

		Error_Handler::try_catch( function () {
			return 'ok';
		} );

		$this->assertFalse( $exception_fired );
	}

	public function test_try_catch_catches_throwable_not_just_exception(): void {
		$result = Error_Handler::try_catch(
			function () {
				throw new \Error( 'Type error' );
			},
			'Test',
			'caught'
		);

		$this->assertSame( 'caught', $result );
	}

	public function test_try_catch_with_empty_context(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/exception', function ( $exception, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 2 );

		Error_Handler::try_catch( function () {
			throw new \RuntimeException( 'Fail' );
		} );

		$this->assertSame( '', $captured_context );
	}

	// Interaction between different exception types

	public function test_handle_exception_with_invalid_argument_exception(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::handle_exception( new \InvalidArgumentException( 'Bad' ) );

		$this->assertSame( 'InvalidArgumentException', $captured_context['exception_class'] );
	}

	public function test_handle_exception_with_error(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::handle_exception( new \Error( 'Fatal' ) );

		$this->assertSame( 'Error', $captured_context['exception_class'] );
	}

	public function test_handle_exception_with_type_error(): void {
		$captured_context = null;
		add_action( 'blockstudio/error/logged', function ( $message, $level, $context ) use ( &$captured_context ) {
			$captured_context = $context;
		}, 10, 3 );

		Error_Handler::handle_exception( new \TypeError( 'Wrong type' ) );

		$this->assertSame( 'TypeError', $captured_context['exception_class'] );
	}
}
