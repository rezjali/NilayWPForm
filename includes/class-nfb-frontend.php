<?php
/**
 * NFB_Frontend Class
 * Handles the rendering of forms on the frontend.
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

class NFB_Frontend {

	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function register_shortcode() {
		add_shortcode( 'nilay-form', [ $this, 'render_form' ] );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'nfb-frontend-style', NFB_PLUGIN_URL . 'assets/css/frontend.css', [], NFB_VERSION );
        wp_register_script( 'signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', [], '4.0.0', true );
        wp_register_script( 'nfb-frontend-script', NFB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'signature-pad'], NFB_VERSION, true );
	}

	public function render_form( $atts ) {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'nilay-form' );
		$form_id = intval( $atts['id'] );

		if ( ! $form_id || get_post_type( $form_id ) !== 'nfb_form' ) return '';

        $settings = get_post_meta($form_id, '_nfb_form_settings', true);

        // --- Check Submission Limits Before Rendering ---
        $limit_message = NFB_Services::get_instance()->check_submission_limits($form_id, $settings);
        if ($limit_message !== true) {
            return '<div class="nfb-limit-message">' . wp_kses_post($limit_message) . '</div>';
        }
        
        // Handle success message
        if ( isset( $_GET['nfb_success'] ) && intval( $_GET['nfb_success'] ) === $form_id ) {
            $success_message = !empty($settings['success_message']) ? $settings['success_message'] : __('فرم شما با موفقیت ارسال شد.', 'nilay-form-builder');
            return '<div class="nfb-success-message">' . wp_kses_post($success_message) . '</div>';
        }

		$fields = json_decode( get_post_meta( $form_id, '_nfb_form_fields_json', true ), true );
		if ( ! is_array( $fields ) || empty( $fields ) ) return '';

        // ... rest of the render_form method is unchanged from the previous step ...
	}

	private function render_field( $field ) {
		// ... this method is unchanged from the previous step ...
	}
}

new NFB_Frontend();

