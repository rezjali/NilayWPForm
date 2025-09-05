<?php
/**
 * NFB_Services Class
 * Handles form submissions, validation, notifications and payments.
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

    // ... instance and __construct methods are unchanged ...

	private function process_submission() {
		// ... existing nonce, limit checks, validation ...
		if ( ! empty($settings['payment_enabled']) && ! empty($settings['payment_amount']) ) {
			// ... payment logic remains same ...
		} else {
			$entry_id = $this->save_entry( $form_id, $sanitized_data );
			if ( ! $entry_id ) return;
            
            $this->process_special_fields($entry_id, $fields, $raw_data);
			$this->send_notifications( $form_id, $entry_id, $sanitized_data, $actions );
            $this->send_webhook_notification( $form_id, $entry_id, $sanitized_data, $settings);

            // Use conditional redirect if available
			$redirect_url = $actions['redirect_url'] ?? add_query_arg( 'nfb_success', $form_id, wp_get_referer() );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	private function process_payment_verification() {
		// ... existing logic ...

		if ( $is_successful ) {
			// ... existing logic ...
            
			// Send Notifications and Webhook
			$this->send_notifications( $form_id, $entry_id, $data, $actions );
            $this->send_webhook_notification( $form_id, $entry_id, $data, $settings );
		} 
		// ... existing logic ...
	}

    // ... other methods until the end ...
    
    /**
     * Sends submitted data to a specified Webhook URL.
     *
     * @param int   $form_id
     * @param int   $entry_id
     * @param array $data
     * @param array $settings
     */
    private function send_webhook_notification( $form_id, $entry_id, $data, $settings ) {
        if ( empty( $settings['webhook_enabled'] ) || empty( $settings['webhook_url'] ) ) {
            return;
        }

        $webhook_url = $settings['webhook_url'];
        if ( ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
            return;
        }

        $payload = [
            'form_id'    => $form_id,
            'form_title' => get_the_title( $form_id ),
            'entry_id'   => $entry_id,
            'entry_date' => current_time( 'mysql' ),
            'user_ip'    => $this->get_user_ip(),
            'form_data'  => $data,
        ];

        $args = [
            'body'        => json_encode( $payload ),
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'timeout'     => 15,
            'redirection' => 5,
            'blocking'    => true, // Send now, don't wait for response
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
        ];

        wp_remote_post( $webhook_url, $args );
    }

    // ... rest of the file is unchanged ...
}

