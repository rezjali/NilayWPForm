<?php
/**
 * NFB_Services Class
 * Handles form submissions, payments, notifications, and other services.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NFB_Services {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_nfb_submit_form', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_nopriv_nfb_submit_form', [ $this, 'handle_form_submission' ] );
        add_action( 'init', [ $this, 'handle_payment_verification' ] );
	}

	public function handle_form_submission() {
        check_ajax_referer( 'nfb_form_nonce', 'nonce' );

		$form_data = wp_unslash($_POST);
		$form_id = isset( $form_data['form_id'] ) ? intval( $form_data['form_id'] ) : 0;
		if ( ! $form_id ) wp_send_json_error( [ 'message' => __( 'فرم نامعتبر است.', 'nilay-form-builder' ) ] );
        
        $limit_message = $this->check_submission_limits($form_id);
        if ($limit_message !== true) wp_send_json_error(['message' => $limit_message]);

		$validation_result = $this->validate_form_submission( $form_id, $form_data );
		if ( ! $validation_result['success'] ) wp_send_json_error( [ 'errors' => $validation_result['errors'] ] );

        $sanitized_data = $validation_result['sanitized_data'];
		$settings = get_post_meta( $form_id, '_nfb_settings', true );
		$payment_enabled = !empty( $settings['payment_enabled'] );
		$amount = !empty( $settings['payment_amount'] ) ? floatval( $settings['payment_amount'] ) : 0;
        
        // Calculate product fields price
        $fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields = json_decode( $fields_json, true );
        foreach($fields as $field) {
            if($field['type'] === 'product' && !empty($sanitized_data[$field['meta_key']])) {
                $amount += floatval($sanitized_data[$field['meta_key']]['price']) * intval($sanitized_data[$field['meta_key']]['quantity']);
            }
        }

		if ( $payment_enabled && $amount > 0 ) {
            $entry_id = $this->create_entry($form_id, $sanitized_data, 'pending');
            if(is_wp_error($entry_id)) wp_send_json_error(['message' => $entry_id->get_error_message()]);

			$gateway_id = $settings['payment_gateway'] ?? 'zarinpal';
			$gateway = NFB_Gateways::get_gateway( $gateway_id );
			if ( $gateway ) {
                update_post_meta($entry_id, '_nfb_payment_amount', $amount);
				$gateway->send_to_gateway( $entry_id, $amount, $form_id ); // This will exit and redirect
			} else {
				wp_send_json_error( [ 'message' => __( 'درگاه پرداخت انتخاب شده معتبر نیست.', 'nilay-form-builder' ) ] );
			}
		} else {
			$entry_id = $this->create_entry( $form_id, $sanitized_data, 'publish' );
			if ( is_wp_error( $entry_id ) ) wp_send_json_error( [ 'message' => $entry_id->get_error_message() ] );
            
            $this->post_submission_actions($form_id, $entry_id, $sanitized_data);
            
            $success_message = !empty($settings['success_message']) ? $settings['success_message'] : __('فرم شما با موفقیت ارسال شد.', 'nilay-form-builder');
            $success_message = $this->replace_meta_keys($success_message, $entry_id);

			wp_send_json_success( [ 'message' => $success_message ] );
		}
	}
    
    public function post_submission_actions($form_id, $entry_id, $form_data) {
        $settings = get_post_meta($form_id, '_nfb_settings', true);
        $this->send_notifications($form_id, $entry_id, $form_data, $settings);

        if ( !empty($settings['webhook_enabled']) && !empty($settings['webhook_url']) ) {
            $this->send_webhook($form_id, $entry_id, $form_data, $settings['webhook_url']);
        }
    }

	public function validate_form_submission( $form_id, $form_data ) {
		$errors = [];
        $sanitized_data = [];
		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields = json_decode( $fields_json, true );

		if ( empty( $fields ) ) return [ 'success' => true, 'sanitized_data' => [] ];

		foreach ( $fields as $field ) {
			$key = $field['meta_key'];
			$value = isset( $form_data[ $key ] ) ? $form_data[ $key ] : '';

			if ( !empty( $field['required'] ) && ( is_string($value) && trim($value) === '' || empty($value) ) ) {
				$errors[ $key ] = sprintf( __( 'فیلد "%s" الزامی است.', 'nilay-form-builder' ), $field['label'] );
				continue;
			}
            
            // Further validation and sanitization
            // ...
            $sanitized_data[$key] = $value; // This is a simplified version
		}

		return [
			'success' => empty( $errors ),
			'errors'  => $errors,
            'sanitized_data' => $sanitized_data,
		];
	}

	public function create_entry( $form_id, $form_data, $status = 'publish' ) {
		$entry_title = sprintf( 'ورودی برای فرم #%d - %s', $form_id, date_i18n( 'Y-m-d H:i:s' ) );
		$entry_id = wp_insert_post( [
			'post_type'   => 'nfb_entry',
			'post_title'  => $entry_title,
			'post_status' => $status,
            'post_parent' => $form_id,
		] );

		if ( is_wp_error( $entry_id ) ) return $entry_id;

		foreach ( $form_data as $key => $value ) {
            if (in_array($key, ['form_id', 'nonce', 'action'])) continue;
            // For complex fields like repeaters, value might be an array
            update_post_meta( $entry_id, '_nfb_field_' . sanitize_key($key), $value );
		}
        
        update_post_meta( $entry_id, '_user_ip', $_SERVER['REMOTE_ADDR'] );

		return $entry_id;
	}
    
    public function handle_payment_verification() {
        if (!isset($_GET['nfb_action']) || $_GET['nfb_action'] !== 'verify_payment') return;
        
        $gateway_id = sanitize_key($_GET['gateway'] ?? '');
        $entry_id = intval($_GET['entry_id'] ?? 0);
        $entry = get_post($entry_id);
        $form_id = $entry ? $entry->post_parent : 0;
        
        if(!$form_id || !$entry_id || !$gateway_id) wp_die('اطلاعات پرداخت ناقص است.');

        $amount = get_post_meta($entry_id, '_nfb_payment_amount', true);
        $gateway = NFB_Gateways::get_gateway($gateway_id);
        
        if ($gateway && $gateway->verify_payment($entry_id, $amount)) {
            wp_update_post(['ID' => $entry_id, 'post_status' => 'publish']);
            update_post_meta($entry_id, '_entry_status', 'completed');
            
            $form_data = get_post_meta($entry_id); // Simplified
            $this->post_submission_actions($form_id, $entry_id, $form_data);
            
            $settings = get_post_meta($form_id, '_nfb_settings', true);
            $redirect_url = !empty($settings['success_redirect']) ? $settings['success_redirect'] : home_url();
            $success_message = !empty($settings['success_message']) ? $settings['success_message'] : __('پرداخت شما با موفقیت انجام شد.', 'nilay-form-builder');
            $success_message = $this->replace_meta_keys($success_message, $entry_id);
            
            // Add a query arg to show the message on redirect page
            wp_redirect(add_query_arg('nfb_success_msg', urlencode($success_message), $redirect_url));
            exit;
        } else {
            update_post_meta($entry_id, '_entry_status', 'failed');
            wp_die('تایید پرداخت ناموفق بود.');
        }
    }
    
    public function send_notifications($form_id, $entry_id, $form_data, $settings) { /* ... */ }
    public function check_submission_limits($form_id) { return true; }
    private function send_webhook($form_id, $entry_id, $form_data, $webhook_url) { /* ... */ }
    private function replace_meta_keys($content, $entry_id) { /* ... */ return $content; }
    private function send_sms($to, $pattern_code, $args) { /* ... */ }
}

