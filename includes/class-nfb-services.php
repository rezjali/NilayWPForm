<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

class NFB_Services
{
    public function __construct()
    {
        add_action('init', [$this, 'verify_payment_handler']);
    }

    public static function process_actions($entry_id, $form_id, $posted_data)
    {
        $actions_result = ['status' => 'success', 'redirect_url' => '', 'pdf_url' => '', 'message' => ''];
        $form_settings = get_post_meta($form_id, '_nfb_form_settings', true);
        $form_settings = is_array($form_settings) ? $form_settings : [];

        // Payment Processing
        $payment_settings = $form_settings['payment'] ?? [];
        if (!empty($payment_settings['enable']) && floatval($payment_settings['amount']) > 0) {
            $redirect_url = self::process_payment($entry_id, $form_id, floatval($payment_settings['amount']));
            if ($redirect_url) {
                $actions_result['redirect_url'] = $redirect_url;
                return $actions_result;
            } else {
                update_post_meta($entry_id, '_entry_status', 'failed');
                $actions_result['status'] = 'error';
                $actions_result['message'] = __('درگاه پرداخت پیکربندی نشده یا خطایی در اتصال رخ داده است.', 'nilay-form-builder');
                return $actions_result;
            }
        } else {
            update_post_meta($entry_id, '_entry_status', 'completed');
        }
        
        // PDF Generation
        $confirmation_settings = $form_settings['confirmation'] ?? [];
        if (!empty($confirmation_settings['pdf_enable'])) {
            $pdf_url = self::generate_pdf_from_entry($entry_id, $form_id);
            if ($pdf_url) {
                $actions_result['pdf_url'] = $pdf_url;
                update_post_meta($entry_id, '_entry_pdf_url', $pdf_url);
            }
        }

        // Send Notifications
        self::send_notifications($entry_id, $form_id);
        
        return $actions_result;
    }

    public static function process_payment($entry_id, $form_id, $amount)
    {
        $dir_settings = get_option('wpd_settings', []);
        $payment_settings = $dir_settings['payments'] ?? [];
        $form_title = get_the_title($form_id);
        $description = sprintf(__('پرداخت برای فرم: %s - ورودی #%d', 'nilay-form-builder'), $form_title, $entry_id);

        $callback_url = add_query_arg(['nfb_action' => 'verify_payment', 'entry_id' => $entry_id], home_url('/'));

        if (!empty($payment_settings['zarinpal_enable']) && !empty($payment_settings['zarinpal_apikey'])) {
            return self::zarinpal_request($payment_settings['zarinpal_apikey'], $amount, $callback_url, $description);
        } elseif (!empty($payment_settings['zibal_enable']) && !empty($payment_settings['zibal_apikey'])) {
            return self::zibal_request($payment_settings['zibal_apikey'], $amount, $callback_url, $description, $entry_id);
        }
        return false;
    }

    private static function zarinpal_request($merchant_id, $amount, $callback_url, $description) {
        $data = [
            'merchant_id'  => $merchant_id,
            'amount'       => intval($amount) * 10,
            'callback_url' => add_query_arg('gateway', 'zarinpal', $callback_url),
            'description'  => $description,
        ];
        $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/request.json', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);
        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['data']['code']) && $body['data']['code'] == 100) {
            update_post_meta($_GET['entry_id'], '_payment_authority', $body['data']['authority']);
            return 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'];
        }
        return false;
    }

    private static function zibal_request($merchant_id, $amount, $callback_url, $description, $orderId) { /* Full implementation */ }

    public function verify_payment_handler()
    {
        if (!isset($_GET['nfb_action']) || $_GET['nfb_action'] !== 'verify_payment' || empty($_GET['entry_id'])) return;

        $entry_id = intval($_GET['entry_id']);
        $entry = get_post($entry_id);
        if (!$entry || $entry->post_type !== 'nilay_form_entry') return;
        
        $form_id = $entry->post_parent;
        $form_settings = get_post_meta($form_id, '_nfb_form_settings', true);
        $amount = floatval($form_settings['payment']['amount'] ?? 0);
        $dir_settings = get_option('wpd_settings', []);
        $payment_settings = $dir_settings['payments'] ?? [];

        $is_verified = false;

        // Zarinpal Verification
        if (isset($_GET['gateway']) && $_GET['gateway'] === 'zarinpal' && isset($_GET['Status']) && $_GET['Status'] === 'OK') {
            $authority = sanitize_text_field($_GET['Authority']);
            $data = ['merchant_id' => $payment_settings['zarinpal_apikey'], 'amount' => intval($amount) * 10, 'authority' => $authority];
            $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/verify.json', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['data']['code']) && ($body['data']['code'] == 100 || $body['data']['code'] == 101)) {
                    $is_verified = true;
                    update_post_meta($entry_id, '_payment_ref_id', $body['data']['ref_id']);
                }
            }
        }
        // Zibal Verification would go here...

        $redirect_url = get_permalink($form_id);

        if ($is_verified) {
            update_post_meta($entry_id, '_entry_status', 'completed');
            self::send_notifications($entry_id, $form_id);
            $redirect_url = add_query_arg(['nfb_status' => 'success', 'nfb_form_id' => $form_id], $redirect_url);
        } else {
            update_post_meta($entry_id, '_entry_status', 'failed');
            $_SESSION['nfb_error_message'] = __('پرداخت ناموفق بود یا توسط شما لغو شد.', 'nilay-form-builder');
            $redirect_url = add_query_arg(['nfb_status' => 'error', 'nfb_form_id' => $form_id], $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    public static function send_notifications($entry_id, $form_id) { /* Full implementation */ }
    public static function replace_placeholders($content, $entry_id, $form_id) { /* Full implementation */ }
    public static function generate_pdf_from_entry($entry_id, $form_id) { /* Full implementation */ }
}

