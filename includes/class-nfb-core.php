<?php
/**
 * NFB_Core Class
 * The main class of the plugin.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NFB_Core {
	/**
	 * The single instance of the class.
	 *
	 * @var NFB_Core
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * Main NFB_Core Instance.
	 * Ensures only one instance of the class is loaded.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Initialize the plugin.
	 * Load textdomain and include necessary files.
	 */
	public function init() {
		// Load Plugin Text Domain for translation.
		load_plugin_textdomain( 'nilay-form-builder', false, dirname( plugin_basename( NFB_PLUGIN_FILE ) ) . '/languages/' );

		// Include necessary files.
		$this->includes();
	}

	/**
	 * Define constants.
	 */
	private function define_constants() {
		define( 'NFB_PLUGIN_VERSION', '1.0.0' );
		define( 'NFB_PLUGIN_FILE', trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'nilay-form-builder.php' );
		define( 'NFB_PLUGIN_DIR', plugin_dir_path( NFB_PLUGIN_FILE ) );
		define( 'NFB_PLUGIN_URL', plugin_dir_url( NFB_PLUGIN_FILE ) );
	}

	/**
	 * Include required files and instantiate classes.
	 */
	private function includes() {
		// Base classes that should always exist
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-entries-list-table.php';

		// Core components
		$core_files = [
			'class-nfb-fields.php',
			'class-nfb-admin.php',
			'class-nfb-frontend.php',
			'class-nfb-gateways.php',
			'class-nfb-services.php',
		];

		foreach ($core_files as $file) {
			$path = NFB_PLUGIN_DIR . 'includes/' . $file;
			if (file_exists($path)) {
				require_once $path;
			}
		}
        
        // Instantiate classes after all files are loaded
        if (class_exists('NFB_Fields'))   { new NFB_Fields(); }
        if (class_exists('NFB_Admin'))    { new NFB_Admin(); }
        if (class_exists('NFB_Frontend')) { new NFB_Frontend(); }
        if (class_exists('NFB_Gateways')) { new NFB_Gateways(); }
        if (class_exists('NFB_Services')) { NFB_Services::instance(); }
	}
}
