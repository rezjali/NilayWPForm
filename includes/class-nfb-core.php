<?php
/**
 * NFB_Core Class
 * The core plugin class.
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
	 */
	private static $_instance = null;

	/**
	 * Main NFB_Core Instance.
	 * Ensures only one instance is loaded or can be loaded.
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
	private function __construct() {
		$this->includes();
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 * Load text domain and instantiate classes.
	 */
	public function init() {
		// Load plugin textdomain
		add_action( 'init', function() {
			load_plugin_textdomain(
				'nilay-form-builder',
				false,
				dirname( plugin_basename( NFB_PLUGIN_FILE ) ) . '/languages'
			);
		});

		// Instantiate classes
		if ( is_admin() ) {
			NFB_Admin::instance();
		}
		NFB_Frontend::instance();
		NFB_Services::instance();
		NFB_Gateways::instance();
	}

	/**
	 * Include necessary files.
	 */
	private function includes() {
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-fields.php';
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-admin.php';
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-frontend.php';
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-services.php';
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-gateways.php';
		require_once NFB_PLUGIN_DIR . 'includes/class-nfb-entries-list-table.php';
	}
}

