<?php
/**
 * NFB_Gateways Class
 * Manages payment gateways.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NFB_Gateways {

    private static $_instance = null;
    private static $gateways = [];

	public static function instance() {
		if ( is_null( self::$_instance ) ) self::$_instance = new self();
		return self::$_instance;
	}

    private function __construct() {
        $this->include_gateways();
        $this->init_gateways();
    }

    private function include_gateways() {
        require_once NFB_PLUGIN_DIR . 'includes/gateways/class-gateway-zarinpal.php';
        require_once NFB_PLUGIN_DIR . 'includes/gateways/class-gateway-zibal.php';
    }

    private function init_gateways() {
        $available_gateways = ['NFB_Gateway_Zarinpal', 'NFB_Gateway_Zibal'];

        foreach ($available_gateways as $gateway_class) {
            if (class_exists($gateway_class)) {
                $gateway = new $gateway_class();
                self::$gateways[$gateway->id] = ['title' => $gateway->title, 'class' => $gateway];
            }
        }
    }

    public static function get_active_gateways() {
        $active_gateways = [];
        foreach (self::$gateways as $id => $gateway_data) {
             $active_gateways[$id] = ['title' => $gateway_data['title']];
        }
        return $active_gateways;
    }

    public static function get_gateway($id) {
        return self::$gateways[$id]['class'] ?? null;
    }
}
