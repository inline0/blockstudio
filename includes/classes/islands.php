<?php
/**
 * Block islands.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Type;
use WP_Error;
use WP_REST_Request;

/**
 * Handles hydrated and dynamic block islands.
 *
 * @since 7.5.0
 */
final class Islands {

	/**
	 * Current island render phase.
	 *
	 * @var string
	 */
	private static string $phase = 'normal';

	/**
	 * Reset the request-local island render phase.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		self::$phase = 'normal';
	}

	/**
	 * Whether hooks are registered.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Maximum islands per request.
	 *
	 * @var int
	 */
	private const MAX_ISLANDS_PER_REQUEST = 50;

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'blockstudio_islands';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		add_filter( 'blockstudio/render', array( __CLASS__, 'inject_runtime' ), 20 );

		self::$initialized = true;
	}

	/**
	 * Normalize island config from block.json.
	 *
	 * @param mixed $config Raw island config.
	 *
	 * @return array|false Normalized config or false.
	 */
	public static function normalize_config( $config ) {
		if ( false === $config || null === $config || '' === $config ) {
			return false;
		}

		if ( true === $config ) {
			$config = array( 'mode' => 'hydrate' );
		} elseif ( is_string( $config ) ) {
			$config = array( 'mode' => $config );
		} elseif ( ! is_array( $config ) ) {
			return false;
		}

		$mode = strtolower( (string) ( $config['mode'] ?? 'hydrate' ) );
		if ( 'hydrated' === $mode ) {
			$mode = 'hydrate';
		}

		if ( ! in_array( $mode, array( 'hydrate', 'dynamic' ), true ) ) {
			return false;
		}

		$tag = strtolower( (string) ( $config['tag'] ?? 'div' ) );
		if ( ! preg_match( '/^[a-z][a-z0-9:-]*$/', $tag ) ) {
			$tag = 'div';
		}

		$attributes = null;
		if ( isset( $config['attributes'] ) && is_array( $config['attributes'] ) ) {
			$attributes = array_values(
				array_filter(
					array_map(
						static fn( $attribute ) => is_string( $attribute ) ? trim( $attribute ) : '',
						$config['attributes']
					),
					static fn( string $attribute ) => '' !== $attribute
				)
			);
		}

		$loading = strtolower( (string) ( $config['loading'] ?? 'eager' ) );
		if ( ! in_array( $loading, array( 'eager', 'visible' ), true ) ) {
			$loading = 'eager';
		}

		$placeholder = null;
		if ( isset( $config['placeholder'] ) && is_string( $config['placeholder'] ) ) {
			$placeholder = trim( $config['placeholder'] );
			if ( '' === $placeholder || str_contains( $placeholder, '..' ) || str_starts_with( $placeholder, '/' ) ) {
				$placeholder = null;
			}
		}

		$event = null;
		if ( isset( $config['event'] ) && is_string( $config['event'] ) ) {
			$event = trim( $config['event'] );
			if ( '' === $event ) {
				$event = null;
			}
		}

		return array(
			'mode'        => $mode,
			'tag'         => $tag,
			'attributes'  => $attributes,
			'placeholder' => $placeholder,
			'cache'       => self::normalize_cache_config( $config['cache'] ?? false ),
			'loading'     => $loading,
			'event'       => $event,
		);
	}

	/**
	 * Normalize fragment cache config.
	 *
	 * @param mixed $config Raw cache config.
	 *
	 * @return array|false Normalized cache config or false.
	 */
	private static function normalize_cache_config( $config ) {
		if ( true === $config ) {
			return array(
				'ttl' => 60,
				'per' => 'global',
			);
		}

		if ( ! is_array( $config ) ) {
			return false;
		}

		$ttl = isset( $config['ttl'] ) ? (int) $config['ttl'] : 60;
		if ( $ttl < 1 ) {
			return false;
		}

		$per = strtolower( (string) ( $config['per'] ?? 'global' ) );
		if ( ! in_array( $per, array( 'global', 'user' ), true ) ) {
			$per = 'global';
		}

		return array(
			'ttl' => $ttl,
			'per' => $per,
		);
	}

	/**
	 * Get current island phase.
	 *
	 * @return string Phase.
	 */
	public static function phase(): string {
		return self::$phase;
	}

	/**
	 * Run a callback inside an island render phase.
	 *
	 * @param string   $phase    Phase name.
	 * @param callable $callback Callback.
	 *
	 * @return mixed Callback result.
	 */
	public static function with_phase( string $phase, callable $callback ) {
		$previous    = self::$phase;
		self::$phase = $phase;

		try {
			return $callback();
		} finally {
			self::$phase = $previous;
		}
	}

	/**
	 * Get island config for a block.
	 *
	 * @param string $name Block name.
	 *
	 * @return array|false Island config.
	 */
	public static function config( string $name ) {
		$block = Build::blocks()[ $name ] ?? null;
		if ( ! $block instanceof WP_Block_Type ) {
			return false;
		}

		$config = $block->blockstudio['island'] ?? false;
		return is_array( $config ) ? $config : false;
	}

	/**
	 * Whether a block is any island.
	 *
	 * @param string $name Block name.
	 *
	 * @return bool Whether the block is an island.
	 */
	public static function is_island( string $name ): bool {
		return (bool) apply_filters( 'blockstudio/islands/is_island', false !== self::config( $name ), $name );
	}

	/**
	 * Get island mode.
	 *
	 * @param string $name Block name.
	 *
	 * @return string|null Mode.
	 */
	public static function mode( string $name ): ?string {
		$config = self::config( $name );
		$mode   = $config['mode'] ?? null;

		return apply_filters( 'blockstudio/islands/mode', $mode, $name, $config );
	}

	/**
	 * Get registered islands.
	 *
	 * @return array<string, array> Registered island configs.
	 */
	public static function registered(): array {
		$islands = array();

		foreach ( Build::blocks() as $name => $block ) {
			if ( ! $block instanceof WP_Block_Type ) {
				continue;
			}

			$config = $block->blockstudio['island'] ?? false;
			if ( is_array( $config ) ) {
				$islands[ $name ] = $config;
			}
		}

		do_action( 'blockstudio/islands/registered', $islands );

		return $islands;
	}

	/**
	 * Determine the render phase for a block.
	 *
	 * @param string $name       Block name.
	 * @param bool   $is_editor  Whether editor render.
	 * @param bool   $is_preview Whether preview render.
	 *
	 * @return string Render phase.
	 */
	public static function render_phase( string $name, bool $is_editor, bool $is_preview ): string {
		if ( $is_editor || $is_preview ) {
			return 'normal';
		}

		if ( 'fragment' === self::$phase ) {
			return 'fragment';
		}

		$config = self::config( $name );
		if ( ! $config ) {
			return 'normal';
		}

		return 'dynamic' === ( $config['mode'] ?? '' ) ? 'placeholder' : 'hydrate';
	}

	/**
	 * Get placeholder path for a block.
	 *
	 * @param string $name       Block name.
	 * @param array  $block_data Block data.
	 *
	 * @return string|null Placeholder path.
	 */
	public static function placeholder_path( string $name, array $block_data ): ?string {
		$config = self::config( $name );
		if ( ! $config || 'dynamic' !== ( $config['mode'] ?? '' ) ) {
			return null;
		}

		$dir = isset( $block_data['path'] ) ? dirname( $block_data['path'] ) : '';
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return null;
		}

		$candidates  = array();
		$placeholder = apply_filters(
			'blockstudio/islands/placeholder',
			$config['placeholder'] ?? null,
			$name,
			$config,
			$block_data
		);
		if ( ! empty( $placeholder ) ) {
			$candidates[] = $placeholder;
		}

		array_push(
			$candidates,
			'placeholder.php',
			'placeholder.blade.php',
			'placeholder.twig',
			'placeholder.html'
		);

		$base = wp_normalize_path( $dir );
		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === $candidate || str_contains( $candidate, '..' ) || str_starts_with( $candidate, '/' ) ) {
				continue;
			}

			$path = wp_normalize_path( $dir . '/' . $candidate );
			if ( ! str_starts_with( $path, $base . '/' ) || ! file_exists( $path ) ) {
				continue;
			}

			return $path;
		}

		return null;
	}

	/**
	 * Filter attributes for island props/request.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Raw attributes.
	 *
	 * @return array Filtered attributes.
	 */
	public static function filter_attributes( string $name, array $attributes ): array {
		$block  = Build::blocks()[ $name ] ?? null;
		$config = self::config( $name );
		if ( ! $block instanceof WP_Block_Type || ! $config ) {
			return array();
		}

		$schema_keys = self::schema_attribute_keys( $block );
		$allowed     = $config['attributes'] ?? null;
		if ( null === $allowed ) {
			$allowed = $schema_keys;
			if ( empty( $allowed ) ) {
				$allowed = array_keys( $attributes );
			}
		} elseif ( ! empty( $schema_keys ) ) {
			$allowed = array_values( array_intersect( $allowed, $schema_keys ) );
		}

		$filtered = array();
		foreach ( $allowed as $key ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $attributes ) ) {
				continue;
			}

			if ( str_starts_with( $key, '_' ) || in_array( $key, array( 'blockstudio', 'className', 'anchor' ), true ) ) {
				continue;
			}

			$filtered[ $key ] = $attributes[ $key ];
		}

		return apply_filters( 'blockstudio/islands/attributes', $filtered, $name, $attributes, $config );
	}

	/**
	 * Get public block attribute schema keys.
	 *
	 * @param WP_Block_Type $block Block type.
	 *
	 * @return array<string> Attribute keys.
	 */
	private static function schema_attribute_keys( WP_Block_Type $block ): array {
		$keys = array();

		foreach ( array_keys( $block->blockstudio['attributes'] ?? array() ) as $key ) {
			if ( is_string( $key ) ) {
				$keys[] = $key;
			}
		}

		foreach ( array_keys( $block->attributes ?? array() ) as $key ) {
			if ( is_string( $key ) ) {
				$keys[] = $key;
			}
		}

		return array_values(
			array_unique(
				array_diff(
					$keys,
					array( 'blockstudio', 'className', 'anchor' )
				)
			)
		);
	}

	/**
	 * Build island marker.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Raw attributes.
	 * @param string $inner_html Inner HTML.
	 * @param string $phase      Render phase.
	 *
	 * @return string Marker HTML.
	 */
	public static function marker( string $name, array $attributes, string $inner_html, string $phase = 'placeholder' ): string {
		$config = self::config( $name );
		if ( ! $config ) {
			return $inner_html;
		}

		$mode = $config['mode'] ?? 'hydrate';
		$tag  = apply_filters( 'blockstudio/islands/marker_tag', $config['tag'] ?? 'div', $name, $config );
		$tag  = is_string( $tag ) && preg_match( '/^[a-z][a-z0-9:-]*$/i', $tag ) ? strtolower( $tag ) : 'div';

		$attrs = array(
			'data-bs-island'       => $name,
			'data-bs-island-mode'  => $mode,
			'data-bs-island-phase' => $phase,
		);

		if ( 'dynamic' === $mode ) {
			if ( ! self::can_render_dynamic( $name ) ) {
				return $inner_html;
			}

			$props     = self::filter_attributes( $name, $attributes );
			$payload   = self::signature_payload( $name, $props );
			$signature = self::signature( $payload );

			$encoded_props                     = wp_json_encode( $props );
			$attrs['data-bs-island-props']     = false !== $encoded_props ? $encoded_props : '{}';
			$attrs['data-bs-island-signature'] = $signature;
			$loading                           = apply_filters( 'blockstudio/islands/loading', $config['loading'] ?? 'eager', $name, $config );
			$attrs['data-bs-island-loading']   = in_array( $loading, array( 'eager', 'visible' ), true ) ? $loading : 'eager';

			if ( ! empty( $config['event'] ) ) {
				$attrs['data-bs-island-event'] = $config['event'];
			}
		}

		$attrs = apply_filters( 'blockstudio/islands/marker_attributes', $attrs, $name, $attributes, $config, $phase );

		return '<' . $tag . ' ' . self::attributes_to_string( $attrs ) . '>' . $inner_html . '</' . $tag . '>';
	}

	/**
	 * Check if dynamic island can be rendered.
	 *
	 * @param string $name Block name.
	 *
	 * @return bool Whether dynamic rendering is allowed.
	 */
	private static function can_render_dynamic( string $name ): bool {
		$block = Build::blocks()[ $name ] ?? null;
		if ( ! $block instanceof WP_Block_Type || ! $block->is_dynamic() ) {
			return false;
		}

		$config  = $block->blockstudio['island'] ?? false;
		$allowed = is_array( $config ) && 'dynamic' === ( $config['mode'] ?? '' );

		return (bool) apply_filters( 'blockstudio/islands/allowed', $allowed, $name, $block, $config );
	}

	/**
	 * Convert attributes array to HTML string.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return string Attribute string.
	 */
	private static function attributes_to_string( array $attributes ): string {
		$output = array();
		foreach ( $attributes as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}

			if ( true === $value ) {
				$output[] = esc_attr( $name );
				continue;
			}

			$output[] = esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return implode( ' ', $output );
	}

	/**
	 * Build signature payload.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Attributes.
	 * @param array  $context    Public context.
	 *
	 * @return array Payload.
	 */
	public static function signature_payload( string $name, array $attributes, array $context = array() ): array {
		$payload = array(
			'name'       => $name,
			'mode'       => 'dynamic',
			'attributes' => self::sort_recursive( $attributes ),
			'context'    => self::sort_recursive( $context ),
			'version'    => 1,
		);

		return apply_filters( 'blockstudio/islands/signature_payload', $payload, $name, $attributes, $context );
	}

	/**
	 * Sign an island payload.
	 *
	 * @param array $payload Payload.
	 *
	 * @return string Signature.
	 */
	public static function signature( array $payload ): string {
		$signature = hash_hmac( 'sha256', wp_json_encode( $payload ), wp_salt( 'auth' ) );

		return apply_filters( 'blockstudio/islands/signature', $signature, $payload );
	}

	/**
	 * Verify an island payload signature.
	 *
	 * @param array  $payload   Payload.
	 * @param string $signature Signature.
	 *
	 * @return bool Whether signature is valid.
	 */
	public static function verify_signature( array $payload, string $signature ): bool {
		$expected = self::signature( $payload );
		$valid    = '' !== $signature && hash_equals( $expected, $signature );

		return (bool) apply_filters( 'blockstudio/islands/verify_signature', $valid, $payload, $signature, $expected );
	}

	/**
	 * Sort an array recursively.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed Sorted value.
	 */
	private static function sort_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sort_recursive( $item );
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return $value;
	}

	/**
	 * Inject client runtime when islands are present.
	 *
	 * @param string $html HTML.
	 *
	 * @return string HTML.
	 */
	public static function inject_runtime( string $html ): string {
		if ( false === strpos( $html, 'data-bs-island' ) || false !== strpos( $html, 'data-bs-islands-runtime' ) ) {
			return $html;
		}

		$runtime = self::runtime_script();
		if ( false !== stripos( $html, '</body>' ) ) {
			return preg_replace( '/<\/body>/i', $runtime . '</body>', $html, 1 ) ?? $html;
		}

		return $html . $runtime;
	}

	/**
	 * Get runtime script.
	 *
	 * @return string Script tag.
	 */
	private static function runtime_script(): string {
		$config = array(
			'endpoint' => apply_filters( 'blockstudio/islands/endpoint_url', rest_url( 'blockstudio/v1/island/render' ) ),
		);

		$js = <<<'JS'
(() => {
  const config = window.__blockstudioIslands || {};
  const endpoint = config.endpoint;
  if (!endpoint) return;

  const hydrated = new WeakSet();
  const queue = new Map();
  let scheduled = false;

  const parseJSON = (value, fallback) => {
    if (!value) return fallback;
    try { return JSON.parse(value); } catch (error) { return fallback; }
  };

  const dispatch = (el, name, detail = {}) => {
    el.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
  };

  const hydrate = (el) => {
    if (hydrated.has(el)) return;
    hydrated.add(el);
    el.dataset.bsIslandHydrated = '1';
    dispatch(el, 'blockstudio:island:hydrate', { name: el.dataset.bsIsland });
  };

  const clientId = (() => {
    let i = 0;
    return () => `i${Date.now().toString(36)}${(i++).toString(36)}`;
  })();

  const enqueue = (el) => {
    if (!el || el.dataset.bsIslandMode !== 'dynamic') return;
    const name = el.dataset.bsIsland || '';
    const attributes = parseJSON(el.dataset.bsIslandProps, {});
    const signature = el.dataset.bsIslandSignature || '';
    if (!name || !signature) return;
    const key = JSON.stringify([name, attributes, signature]);
    if (!queue.has(key)) {
      queue.set(key, { clientId: clientId(), name, attributes, signature, elements: [] });
    }
    queue.get(key).elements.push(el);
    if (!scheduled) {
      scheduled = true;
      Promise.resolve().then(flush);
    }
  };

  const markError = (entry, error) => {
    entry.elements.forEach((el) => {
      el.dataset.bsIslandError = '1';
      dispatch(el, 'blockstudio:island:error', { name: entry.name, error });
    });
  };

  const flush = async () => {
    scheduled = false;
    const entries = Array.from(queue.values());
    queue.clear();
    if (!entries.length) return;

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          islands: entries.map(({ clientId, name, attributes, signature }) => ({ clientId, name, attributes, signature }))
        })
      });
      const data = await response.json();
      entries.forEach((entry) => {
        const rendered = data ? data[entry.clientId] : null;
        if (typeof rendered !== 'string') {
          markError(entry, rendered || 'missing');
          return;
        }
        entry.elements.forEach((el) => {
          const range = document.createRange();
          range.selectNodeContents(el);
          el.replaceChildren(range.createContextualFragment(rendered));
          delete el.dataset.bsIslandError;
          dispatch(el, 'blockstudio:island:rendered', { name: entry.name });
          init(el);
          hydrate(el);
        });
      });
    } catch (error) {
      entries.forEach((entry) => markError(entry, error));
    }
  };

  const init = (root = document) => {
    root.querySelectorAll('[data-bs-island]').forEach((el) => {
      if (el.dataset.bsIslandMode === 'dynamic') {
        if ((el.dataset.bsIslandLoading || 'eager') === 'visible' && 'IntersectionObserver' in window) {
          visibleObserver.observe(el);
        } else {
          enqueue(el);
        }
        if (el.dataset.bsIslandEvent && !el.dataset.bsIslandEventBound) {
          el.dataset.bsIslandEventBound = '1';
          window.addEventListener(el.dataset.bsIslandEvent, () => enqueue(el));
        }
      } else {
        hydrate(el);
      }
    });
  };

  const visibleObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((items) => {
      items.forEach((item) => {
        if (item.isIntersecting) {
          visibleObserver.unobserve(item.target);
          enqueue(item.target);
        }
      });
    })
    : null;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }
})();
JS;

		return '<script data-bs-islands-runtime>window.__blockstudioIslands=' .
			wp_json_encode( $config ) .
			';</script><script type="module" data-bs-islands-runtime>' .
			$js .
			'</script>';
	}

	/**
	 * Render island endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function render_endpoint( WP_REST_Request $request ) {
		self::maybe_restore_cookie_user();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporarily preserving ambient front-end query context before render.
		$had_mode = array_key_exists( 'blockstudioMode', $_GET );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Restored exactly after render, not trusted.
		$mode = $had_mode ? $_GET['blockstudioMode'] : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporarily preserving ambient front-end query context before render.
		$had_post = array_key_exists( 'postId', $_GET );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Restored exactly after render, not trusted.
		$post_id = $had_post ? $_GET['postId'] : null;

		unset( $_GET['blockstudioMode'], $_GET['postId'] );

		try {
			$islands = $request->get_param( 'islands' );
			if ( ! is_array( $islands ) ) {
				return new WP_Error( 'blockstudio_islands_invalid_request', __( 'Invalid islands request.', 'blockstudio' ), array( 'status' => 400 ) );
			}

			$max = (int) apply_filters( 'blockstudio/islands/max_per_request', self::MAX_ISLANDS_PER_REQUEST );
			if ( count( $islands ) > $max ) {
				return new WP_Error( 'blockstudio_islands_too_many', __( 'Too many islands requested.', 'blockstudio' ), array( 'status' => 413 ) );
			}

			$rendered = array();
			foreach ( $islands as $island ) {
				if ( ! is_array( $island ) ) {
					continue;
				}

				$client_id  = isset( $island['clientId'] ) ? sanitize_key( (string) $island['clientId'] ) : '';
				$name       = isset( $island['name'] ) ? sanitize_text_field( (string) $island['name'] ) : '';
				$attributes = isset( $island['attributes'] ) && is_array( $island['attributes'] ) ? $island['attributes'] : array();
				$signature  = isset( $island['signature'] ) ? (string) $island['signature'] : '';

				if ( '' === $client_id ) {
					continue;
				}

				$result = self::render_endpoint_item( $name, $attributes, $signature );
				if ( is_wp_error( $result ) ) {
					$rendered[ $client_id ] = array(
						'error' => $result->get_error_code(),
					);
				} else {
					$rendered[ $client_id ] = $result;
				}
			}

			$response = rest_ensure_response( $rendered );
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'X-Robots-Tag', 'noindex' );

			return $response;
		} finally {
			if ( $had_mode ) {
				$_GET['blockstudioMode'] = $mode;
			}

			if ( $had_post ) {
				$_GET['postId'] = $post_id;
			}
		}
	}

	/**
	 * Render a single endpoint item.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Attributes.
	 * @param string $signature  Signature.
	 *
	 * @return string|WP_Error Rendered HTML or error.
	 */
	private static function render_endpoint_item( string $name, array $attributes, string $signature ) {
		if ( ! self::can_render_dynamic( $name ) ) {
			return new WP_Error( 'blockstudio_island_not_allowed', __( 'Invalid island block.', 'blockstudio' ), array( 'status' => 403 ) );
		}

		$signature_attributes = self::filter_attributes( $name, $attributes );
		$payload              = self::signature_payload( $name, $signature_attributes );
		if ( ! self::verify_signature( $payload, $signature ) ) {
			return new WP_Error( 'blockstudio_island_invalid_signature', __( 'Invalid island signature.', 'blockstudio' ), array( 'status' => 403 ) );
		}

		$attributes = apply_filters(
			'blockstudio/islands/request_attributes',
			$signature_attributes,
			$name,
			$attributes
		);
		$attributes = is_array( $attributes ) ? $attributes : array();

		$cached = self::get_cached_fragment( $name, $attributes );
		if ( null !== $cached ) {
			return $cached;
		}

		$block = array(
			'blockName'    => $name,
			'attrs'        => array(
				'blockstudio' => array(
					'name'       => $name,
					'attributes' => $attributes,
				),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$rendered = self::with_phase(
			'fragment',
			static fn() => render_block( $block )
		);

		$rendered = apply_filters( 'blockstudio/islands/fragment', $rendered, $name, $attributes );
		self::set_cached_fragment( $name, $attributes, $rendered );

		do_action( 'blockstudio/islands/rendered', $name, $attributes, $rendered );

		return $rendered;
	}

	/**
	 * Get cached fragment.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Attributes.
	 *
	 * @return string|null Cached fragment or null.
	 */
	private static function get_cached_fragment( string $name, array $attributes ): ?string {
		$config = self::config( $name );
		$cache  = $config['cache'] ?? false;
		if ( ! is_array( $cache ) ) {
			return null;
		}

		if ( 'user' === ( $cache['per'] ?? 'global' ) && 0 === get_current_user_id() ) {
			return null;
		}

		$key   = self::cache_key( $name, $attributes, $cache );
		$value = get_transient( $key );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Store cached fragment.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Attributes.
	 * @param string $rendered   Rendered fragment.
	 *
	 * @return void
	 */
	private static function set_cached_fragment( string $name, array $attributes, string $rendered ): void {
		$config = self::config( $name );
		$cache  = $config['cache'] ?? false;
		if ( ! is_array( $cache ) ) {
			return;
		}

		if ( 'user' === ( $cache['per'] ?? 'global' ) && 0 === get_current_user_id() ) {
			return;
		}

		set_transient( self::cache_key( $name, $attributes, $cache ), $rendered, (int) $cache['ttl'] );
	}

	/**
	 * Restore cookie-authenticated users for same-origin island renders.
	 *
	 * @return void
	 */
	private static function maybe_restore_cookie_user(): void {
		if ( is_user_logged_in() || ! self::is_same_origin_browser_request() ) {
			return;
		}

		$user_id = wp_validate_auth_cookie( '', 'logged_in' );
		if ( $user_id > 0 ) {
			wp_set_current_user( $user_id );
		}
	}

	/**
	 * Check whether the REST request came from the same site.
	 *
	 * @return bool
	 */
	private static function is_same_origin_browser_request(): bool {
		$fetch_site = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '' ) ) );
		if ( '' !== $fetch_site && ! in_array( $fetch_site, array( 'same-origin', 'same-site', 'none' ), true ) ) {
			return false;
		}

		$origin_header = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? '' ) );
		if ( '' === $fetch_site && '' === $origin_header ) {
			return false;
		}

		if ( '' === $origin_header ) {
			return true;
		}

		$origin = wp_parse_url( $origin_header );
		$home   = wp_parse_url( home_url() );

		return self::origin_parts( $origin ) === self::origin_parts( $home );
	}

	/**
	 * Normalize URL origin parts.
	 *
	 * @param array|false $parts Parsed URL parts.
	 *
	 * @return array{scheme:string,host:string,port:int}
	 */
	private static function origin_parts( $parts ): array {
		if ( ! is_array( $parts ) ) {
			return array(
				'scheme' => '',
				'host'   => '',
				'port'   => 0,
			);
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 0;

		if ( 0 === $port ) {
			$port = 'https' === $scheme ? 443 : ( 'http' === $scheme ? 80 : 0 );
		}

		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $port,
		);
	}

	/**
	 * Build cache key.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Attributes.
	 * @param array  $cache      Cache config.
	 *
	 * @return string Cache key.
	 */
	private static function cache_key( string $name, array $attributes, array $cache ): string {
		$scope = 'user' === ( $cache['per'] ?? 'global' ) ? 'user:' . get_current_user_id() : 'global';
		$hash  = md5(
			wp_json_encode(
				array(
					'name'       => $name,
					'attributes' => self::sort_recursive( $attributes ),
					'scope'      => $scope,
					'runtime'    => Runtime_Context::hash(
						'island-fragment',
						array( 'blocks' ),
						array(
							'block' => $name,
							'cache' => $cache,
						)
					),
				)
			)
		);

		return self::CACHE_GROUP . '_' . $hash;
	}
}
