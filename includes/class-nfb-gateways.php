<?php
/**
 * NFB_Gateways Class
 * Manages payment gateways.
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

    /**
	 * The single instance of the class.
	 * @var NFB_Gateways
	 */
	private static $_instance = null;

	/**
	 * Main NFB_Gateways Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

    /**
     * @var array
     */
    private static $gateways = [];

    /**
	 * Constructor.
	 */
    private function __construct() {
        $this->include_gateways();
        $this->init_gateways();
    }

    /**
     * Include gateway files.
     */
    private function include_gateways() {
        require_once NFB_PLUGIN_DIR . 'includes/gateways/class-gateway-zarinpal.php';
    }

    /**
     * Initialize available gateways.
     */
    private function init_gateways() {
        $available_gateways = [
            'NFB_Gateway_Zarinpal'
        ];

        foreach ($available_gateways as $gateway_class) {
            if (class_exists($gateway_class)) {
                $gateway = new $gateway_class();
                self::$gateways[$gateway->id] = [
                    'title' => $gateway->title,
                    'class' => $gateway
                ];
            }
        }
    }

    /**
     * Get all registered gateways.
     * @return array
     */
    public static function get_active_gateways() {
        $active_gateways = [];
        foreach (self::$gateways as $id => $gateway_data) {
             $active_gateways[$id] = [
                'title' => $gateway_data['title'],
            ];
        }
        return $active_gateways;
    }

    /**
     * Get a specific gateway instance.
     * @param string $id
     * @return object|null
     */
    public static function get_gateway($id) {
        return self::$gateways[$id]['class'] ?? null;
    }
}

