<?php
/**
 * Stub Plugin class for V7.
 *
 * This is a stub version that provides empty data for licensing.
 * The actual licensing/updater functionality is not needed in V7.
 *
 * @package Blockstudio
 */

if ( ! class_exists( 'BlockstudioPlugin' ) ) {
	/**
	 * Stub BlockstudioPlugin class.
	 */
	class BlockstudioPlugin {

		/**
		 * Plugin prefix.
		 *
		 * @var string
		 */
		public static string $prefix = 'blockstudio_';

		/**
		 * Plugin name.
		 *
		 * @var string
		 */
		public static string $name = 'Blockstudio';

		/**
		 * Store URL.
		 *
		 * @var string
		 */
		public static string $store_url = 'https://blockstudio.dev';

		/**
		 * Item ID.
		 *
		 * @var int|null
		 */
		public static ?int $item_id = 31;

		/**
		 * Plugin file path.
		 *
		 * @var string
		 */
		public static string $file = '';

		/**
		 * Initialize the plugin updater.
		 *
		 * @param string $prefix    Plugin prefix.
		 * @param string $name      Plugin name.
		 * @param string $store_url Store URL.
		 * @param int    $item_id   Item ID.
		 * @param string $file      Plugin file path.
		 *
		 * @return void
		 */
		public static function init( $prefix, $name, $store_url, $item_id, $file ): void {
			self::$prefix    = $prefix;
			self::$name      = $name;
			self::$store_url = $store_url;
			self::$item_id   = $item_id;
			self::$file      = $file;
		}

		/**
		 * Get plugin data.
		 *
		 * @return array Plugin data array.
		 */
		public static function getData(): array {
			return array(
				'name'          => str_replace( '_', '', self::$prefix ),
				'pluginUrl'     => defined( 'BLOCKSTUDIO_FILE' ) ? plugins_url( '/', BLOCKSTUDIO_FILE ) : '',
				'shopUrl'       => self::$store_url,
				'productId'     => self::$item_id,
				'currentUrl'    => home_url(),
				'licenseStatus' => self::getStatus(),
				'licenseCode'   => self::getKey(),
			);
		}

		/**
		 * Get license status.
		 *
		 * @return string License status.
		 */
		public static function getStatus(): string {
			return trim( get_option( self::$prefix . 'license_status', '' ) );
		}

		/**
		 * Get license key.
		 *
		 * @return string License key.
		 */
		public static function getKey(): string {
			return trim( get_option( self::$prefix . 'license_key', '' ) );
		}
	}
}
