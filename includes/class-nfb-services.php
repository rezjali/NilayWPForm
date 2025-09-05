<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس NFB_Services
 * مسئولیت مدیریت تمام اقدامات پس از ارسال فرم مانند پرداخت، اعلان‌ها، PDF و وب‌هوک‌ها را بر عهده دارد.
 */
class NFB_Services
{
    /**
     * نقطه ورود اصلی برای پردازش تمام اقدامات پس از ثبت موفق ورودی.
     *
     * @param int $entry_id شناسه ورودی ثبت شده.
     * @param int $form_id شناسه فرم.
     * @param array $posted_data داده‌های ارسال شده از فرم.
     * @return array نتیجه اقدامات انجام شده.
     */
    public static function process_actions($entry_id, $form_id, $posted_data)
    {
        $actions_result = [
            'status' => 'success',
            'redirect_url' => '',
            'pdf_url' => '',
        ];

        $form_settings = get_post_meta($form_id, '_nfb_form_settings', true);

        // 1. پردازش پرداخت
        $payment_settings = $form_settings['payment'] ?? [];
        if (!empty($payment_settings['enable']) && floatval($payment_settings['amount']) > 0) {
            $redirect_url = self::process_payment($entry_id, $form_id, $payment_settings['amount']);
            if ($redirect_url) {
                $actions_result['redirect_url'] = $redirect_url;
                // در صورت وجود پرداخت، اقدامات دیگر پس از تایید پرداخت انجام می‌شوند.
                return $actions_result;
            } else {
                // اگر درگاه پرداخت پیکربندی نشده باشد
                update_post_meta($entry_id, '_entry_status', 'failed');
                $actions_result['status'] = 'error';
                $actions_result['message'] = __('درگاه پرداخت پیکربندی نشده است.', 'nilay-form-builder');
                return $actions_result;
            }
        } else {
            // اگر فرم رایگان است، وضعیت ورودی را به "تکمیل شده" تغییر می‌دهیم.
            update_post_meta($entry_id, '_entry_status', 'completed');
        }
        
        // 2. تولید PDF
        $confirmation_settings = $form_settings['confirmation'] ?? [];
        if (!empty($confirmation_settings['pdf_enable'])) {
            $pdf_url = self::generate_pdf_from_entry($entry_id, $form_id);
            if ($pdf_url) {
                $actions_result['pdf_url'] = $pdf_url;
                update_post_meta($entry_id, '_entry_pdf_url', $pdf_url);
            }
        }

        // 3. ارسال اعلان‌ها
        self::send_notifications($entry_id, $form_id);

        // 4. ارسال وب‌هوک
        $actions_settings = $form_settings['actions'] ?? [];
        if (!empty($actions_settings['webhook']['enable']) && !empty($actions_settings['webhook']['url'])) {
            self::send_webhook_notification($entry_id, $actions_settings['webhook']['url']);
        }
        
        // 5. اجرای اقدامات شرطی
        self::execute_conditional_actions($entry_id, $form_id, $actions_settings['conditional_actions'] ?? []);

        return $actions_result;
    }

    /**
     * ================================================================
     * بخش ۱: سیستم پرداخت
     * ================================================================
     */

    /**
     * پردازش پرداخت برای یک ورودی.
     *
     * @param int $entry_id شناسه ورودی.
     * @param int $form_id شناسه فرم.
     * @param float $amount مبلغ.
     * @return string|false URL هدایت به درگاه یا false در صورت خطا.
     */
    public static function process_payment($entry_id, $form_id, $amount)
    {
        // در اینجا از همان منطق پرداخت افزونه دایرکتوری استفاده می‌کنیم
        // فرض می‌کنیم تنظیمات درگاه در دیتابیس ذخیره شده‌اند.
        $payment_settings = get_option('wpd_settings')['payments'] ?? [];
        $form_title = get_the_title($form_id);
        $description = sprintf(__('پرداخت برای فرم: %s - ورودی #%d', 'nilay-form-builder'), $form_title, $entry_id);

        // ساخت URL بازگشت برای تایید پرداخت
        $callback_url = add_query_arg([
            'nfb_action' => 'verify_payment',
            'entry_id' => $entry_id,
        ], site_url('/'));

        if (!empty($payment_settings['zarinpal_enable']) && !empty($payment_settings['zarinpal_apikey'])) {
            return self::zarinpal_request($payment_settings['zarinpal_apikey'], $amount, $callback_url, $description);
        } elseif (!empty($payment_settings['zibal_enable']) && !empty($payment_settings['zibal_apikey'])) {
            return self::zibal_request($payment_settings['zibal_apikey'], $amount, $callback_url, $description, $entry_id);
        }

        return false;
    }

    /**
     * ایجاد درخواست پرداخت برای زرین پال.
     */
    private static function zarinpal_request($merchant_id, $amount, $callback_url, $description)
    {
        $data = [
            'merchant_id'  => $merchant_id,
            'amount'       => intval($amount) * 10, // تبدیل به ریال
            'callback_url' => add_query_arg('gateway', 'zarinpal', $callback_url),
            'description'  => $description,
        ];

        $response = wp_remote_post('https://api.zarinpal.com/pg/v4/payment/request.json', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);
        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['data']['code']) && $body['data']['code'] == 100) {
            return 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'];
        }
        return false;
    }

    /**
     * ایجاد درخواست پرداخت برای زیبال.
     */
    private static function zibal_request($merchant_id, $amount, $callback_url, $description, $orderId)
    {
        $data = [
            'merchant'     => $merchant_id,
            'amount'       => intval($amount) * 10, // تبدیل به ریال
            'callbackUrl'  => add_query_arg('gateway', 'zibal', $callback_url),
            'description'  => $description,
            'orderId'      => $orderId,
        ];

        $response = wp_remote_post('https://gateway.zibal.ir/v1/request', ['body' => json_encode($data), 'headers' => ['Content-Type' => 'application/json']]);
        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['result']) && $body['result'] == 100) {
            return 'https://gateway.zibal.ir/start/' . $body['trackId'];
        }
        return false;
    }

    /**
     * تایید پرداخت پس از بازگشت از درگاه.
     */
    public static function verify_payment_handler()
    {
        if (!isset($_GET['nfb_action']) || $_GET['nfb_action'] !== 'verify_payment' || empty($_GET['entry_id'])) {
            return;
        }

        $entry_id = intval($_GET['entry_id']);
        $entry = get_post($entry_id);
        if (!$entry || $entry->post_type !== 'nilay_form_entry') {
            return;
        }

        // در اینجا منطق تایید پرداخت برای هر درگاه پیاده‌سازی می‌شود
        // ... (کد مشابه افزونه دایرکتوری)

        // پس از تایید موفق:
        update_post_meta($entry_id, '_entry_status', 'completed');
        // و اجرای اقدامات دیگر مانند ارسال اعلان
        self::send_notifications($entry_id, $entry->post_parent);
    }


    /**
     * ================================================================
     * بخش ۲: سیستم اعلان‌ها
     * ================================================================
     */

    /**
     * ارسال اعلان‌های ایمیلی.
     */
    public static function send_notifications($entry_id, $form_id)
    {
        $form_settings = get_post_meta($form_id, '_nfb_form_settings', true);
        $notifications = $form_settings['notifications'] ?? [];
        $entry_meta = get_post_meta($entry_id);

        // ارسال اعلان به مدیر
        if (!empty($notifications['admin']['enable'])) {
            $to = !empty($notifications['admin']['send_to']) ? $notifications['admin']['send_to'] : get_option('admin_email');
            $subject = self::replace_placeholders($notifications['admin']['subject'], $entry_meta, $form_id);
            $message = self::replace_placeholders($notifications['admin']['message'], $entry_meta, $form_id);
            wp_mail($to, $subject, wpautop($message), ['Content-Type: text/html; charset=UTF-8']);
        }

        // ارسال اعلان به کاربر (Autoresponder)
        if (!empty($notifications['user']['enable'])) {
            $to_field_key = $notifications['user']['send_to_field'] ?? '';
            $to = $entry_meta["_nfb_field_{$to_field_key}"][0] ?? '';
            if (is_email($to)) {
                $subject = self::replace_placeholders($notifications['user']['subject'], $entry_meta, $form_id);
                $message = self::replace_placeholders($notifications['user']['message'], $entry_meta, $form_id);
                wp_mail($to, $subject, wpautop($message), ['Content-Type: text/html; charset=UTF-8']);
            }
        }
    }

    /**
     * جایگزینی Placeholder ها در متن.
     */
    public static function replace_placeholders($content, $entry_meta, $form_id)
    {
        // جایگزینی فیلدهای فرم
        preg_match_all('/\{([a-zA-Z0-9_\-]+)\}/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $key) {
                $meta_key = '_nfb_field_' . sanitize_key($key);
                if (isset($entry_meta[$meta_key][0])) {
                    $value = maybe_unserialize($entry_meta[$meta_key][0]);
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $content = str_replace('{' . $key . '}', esc_html($value), $content);
                }
            }
        }

        // جایگزینی تگ‌های عمومی
        $content = str_replace('{form_title}', get_the_title($form_id), $content);
        $content = str_replace('{entry_id}', $entry_meta['_entry_id'][0] ?? '', $content);
        $content = str_replace('{user_ip}', $entry_meta['_entry_ip'][0] ?? '', $content);
        $content = str_replace('{date_submitted}', get_the_date('Y-m-d', $entry_meta['_entry_id'][0]), $content);

        return $content;
    }

    /**
     * ================================================================
     * بخش ۳: تولید PDF (تکمیل شده)
     * ================================================================
     */

    /**
     * تولید فایل PDF از یک ورودی.
     *
     * @param int $entry_id شناسه ورودی.
     * @param int $form_id شناسه فرم.
     * @return string|false URL فایل PDF یا false در صورت خطا.
     */
    public static function generate_pdf_from_entry($entry_id, $form_id)
    {
        // بررسی وجود کتابخانه mPDF
        $mpdf_autoloader = NFB_PLUGIN_PATH . 'vendor/autoload.php';
        if (!file_exists($mpdf_autoloader)) {
            // می‌توانید در اینجا یک لاگ خطا ثبت کنید
            return false;
        }
        require_once $mpdf_autoloader;

        $form_settings = get_post_meta($form_id, '_nfb_form_settings', true);
        $pdf_template = $form_settings['confirmation']['pdf_template'] ?? '';

        if (empty($pdf_template)) {
            return false;
        }

        $entry_meta = get_post_meta($entry_id);
        $html_content = self::replace_placeholders($pdf_template, $entry_meta, $form_id);

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            
            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html_content);

            $upload_dir = wp_upload_dir();
            $nfb_dir = $upload_dir['basedir'] . '/nfb-entries';
            if (!file_exists($nfb_dir)) {
                wp_mkdir_p($nfb_dir);
            }

            $filename = "entry-{$entry_id}-form-{$form_id}.pdf";
            $filepath = $nfb_dir . '/' . $filename;
            $fileurl = $upload_dir['baseurl'] . '/nfb-entries/' . $filename;

            $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

            return $fileurl;
        } catch (\Mpdf\MpdfException $e) {
            // ثبت خطا
            error_log('mPDF Error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * ================================================================
     * بخش ۴: وب‌هوک و اقدامات شرطی
     * ================================================================
     */

    /**
     * ارسال داده‌های ورودی به یک URL وب‌هوک.
     */
    public static function send_webhook_notification($entry_id, $webhook_url)
    {
        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return;
        }

        $entry_meta = get_post_meta($entry_id);
        $data_to_send = [];
        foreach ($entry_meta as $key => $value) {
            if (strpos($key, '_nfb_field_') === 0) {
                $field_key = str_replace('_nfb_field_', '', $key);
                $data_to_send[$field_key] = maybe_unserialize($value[0]);
            }
        }
        
        $response = wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'      => json_encode($data_to_send),
            'data_format' => 'body',
        ]);
    }

    /**
     * اجرای اقدامات شرطی تعریف شده برای فرم.
     */
    public static function execute_conditional_actions($entry_id, $form_id, $conditional_actions)
    {
        if (empty($conditional_actions) || !is_array($conditional_actions)) {
            return;
        }
        // این تابع در نسخه‌های بعدی برای اجرای منطق شرطی کامل می‌شود
    }
}

