<?php
/**
 * NFB_Admin Class
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

class NFB_Admin {

	// ... __construct, register_post_types, register_admin_menus, add_meta_boxes, render_form_builder_metabox, render_shortcode_metabox are unchanged ...

    public function render_settings_metabox( $post ) {
        $settings = get_post_meta($post->ID, '_nfb_form_settings', true);
        $defaults = [
            'payment_enabled'          => 0,
            'payment_amount'           => '',
            'payment_gateway'          => '',
            'success_message'          => __('فرم شما با موفقیت ارسال شد. متشکریم!', 'nilay-form-builder'),
            'limit_total_enabled'      => 0,
            'limit_total_count'        => 100,
            'limit_per_user_enabled'   => 0,
            'schedule_enabled'         => 0,
            'schedule_start'           => '',
            'schedule_end'             => '',
            'limit_reached_message'    => __('متاسفانه ظرفیت ثبت‌نام برای این فرم تکمیل شده است.', 'nilay-form-builder'),
            'schedule_pending_message' => __('ثبت‌نام برای این فرم هنوز شروع نشده است.', 'nilay-form-builder'),
            'schedule_expired_message' => __('متاسفانه مهلت ثبت‌نام در این فرم به پایان رسیده است.', 'nilay-form-builder'),
            'conditional_logic'        => [],
            'webhook_enabled'          => 0,
            'webhook_url'              => '',
        ];
        $settings = wp_parse_args($settings, $defaults);
        $conditional_rules = $settings['conditional_logic'];
        ?>
        <div class="nfb-settings-wrapper">
            <!-- Payment, Notification, Limit, Conditional Logic sections are unchanged -->
            <!-- ... -->
            <hr>
            
            <!-- Integrations Settings -->
            <h3><?php _e('یکپارچه‌سازی‌ها', 'nilay-form-builder'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('فعال‌سازی وب‌هوک', 'nilay-form-builder'); ?></th>
                        <td>
                            <input type="checkbox" name="nfb_settings[webhook_enabled]" value="1" <?php checked(1, $settings['webhook_enabled']); ?>>
                            <p class="description"><?php _e('در صورت فعال بودن، داده‌های فرم پس از ارسال موفق به آدرس زیر فرستاده می‌شود.', 'nilay-form-builder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nfb_webhook_url"><?php _e('آدرس وب‌هوک (Webhook URL)', 'nilay-form-builder'); ?></label></th>
                        <td>
                            <input type="url" name="nfb_settings[webhook_url]" id="nfb_webhook_url" value="<?php echo esc_attr($settings['webhook_url']); ?>" class="widefat" placeholder="https://... ">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- JS Template and script for conditional logic and datepicker remain the same -->
        <!-- ... -->
        <?php
    }

	public function save_form_data( $post_id ) {
		if ( ! isset( $_POST['nfb_form_nonce'] ) || ! wp_verify_nonce( $_POST['nfb_form_nonce'], 'nfb_save_form_data' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Save Form Builder Fields JSON
		if ( isset( $_POST['nfb_form_fields'] ) ) {
			update_post_meta( $post_id, '_nfb_form_fields_json', wp_unslash( $_POST['nfb_form_fields'] ) );
		}
        
        // Save Form Settings
        if ( isset( $_POST['nfb_settings'] ) ) {
            $settings = (array) $_POST['nfb_settings'];
            $sanitized_settings = [
                'payment_enabled' => isset($settings['payment_enabled']) ? 1 : 0,
                'payment_amount' => sanitize_text_field($settings['payment_amount']),
                'payment_gateway' => sanitize_text_field($settings['payment_gateway']),
                'success_message' => sanitize_textarea_field($settings['success_message']),
                'limit_total_enabled' => isset($settings['limit_total_enabled']) ? 1 : 0,
                'limit_total_count' => intval($settings['limit_total_count']),
                'limit_per_user_enabled' => isset($settings['limit_per_user_enabled']) ? 1 : 0,
                'schedule_enabled' => isset($settings['schedule_enabled']) ? 1 : 0,
                'schedule_start' => sanitize_text_field($settings['schedule_start']),
                'schedule_end' => sanitize_text_field($settings['schedule_end']),
                'limit_reached_message' => sanitize_textarea_field($settings['limit_reached_message']),
                'schedule_pending_message' => sanitize_textarea_field($settings['schedule_pending_message']),
                'schedule_expired_message' => sanitize_textarea_field($settings['schedule_expired_message']),
                'webhook_enabled' => isset($settings['webhook_enabled']) ? 1 : 0,
                'webhook_url' => isset($settings['webhook_url']) ? esc_url_raw($settings['webhook_url']) : '',
            ];
            
            // Sanitize conditional logic rules
            if (isset($settings['conditional_logic']) && is_array($settings['conditional_logic'])) {
                // ... same sanitization as before ...
                 $sanitized_settings['conditional_logic'] = $sanitized_rules;
            } else {
                 $sanitized_settings['conditional_logic'] = [];
            }
            
            update_post_meta($post_id, '_nfb_form_settings', $sanitized_settings);
        }
	}
    
    // ... enqueue_scripts method is unchanged ...
}

new NFB_Admin();

