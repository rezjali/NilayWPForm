<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس NFB_Frontend
 * مسئولیت تمام عملیات‌های سمت کاربر، از جمله نمایش فرم و پردازش ارسال آن را بر عهده دارد.
 */
class NFB_Frontend
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('nilay_form', [$this, 'render_form_shortcode']);
        add_action('template_redirect', [$this, 'handle_form_submission']);
        add_action('init', function() {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
        });
    }

    public function enqueue_scripts()
    {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'nilay_form')) {
            wp_enqueue_style('nfb-frontend-style', NFB_PLUGIN_URL . 'assets/css/frontend.css', [], NFB_VERSION);
            wp_enqueue_script('nfb-signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', [], '4.0.0', true);
            wp_enqueue_script('nfb-frontend-script', NFB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'nfb-signature-pad'], NFB_VERSION, true);
        }
    }

    public function render_form_shortcode($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $form_id = intval($atts['id']);

        if (empty($form_id) || get_post_type($form_id) !== 'nilay_form') {
            return '<div class="nfb-alert nfb-alert-error">' . __('فرم مورد نظر یافت نشد.', 'nilay-form-builder') . '</div>';
        }

        ob_start();

        if (isset($_GET['nfb_form_id']) && intval($_GET['nfb_form_id']) === $form_id) {
            if (isset($_GET['nfb_status']) && $_GET['nfb_status'] === 'success') {
                $this->render_success_message($form_id);
            } elseif (isset($_GET['nfb_status']) && $_GET['nfb_status'] === 'error') {
                $error_message = isset($_SESSION['nfb_error_message']) ? $_SESSION['nfb_error_message'] : __('خطایی رخ داده است.', 'nilay-form-builder');
                echo '<div class="nfb-alert nfb-alert-error">' . esc_html($error_message) . '</div>';
                unset($_SESSION['nfb_error_message']);
            }
        }
        
        $this->render_form_html($form_id);

        return ob_get_clean();
    }
    
    private function render_form_html($form_id)
    {
        $fields = get_post_meta($form_id, '_nfb_form_fields', true);
        $settings = get_post_meta($form_id, '_nfb_form_settings', true);

        echo '<div class="nfb-form-container">';
        echo '<form method="post" enctype="multipart/form-data" class="nfb-form">';
        wp_nonce_field('nfb_submit_form_action', 'nfb_form_nonce');
        echo '<input type="hidden" name="nfb_form_id" value="' . esc_attr($form_id) . '">';

        if (!empty($fields) && is_array($fields)) {
            $has_steps = count(array_filter($fields, fn($field) => $field['type'] === 'page_break')) > 0;
            
            if ($has_steps) {
                echo '<div class="nfb-steps-container">';
                echo '<div class="nfb-step active">';
            }

            NFB_Core::render_fields_recursive($fields, 0);

            if ($has_steps) {
                echo '</div>'; // End last step
                echo '</div>';
                echo '<div class="nfb-step-nav">';
                echo '<button type="button" class="nfb-button nfb-prev-step" style="display: none;">' . __('قبلی', 'nilay-form-builder') . '</button>';
                echo '<button type="button" class="nfb-button nfb-next-step">' . __('بعدی', 'nilay-form-builder') . '</button>';
                echo '</div>';
            }
        }

        echo '<div class="nfb-form-group nfb-submit-group">';
        echo '<button type="submit" name="nfb_submit" class="nfb-button nfb-submit-button">' . __('ارسال', 'nilay-form-builder') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    private function render_success_message($form_id)
    {
        $settings = get_post_meta($form_id, '_nfb_form_settings', true);
        $confirmation_settings = $settings['confirmation'] ?? [];
        $message = !empty($confirmation_settings['message']) ? $confirmation_settings['message'] : __('فرم شما با موفقیت ارسال شد.', 'nilay-form-builder');
        $pdf_url = isset($_GET['pdf_url']) ? esc_url_raw(urldecode($_GET['pdf_url'])) : '';

        echo '<div class="nfb-alert nfb-alert-success">';
        echo wpautop(esc_html($message));
        if ($pdf_url) {
            echo '<a href="' . $pdf_url . '" class="nfb-button" target="_blank" download>' . __('دانلود PDF', 'nilay-form-builder') . '</a>';
        }
        echo '</div>';
    }

    public function handle_form_submission()
    {
        if (!isset($_POST['nfb_submit']) || !isset($_POST['nfb_form_nonce']) || !wp_verify_nonce($_POST['nfb_form_nonce'], 'nfb_submit_form_action')) {
            return;
        }

        $form_id = intval($_POST['nfb_form_id']);
        $fields = get_post_meta($form_id, '_nfb_form_fields', true);
        $posted_data = $_POST['nfb_fields'] ?? [];

        // Simple server-side validation for required fields
        foreach ($fields as $field) {
            if (!empty($field['required']) && empty($posted_data[$field['key']])) {
                $_SESSION['nfb_error_message'] = sprintf(__('فیلد "%s" الزامی است.', 'nilay-form-builder'), $field['label']);
                wp_redirect(add_query_arg(['nfb_status' => 'error', 'nfb_form_id' => $form_id], get_permalink()));
                exit;
            }
        }

        $entry_id = wp_insert_post([
            'post_title'  => sprintf(__('ورودی برای فرم "%s" - %s', 'nilay-form-builder'), get_the_title($form_id), date_i18n('Y-m-d H:i:s')),
            'post_type'   => 'nilay_form_entry',
            'post_status' => 'publish',
            'post_parent' => $form_id,
        ]);

        if (is_wp_error($entry_id)) return;

        update_post_meta($entry_id, '_entry_id', $entry_id);
        update_post_meta($entry_id, '_entry_form_id', $form_id);
        update_post_meta($entry_id, '_entry_ip', $_SERVER['REMOTE_ADDR']);
        update_post_meta($entry_id, '_entry_user_agent', $_SERVER['HTTP_USER_AGENT']);
        update_post_meta($entry_id, '_entry_user_id', get_current_user_id());

        NFB_Core::save_entry_fields($entry_id, $fields, $posted_data);
        
        $actions_result = NFB_Services::process_actions($entry_id, $form_id, $posted_data);
        
        $redirect_url = get_permalink();

        if ($actions_result['status'] === 'success') {
            if (!empty($actions_result['redirect_url'])) {
                wp_redirect($actions_result['redirect_url']);
                exit;
            }
            $redirect_url = add_query_arg([
                'nfb_status' => 'success',
                'nfb_form_id' => $form_id,
                'pdf_url' => urlencode($actions_result['pdf_url'] ?? '')
            ], $redirect_url);
        } else {
            wp_delete_post($entry_id, true);
            $_SESSION['nfb_error_message'] = $actions_result['message'] ?? __('خطای ناشناخته', 'nilay-form-builder');
            $redirect_url = add_query_arg(['nfb_status' => 'error', 'nfb_form_id' => $form_id], $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }
}

