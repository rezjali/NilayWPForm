<?php
/**
 * NFB_Services Class
 * Handles form submissions, payments, notifications, and other services.
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

class NFB_Services {

	/**
	 * The single instance of the class.
	 *
	 * @var NFB_Services
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * Main NFB_Services Instance.
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
     * Changed to private to prevent direct instantiation.
	 */
	private function __construct() {
		// Ajax handlers for form submission.
		add_action( 'wp_ajax_nfb_submit_form', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_nopriv_nfb_submit_form', [ $this, 'handle_form_submission' ] );
	}

	/**
	 * Handle the AJAX form submission.
	 */
	public function handle_form_submission() {
		// Verify nonce for security.
		check_ajax_referer( 'nfb_form_nonce', 'nonce' );

		// Parse the form data.
		parse_str( $_POST['form_data'], $form_data );

		$form_id = isset( $form_data['form_id'] ) ? intval( $form_data['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_send_json_error( [ 'message' => __( 'فرم نامعتبر است.', 'nilay-form-builder' ) ] );
		}
        
        // Check submission limits before processing
        $limit_message = $this->check_submission_limits($form_id);
        if ($limit_message !== true) {
            wp_send_json_error(['message' => $limit_message]);
        }

		// Validate the form submission.
		$validation_result = $this->validate_form_submission( $form_id, $form_data );
		if ( ! $validation_result['success'] ) {
			wp_send_json_error( [ 'errors' => $validation_result['errors'] ] );
		}

		// Check for payment.
		$settings        = get_post_meta( $form_id, '_nfb_settings', true );
		$payment_enabled = isset( $settings['payment_enabled'] ) && $settings['payment_enabled'] === 'yes';
		$amount          = ! empty( $settings['payment_amount'] ) ? floatval( $settings['payment_amount'] ) : 0;

		if ( $payment_enabled && $amount > 0 ) {
			// Handle payment logic.
			$gateway_id = $settings['payment_gateway'] ?? 'zarinpal';
			$gateway    = NFB_Gateways::get_gateway( $gateway_id );
			if ( $gateway ) {
				$redirect_url = $gateway->process_payment( $form_id, $amount, $form_data );
				if ( is_wp_error( $redirect_url ) ) {
					wp_send_json_error( [ 'message' => $redirect_url->get_error_message() ] );
				} else {
					wp_send_json_success( [ 'redirect' => $redirect_url ] );
				}
			} else {
				wp_send_json_error( [ 'message' => __( 'درگاه پرداخت انتخاب شده معتبر نیست.', 'nilay-form-builder' ) ] );
			}
		} else {
			// Process as a free form.
			$entry_id = $this->create_entry( $form_id, $form_data );
			if ( is_wp_error( $entry_id ) ) {
				wp_send_json_error( [ 'message' => $entry_id->get_error_message() ] );
			}
            
            // Send notifications and webhook
            $this->post_submission_actions($form_id, $entry_id, $form_data);
            
			wp_send_json_success( [ 'message' => __( 'فرم شما با موفقیت ارسال شد.', 'nilay-form-builder' ) ] );
		}
	}
    
    /**
     * Perform actions after a successful submission (notifications, webhooks).
     *
     * @param int   $form_id  The ID of the form.
     * @param int   $entry_id The ID of the created entry.
     * @param array $form_data The submitted form data.
     */
    public function post_submission_actions($form_id, $entry_id, $form_data) {
        $settings = get_post_meta($form_id, '_nfb_settings', true);

        // Send Notifications
        $this->send_notifications($form_id, $entry_id, $form_data, $settings);

        // Send Webhook
        if (isset($settings['webhook_enabled']) && $settings['webhook_enabled'] && !empty($settings['webhook_url'])) {
            $this->send_webhook($form_id, $entry_id, $form_data, $settings['webhook_url']);
        }
    }


	/**
	 * Validate form fields based on their settings.
	 *
	 * @param int   $form_id The ID of the form.
	 * @param array $form_data The submitted form data.
	 *
	 * @return array Validation result.
	 */
	public function validate_form_submission( $form_id, $form_data ) {
		$errors      = [];
		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields      = json_decode( $fields_json, true );

		if ( empty( $fields ) ) {
			return [ 'success' => true ]; // No fields to validate.
		}

		foreach ( $fields as $field ) {
			$meta_key = $field['meta_key'];
			$value    = isset( $form_data[ $meta_key ] ) ? $form_data[ $meta_key ] : '';

			// Check for required fields.
			if ( ! empty( $field['required'] ) && empty( $value ) ) {
				$errors[ $meta_key ] = sprintf( __( 'فیلد "%s" الزامی است.', 'nilay-form-builder' ), $field['label'] );
				continue;
			}

			// Validate specific field types if a value is provided.
			if ( ! empty( $value ) ) {
				switch ( $field['type'] ) {
					case 'email':
						if ( ! is_email( $value ) ) {
							$errors[ $meta_key ] = __( 'لطفا یک آدرس ایمیل معتبر وارد کنید.', 'nilay-form-builder' );
						}
						break;
					case 'number':
						if ( ! is_numeric( $value ) ) {
							$errors[ $meta_key ] = __( 'لطفا فقط عدد وارد کنید.', 'nilay-form-builder' );
						}
						break;
                    case 'signature':
                        if (empty($value) && !empty($field['required'])) {
                            $errors[$meta_key] = sprintf( __( 'فیلد "%s" الزامی است.', 'nilay-form-builder' ), $field['label'] );
                        }
                        break;
				}
			}
		}

		return [
			'success' => empty( $errors ),
			'errors'  => $errors,
		];
	}

	/**
	 * Create a new entry for a form submission.
	 *
	 * @param int   $form_id The ID of the form.
	 * @param array $form_data The sanitized form data.
     * @param array $payment_data Optional payment data.
	 *
	 * @return int|WP_Error The new entry ID or a WP_Error object.
	 */
	public function create_entry( $form_id, $form_data, $payment_data = [] ) {
		$entry_title = sprintf( 'ورودی برای فرم #%d - %s', $form_id, date_i18n( 'Y-m-d H:i:s' ) );
		$entry_id    = wp_insert_post( [
			'post_type'   => 'nfb_entry',
			'post_title'  => $entry_title,
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $entry_id ) ) {
			return $entry_id;
		}

		// Store the form ID this entry belongs to.
		update_post_meta( $entry_id, '_nfb_form_id', $form_id );

		// Store each field's value as post meta.
		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields      = json_decode( $fields_json, true );
		
		foreach ( $fields as $field ) {
			$meta_key = $field['meta_key'];
			if ( isset( $form_data[ $meta_key ] ) ) {
                if ($field['type'] === 'signature') {
                    $image_url = $this->save_signature($form_data[$meta_key], $entry_id, $meta_key);
                    if (!is_wp_error($image_url)) {
                        update_post_meta($entry_id, $meta_key, $image_url);
                    }
                } else {
                    $value = is_array( $form_data[ $meta_key ] ) ?
                        implode( ', ', array_map( 'sanitize_text_field', $form_data[ $meta_key ] ) ) :
                        sanitize_textarea_field( $form_data[ $meta_key ] );
                    update_post_meta( $entry_id, $meta_key, $value );
                }
			}
		}

        // Store payment data if available.
        if (!empty($payment_data)) {
            update_post_meta($entry_id, '_nfb_payment_status', 'completed');
            update_post_meta($entry_id, '_nfb_payment_gateway', $payment_data['gateway']);
            update_post_meta($entry_id, '_nfb_payment_amount', $payment_data['amount']);
            update_post_meta($entry_id, '_nfb_transaction_id', $payment_data['transaction_id']);
        }

		return $entry_id;
	}

	/**
	 * Send email notifications.
	 *
	 * @param int   $form_id The ID of the form.
	 * @param int   $entry_id The ID of the entry.
	 * @param array $form_data The submitted form data.
     * @param array $settings The form settings.
	 */
	public function send_notifications( $form_id, $entry_id, $form_data, $settings ) {
		$admin_email = get_option( 'admin_email' );
        
        // Evaluate conditional logic to potentially override admin email
        if (isset($settings['conditional_logic']) && is_array($settings['conditional_logic'])) {
            foreach ($settings['conditional_logic'] as $rule) {
                if ($rule['action'] === 'change_email' && isset($form_data[$rule['field']])) {
                    $field_value = $form_data[$rule['field']];
                    $rule_value = $rule['value'];
                    $match = ($rule['operator'] === 'is' && $field_value == $rule_value) || ($rule['operator'] === 'is_not' && $field_value != $rule_value);
                    
                    if ($match && is_email($rule['action_value'])) {
                        $admin_email = $rule['action_value'];
                        break; // First matching rule wins
                    }
                }
            }
        }

		$subject     = sprintf( __( 'ورودی جدید برای فرم "%s"', 'nilay-form-builder' ), get_the_title( $form_id ) );
		$message     = "<html><body>";
		$message     .= sprintf( '<h2>%s</h2>', __( 'جزئیات ورودی:', 'nilay-form-builder' ) );
		$message     .= '<table border="1" cellpadding="10" style="width:100%; border-collapse: collapse;">';

		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields      = json_decode( $fields_json, true );
		foreach ( $fields as $field ) {
			$meta_key = $field['meta_key'];
			if ( isset( $form_data[ $meta_key ] ) && ! empty( $form_data[ $meta_key ] ) ) {
				$value = is_array($form_data[$meta_key]) ? implode(', ', $form_data[$meta_key]) : $form_data[$meta_key];
                
                if ($field['type'] === 'signature') {
                    $signature_url = get_post_meta($entry_id, $meta_key, true);
                    $value = '<a href="'.esc_url($signature_url).'"><img src="'.esc_url($signature_url).'" alt="Signature" style="max-width: 200px; height: auto;"></a>';
                } else {
                    $value = nl2br(esc_html($value));
                }

				$message .= sprintf( '<tr><td style="width: 30%%;"><strong>%s</strong></td><td>%s</td></tr>', esc_html( $field['label'] ), $value );
			}
		}

		$message .= '</table>';
        $message .= "</body></html>";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $admin_email, $subject, $message, $headers );
	}

    /**
     * Saves the signature from a base64 string to a PNG file.
     *
     * @param string $base64_string The base64 encoded image data.
     * @param int $entry_id The entry ID.
     * @param string $meta_key The field meta key.
     * @return string|WP_Error The URL of the saved image or an error.
     */
    private function save_signature($base64_string, $entry_id, $meta_key) {
        if (strpos($base64_string, 'data:image/png;base64,') === 0) {
            $base64_string = str_replace('data:image/png;base64,', '', $base64_string);
            $base64_string = str_replace(' ', '+', $base64_string);
            $image_data = base64_decode($base64_string);

            $upload_dir = wp_upload_dir();
            $signatures_dir = $upload_dir['basedir'] . '/nfb-signatures';
            if (!file_exists($signatures_dir)) {
                wp_mkdir_p($signatures_dir);
            }
            
            $filename = "signature-{$entry_id}-{$meta_key}.png";
            $filepath = $signatures_dir . '/' . $filename;
            $fileurl = $upload_dir['baseurl'] . '/nfb-signatures/' . $filename;

            if (file_put_contents($filepath, $image_data)) {
                return $fileurl;
            }
        }
        return new WP_Error('signature_save_failed', __('خطا در ذخیره فایل امضا.', 'nilay-form-builder'));
    }

    /**
     * Check submission limits for a form.
     *
     * @param int $form_id The ID of the form.
     * @return bool|string True if submission is allowed, otherwise a string with the error message.
     */
    public function check_submission_limits($form_id) {
        $settings = get_post_meta($form_id, '_nfb_settings', true);

        // Check total entry limit
        if (!empty($settings['limit_total_entries'])) {
            $entry_count = count(get_posts(['post_type' => 'nfb_entry', 'meta_key' => '_nfb_form_id', 'meta_value' => $form_id, 'posts_per_page' => -1]));
            if ($entry_count >= intval($settings['limit_total_entries'])) {
                return __('ظرفیت ثبت‌نام برای این فرم تکمیل شده است.', 'nilay-form-builder');
            }
        }

        // Check limit per user
        if (!empty($settings['limit_per_user'])) {
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $existing_entries = get_posts([
                'post_type' => 'nfb_entry',
                'meta_query' => [
                    'relation' => 'AND',
                    ['_nfb_form_id' => ['key' => '_nfb_form_id', 'value' => $form_id]],
                    ['user_ip' => ['key' => '_user_ip', 'value' => $user_ip]],
                ]
            ]);
            if (!empty($existing_entries)) {
                return __('شما قبلاً این فرم را ارسال کرده‌اید.', 'nilay-form-builder');
            }
        }

        // Check schedule
        if (!empty($settings['schedule_enabled'])) {
            $now = current_time('timestamp');
            $start = !empty($settings['schedule_start']) ? strtotime($settings['schedule_start']) : 0;
            $end = !empty($settings['schedule_end']) ? strtotime($settings['schedule_end']) : $now + 1; // Make sure it's valid if only start is set
            if ($now < $start) {
                return __('زمان ثبت‌نام برای این فرم هنوز شروع نشده است.', 'nilay-form-builder');
            }
            if ($now > $end) {
                return __('زمان ثبت‌نام برای این فرم به پایان رسیده است.', 'nilay-form-builder');
            }
        }

        return true;
    }

    /**
     * Send form data to a specified webhook URL.
     *
     * @param int $form_id The ID of the form.
     * @param int $entry_id The ID of the entry.
     * @param array $form_data The submitted form data.
     * @param string $webhook_url The URL to send the data to.
     */
    private function send_webhook($form_id, $entry_id, $form_data, $webhook_url) {
        $payload = [
            'form_id' => $form_id,
            'form_title' => get_the_title($form_id),
            'entry_id' => $entry_id,
            'submitted_at' => current_time('mysql'),
            'data' => $form_data,
        ];

        wp_remote_post($webhook_url, [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload),
            'data_format' => 'body',
        ]);
    }
}

