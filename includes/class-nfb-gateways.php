<?php
/**
 * NFB_Gateways Class
 * Manages loading and interacting with payment gateways.
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

class NFB_Gateways {

	private static $instance = null;
	public $gateways = [];

	public function __construct() {
		$this->load_gateways();
	}

	/**
	 * Singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load all available gateway files from the gateways directory.
	 */
	public function load_gateways() {
		$gateway_path = NFB_PLUGIN_DIR . 'includes/gateways/';
		
		// در حال حاضر فقط زرین پال را به صورت دستی اضافه می کنیم
		// در آینده می توان این بخش را برای اسکن خودکار فایل ها توسعه داد
		require_once $gateway_path . 'class-gateway-zarinpal.php';
		
		$zarinpal = new NFB_Gateway_Zarinpal();
		$this->gateways[ $zarinpal->id ] = $zarinpal;
	}

	/**
	 * Get an array of active gateways.
	 * @return array
	 */
	public function get_active_gateways() {
		$active_gateways = [];
		foreach ($this->gateways as $gateway) {
			$active_gateways[$gateway->id] = $gateway->name;
		}
		return $active_gateways;
	}

	/**
	 * Get a specific gateway object.
	 * @param string $gateway_id
	 * @return object|null
	 */
	public function get_gateway( $gateway_id ) {
		return isset( $this->gateways[ $gateway_id ] ) ? $this->gateways[ $gateway_id ] : null;
	}
}

// Initialize the gateway manager.
NFB_Gateways::get_instance();
