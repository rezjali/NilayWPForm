<?php
/**
 * Plugin Name: نیلای - فرم ساز
 * Description: یک افزونه پیشرفته برای ساخت انواع فرم‌های ساده و پیچیده در وردپرس.
 * Version: 2.0.0
 * Author: Reza Jalali
 * Author URI: https://rezajalali.com
 * Text Domain: nilay-form-builder
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if the main class already exists.
if ( ! class_exists( 'Nilay_Form_Builder' ) ) {

	/**
	 * Main plugin class.
	 */
	final class Nilay_Form_Builder {

		/**
		 * The single instance of the class.
		 * @var Nilay_Form_Builder
		 */
		private static $_instance = null;

		/**
		 * Main instance.
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
		private function __construct() {
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Initialize WordPress hooks.
		 */
		private function init_hooks() {
			// Activation and deactivation hooks.
			register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );
			register_deactivation_hook( __FILE__, [ __CLASS__, 'deactivate' ] );
		}

		/**
		 * Include core plugin files.
		 */
		private function includes() {
			require_once dirname( __FILE__ ) . '/includes/class-nfb-core.php';
		}

		/**
		 * Plugin activation hook.
		 * This runs once when the plugin is activated.
		 */
		public static function activate() {
			// Ensure required files are loaded for CPT registration.
			require_once dirname( __FILE__ ) . '/includes/class-nfb-admin.php';

			// Ensure post types are registered before flushing rules.
			if ( class_exists( 'NFB_Admin' ) ) {
				$admin_instance = new NFB_Admin();
				// Directly call the public method to register post types
				$admin_instance->register_post_types();
			}

			// Flush rewrite rules to make CPTs' permalinks available.
			flush_rewrite_rules();
		}

		/**
		 * Plugin deactivation hook.
		 * This runs once when the plugin is deactivated.
		 */
		public static function deactivate() {
			// Flush rewrite rules on deactivation to remove CPTs' permalinks.
			flush_rewrite_rules();
		}
	}

	/**
	 * The main function for returning the Nilay_Form_Builder instance.
	 *
	 * @return Nilay_Form_Builder
	 */
	function nilay_form_builder() {
		return Nilay_Form_Builder::instance();
	}

	// Initialize the main plugin class.
	nilay_form_builder();

	// Initialize the core functionality of the plugin.
	if ( file_exists( dirname( __FILE__ ) . '/includes/class-nfb-core.php' ) ) {
		NFB_Core::instance();
	}
}

