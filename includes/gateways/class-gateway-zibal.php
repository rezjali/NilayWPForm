<?php
/**
 * Zibal Gateway Class
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes/Gateways
 * @author     Reza Jalali
 * @since      2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NFB_Gateway_Zibal {

    public $id = 'zibal';
    public $title = 'زیبال';

    public function send_to_gateway($entry_id, $amount, $form_id) {
        $settings = get_option('nfb_settings')['gateways'] ?? [];
        $merchant_id = $settings['zibal_merchant'] ?? '';

        if (empty($merchant_id)) {
            wp_die('پیکربندی درگاه زیبال ناقص است.');
        }

        $callback_url = add_query_arg(['nfb_action' => 'verify_payment', 'gateway' => $this->id, 'entry_id' => $entry_id], home_url('/'));
        $description = sprintf(__('پرداخت برای فرم %s', 'nilay-form-builder'), get_the_title($form_id));

        $data = ['merchant' => $merchant_id, 'amount' => $amount * 10, 'callbackUrl' => $callback_url, 'description' => $description, 'orderId' => $entry_id];
        $response = wp_remote_post('https://gateway.zibal.ir/v1/request', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);

        if (is_wp_error($response)) {
             wp_die('خطا در اتصال به درگاه پرداخت.');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['result']) && $body['result'] == 100) {
            update_post_meta($entry_id, '_nfb_payment_track_id', $body['trackId']);
            wp_redirect('https://gateway.zibal.ir/start/' . $body['trackId']);
            exit;
        } else {
            wp_die('خطا از درگاه زیبال: ' . ($body['message'] ?? 'خطای نامشخص'));
        }
    }

    public function verify_payment($entry_id, $amount) {
        if (empty($_GET['success']) || $_GET['success'] != 1) return false;

        $trackId = get_post_meta($entry_id, '_nfb_payment_track_id', true);
        if (empty($trackId) || $trackId != $_GET['trackId']) return false;

        $settings = get_option('nfb_settings')['gateways'] ?? [];
        $merchant_id = $settings['zibal_merchant'] ?? '';

        $data = ['merchant' => $merchant_id, 'trackId' => $trackId];
        $response = wp_remote_post('https://gateway.zibal.ir/v1/verify', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['result']) && $body['result'] == 100) {
            update_post_meta($entry_id, '_nfb_payment_ref_id', $body['refNumber']);
            return true;
        }

        return false;
    }
}
