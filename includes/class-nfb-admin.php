<?php
/**
 * NFB_Admin Class
 * Handles all admin-side functionality.
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

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_nfb_form', [ $this, 'save_form_meta_data' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register plugin's custom post types.
	 */
	public function register_post_types() {
		$form_labels = [
			'name'               => _x( 'فرم‌ها', 'post type general name', 'nilay-form-builder' ),
			'singular_name'      => _x( 'فرم', 'post type singular name', 'nilay-form-builder' ),
			'menu_name'          => _x( 'فرم ساز', 'admin menu', 'nilay-form-builder' ),
			'name_admin_bar'     => _x( 'فرم', 'add new on admin bar', 'nilay-form-builder' ),
			'add_new'            => _x( 'افزودن فرم', 'form', 'nilay-form-builder' ),
			'add_new_item'       => __( 'افزودن فرم جدید', 'nilay-form-builder' ),
			'new_item'           => __( 'فرم جدید', 'nilay-form-builder' ),
			'edit_item'          => __( 'ویرایش فرم', 'nilay-form-builder' ),
			'view_item'          => __( 'نمایش فرم', 'nilay-form-builder' ),
			'all_items'          => __( 'همه فرم‌ها', 'nilay-form-builder' ),
			'search_items'       => __( 'جستجوی فرم‌ها', 'nilay-form-builder' ),
			'parent_item_colon'  => __( 'فرم والد:', 'nilay-form-builder' ),
			'not_found'          => __( 'هیچ فرمی یافت نشد.', 'nilay-form-builder' ),
			'not_found_in_trash' => __( 'هیچ فرمی در زباله‌دان یافت نشد.', 'nilay-form-builder' )
		];

		$form_args = [
			'labels'             => $form_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'nfb-main-menu',
			'query_var'          => false,
			'rewrite'            => [ 'slug' => 'nfb_form' ],
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title' ],
			'menu_icon'          => 'dashicons-list-view',
		];

		register_post_type( 'nfb_form', $form_args );

		$entry_labels = [
			'name'               => _x( 'ورودی‌ها', 'post type general name', 'nilay-form-builder' ),
			'singular_name'      => _x( 'ورودی', 'post type singular name', 'nilay-form-builder' ),
			'menu_name'          => _x( 'ورودی‌ها', 'admin menu', 'nilay-form-builder' ),
			'all_items'          => __( 'همه ورودی‌ها', 'nilay-form-builder' ),
			'edit_item'          => __( 'ویرایش ورودی', 'nilay-form-builder' ),
			'view_item'          => __( 'نمایش ورودی', 'nilay-form-builder' ),
			'search_items'       => __( 'جستجوی ورودی‌ها', 'nilay-form-builder' ),
			'not_found'          => __( 'هیچ ورودی یافت نشد.', 'nilay-form-builder' ),
			'not_found_in_trash' => __( 'هیچ ورودی در زباله‌دان یافت نشد.', 'nilay-form-builder' )
		];

		$entry_args = [
			'labels'             => $entry_labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'nfb-main-menu',
			'capability_type'    => 'post',
			'capabilities'       => [
				'create_posts' => 'do_not_allow', // prevent backend creation
			],
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'query_var'          => false,
			'supports'           => [ 'title', 'custom-fields' ],
			'show_in_admin_bar'  => false,
		];

		register_post_type( 'nfb_entry', $entry_args );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'فرم ساز نیلی', 'nilay-form-builder' ),
			__( 'فرم ساز', 'nilay-form-builder' ),
			'manage_options',
			'nfb-main-menu',
			null,
			'dashicons-list-view',
			26
		);
	}

	/**
	 * Add meta boxes for the form editor.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'nfb-form-builder',
			__( 'فرم ساز', 'nilay-form-builder' ),
			[ $this, 'render_form_builder_meta_box' ],
			'nfb_form',
			'normal',
			'high'
		);
        add_meta_box(
			'nfb-form-shortcode',
			__( 'کد کوتاه', 'nilay-form-builder' ),
			[ $this, 'render_shortcode_meta_box' ],
			'nfb_form',
			'side',
			'default'
		);
        add_meta_box(
			'nfb-form-settings',
			__( 'تنظیمات فرم', 'nilay-form-builder' ),
			[ $this, 'render_settings_meta_box' ],
			'nfb_form',
			'side',
			'default'
		);
	}

	/**
	 * Render the form builder meta box.
	 */
	public function render_form_builder_meta_box( $post ) {
		// Add a nonce field for security.
		wp_nonce_field( 'nfb_save_form_data', 'nfb_form_nonce' );
		require_once NFB_PLUGIN_DIR . 'includes/views/admin-form-builder.php';
	}
    
    /**
	 * Render the shortcode meta box.
	 */
	public function render_shortcode_meta_box( $post ) {
		?>
        <p><?php _e( 'برای نمایش این فرم در هر برگه، نوشته یا ابزارک، از کد کوتاه زیر استفاده کنید:', 'nilay-form-builder' ); ?></p>
        <input type="text" value="[nilay-form id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]" readonly="readonly" class="widefat">
		<?php
	}
    
    /**
	 * Render the form settings meta box.
	 */
	public function render_settings_meta_box($post) {
        $settings = get_post_meta($post->ID, '_nfb_settings', true);
        if (!is_array($settings)) {
            $settings = [];
        }

        // Default settings
        $settings = wp_parse_args($settings, [
            'payment_enabled'      => 'no',
            'payment_amount'       => '',
            'payment_gateway'      => 'zarinpal',
            'limit_total_entries'  => '',
            'limit_per_user'       => 0,
            'schedule_enabled'     => 0,
            'schedule_start'       => '',
            'schedule_end'         => '',
            'webhook_enabled'      => 0,
            'webhook_url'          => '',
        ]);

        $conditional_logic = isset($settings['conditional_logic']) && is_array($settings['conditional_logic']) ? $settings['conditional_logic'] : [];
        $gateways = NFB_Gateways::get_active_gateways();
        $form_fields = get_post_meta($post->ID, '_nfb_fields', true);
        $fields_array = json_decode($form_fields, true);
        ?>
        <div id="nfb-form-settings-wrapper">
            
            <h4><?php _e('تنظیمات پرداخت', 'nilay-form-builder'); ?></h4>
            <p>
                <label>
                    <input type="checkbox" name="_nfb_settings[payment_enabled]" value="yes" <?php checked($settings['payment_enabled'], 'yes'); ?>>
                    <?php _e('فعال‌سازی پرداخت برای این فرم', 'nilay-form-builder'); ?>
                </label>
            </p>
            <p class="nfb-payment-setting">
                <label for="nfb_payment_amount"><?php _e('مبلغ (تومان):', 'nilay-form-builder'); ?></label>
                <input type="number" id="nfb_payment_amount" name="_nfb_settings[payment_amount]" value="<?php echo esc_attr($settings['payment_amount']); ?>" class="widefat">
            </p>
            <p class="nfb-payment-setting">
                <label for="nfb_payment_gateway"><?php _e('درگاه پرداخت:', 'nilay-form-builder'); ?></label>
                <select id="nfb_payment_gateway" name="_nfb_settings[payment_gateway]" class="widefat">
                    <?php foreach ($gateways as $id => $gateway): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['payment_gateway'], $id); ?>><?php echo esc_html($gateway['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <hr>

            <h4><?php _e('محدودیت‌های ارسال', 'nilay-form-builder'); ?></h4>
            <p>
                <label for="nfb_limit_total_entries"><?php _e('محدودیت تعداد کل ورودی‌ها:', 'nilay-form-builder'); ?></label>
                <input type="number" id="nfb_limit_total_entries" name="_nfb_settings[limit_total_entries]" value="<?php echo esc_attr($settings['limit_total_entries']); ?>" class="widefat">
                <small><?php _e('برای نامحدود بودن، خالی بگذارید.', 'nilay-form-builder'); ?></small>
            </p>
             <p>
                <label>
                    <input type="checkbox" name="_nfb_settings[limit_per_user]" value="1" <?php checked($settings['limit_per_user'], 1); ?>>
                    <?php _e('هر کاربر فقط یک بار ارسال کند', 'nilay-form-builder'); ?>
                </label>
            </p>
            <p>
                 <label>
                    <input type="checkbox" name="_nfb_settings[schedule_enabled]" value="1" <?php checked($settings['schedule_enabled'], 1); ?>>
                    <?php _e('زمان‌بندی فعال بودن فرم', 'nilay-form-builder'); ?>
                </label>
            </p>
            <p class="nfb-schedule-setting">
                <label for="nfb_schedule_start"><?php _e('تاریخ شروع:', 'nilay-form-builder'); ?></label>
                <input type="text" id="nfb_schedule_start" name="_nfb_settings[schedule_start]" value="<?php echo esc_attr($settings['schedule_start']); ?>" class="nfb-datepicker widefat">
            </p>
            <p class="nfb-schedule-setting">
                <label for="nfb_schedule_end"><?php _e('تاریخ پایان:', 'nilay-form-builder'); ?></label>
                <input type="text" id="nfb_schedule_end" name="_nfb_settings[schedule_end]" value="<?php echo esc_attr($settings['schedule_end']); ?>" class="nfb-datepicker widefat">
            </p>

            <hr>
            
            <h4><?php _e('منطق شرطی اقدامات', 'nilay-form-builder'); ?></h4>
            <div id="nfb-conditional-logic-rows">
                <?php if (!empty($conditional_logic)): ?>
                    <?php foreach ($conditional_logic as $index => $rule): ?>
                         <div class="nfb-conditional-row">
                             <p><strong><?php _e('قانون', 'nilay-form-builder'); ?> #<?php echo $index + 1; ?></strong></p>
                             <select name="_nfb_settings[conditional_logic][<?php echo $index; ?>][field]">
                                 <?php if (!empty($fields_array)): foreach ($fields_array as $field): if (in_array($field['type'], ['select', 'radio', 'checkbox'])): ?>
                                     <option value="<?php echo esc_attr($field['meta_key']); ?>" <?php selected($rule['field'], $field['meta_key']); ?>><?php echo esc_html($field['label']); ?></option>
                                 <?php endif; endforeach; endif; ?>
                             </select>
                             <select name="_nfb_settings[conditional_logic][<?php echo $index; ?>][operator]">
                                 <option value="is" <?php selected($rule['operator'], 'is'); ?>><?php _e('برابر است با', 'nilay-form-builder'); ?></option>
                                 <option value="is_not" <?php selected($rule['operator'], 'is_not'); ?>><?php _e('برابر نیست با', 'nilay-form-builder'); ?></option>
                             </select>
                             <input type="text" name="_nfb_settings[conditional_logic][<?php echo $index; ?>][value]" value="<?php echo esc_attr($rule['value']); ?>" placeholder="<?php _e('مقدار', 'nilay-form-builder'); ?>">
                             <select name="_nfb_settings[conditional_logic][<?php echo $index; ?>][action]">
                                <option value="change_email" <?php selected($rule['action'], 'change_email'); ?>><?php _e('ارسال ایمیل به', 'nilay-form-builder'); ?></option>
                                <option value="redirect" <?php selected($rule['action'], 'redirect'); ?>><?php _e('هدایت به آدرس', 'nilay-form-builder'); ?></option>
                             </select>
                            <input type="text" name="_nfb_settings[conditional_logic][<?php echo $index; ?>][action_value]" value="<?php echo esc_attr($rule['action_value']); ?>" placeholder="<?php _e('مقدار اقدام', 'nilay-form-builder'); ?>">
                            <button type="button" class="button nfb-remove-conditional-row">&times;</button>
                         </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="nfb-add-conditional-row"><?php _e('افزودن قانون جدید', 'nilay-form-builder'); ?></button>
            
            <hr>

            <h4><?php _e('یکپارچه‌سازی‌ها', 'nilay-form-builder'); ?></h4>
             <p>
                <label>
                    <input type="checkbox" name="_nfb_settings[webhook_enabled]" value="1" <?php checked($settings['webhook_enabled'], 1); ?>>
                    <?php _e('فعال‌سازی ارسال وب‌هوک', 'nilay-form-builder'); ?>
                </label>
            </p>
             <p class="nfb-webhook-setting">
                <label for="nfb_webhook_url"><?php _e('آدرس Webhook URL:', 'nilay-form-builder'); ?></label>
                <input type="url" id="nfb_webhook_url" name="_nfb_settings[webhook_url]" value="<?php echo esc_attr($settings['webhook_url']); ?>" class="widefat">
            </p>

        </div>

        <script type="text/template" id="nfb-conditional-row-template">
             <div class="nfb-conditional-row">
                 <p><strong><?php _e('قانون جدید', 'nilay-form-builder'); ?></strong></p>
                 <select name="_nfb_settings[conditional_logic][__INDEX__][field]">
                    <?php if (!empty($fields_array)): foreach ($fields_array as $field): if (in_array($field['type'], ['select', 'radio', 'checkbox'])): ?>
                         <option value="<?php echo esc_attr($field['meta_key']); ?>"><?php echo esc_html($field['label']); ?></option>
                     <?php endif; endforeach; endif; ?>
                 </select>
                 <select name="_nfb_settings[conditional_logic][__INDEX__][operator]">
                     <option value="is"><?php _e('برابر است با', 'nilay-form-builder'); ?></option>
                     <option value="is_not"><?php _e('برابر نیست با', 'nilay-form-builder'); ?></option>
                 </select>
                 <input type="text" name="_nfb_settings[conditional_logic][__INDEX__][value]" placeholder="<?php _e('مقدار', 'nilay-form-builder'); ?>">
                 <select name="_nfb_settings[conditional_logic][__INDEX__][action]">
                    <option value="change_email"><?php _e('ارسال ایمیل به', 'nilay-form-builder'); ?></option>
                    <option value="redirect"><?php _e('هدایت به آدرس', 'nilay-form-builder'); ?></option>
                 </select>
                <input type="text" name="_nfb_settings[conditional_logic][__INDEX__][action_value]" placeholder="<?php _e('مقدار اقدام', 'nilay-form-builder'); ?>">
                <button type="button" class="button nfb-remove-conditional-row">&times;</button>
             </div>
        </script>
        <?php
    }

	/**
	 * Save form meta data.
	 */
	public function save_form_meta_data( $post_id ) {
		// Check nonce for security.
		if ( ! isset( $_POST['nfb_form_nonce'] ) || ! wp_verify_nonce( $_POST['nfb_form_nonce'], 'nfb_save_form_data' ) ) {
			return;
		}

		// Check if the current user has permission to save the data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save form builder fields data.
		if ( isset( $_POST['_nfb_fields'] ) ) {
			$fields_json = wp_unslash( $_POST['_nfb_fields'] );
			// Basic validation to ensure it's a valid JSON.
			if ( json_decode( $fields_json ) !== null ) {
				update_post_meta( $post_id, '_nfb_fields', $fields_json );
			}
		}

        // Save form settings data.
        if (isset($_POST['_nfb_settings'])) {
            $settings = (array) $_POST['_nfb_settings'];
            $sanitized_settings = [];

            // Sanitize each setting
            $sanitized_settings['payment_enabled'] = isset($settings['payment_enabled']) ? 'yes' : 'no';
            $sanitized_settings['payment_amount'] = isset($settings['payment_amount']) ? sanitize_text_field($settings['payment_amount']) : '';
            $sanitized_settings['payment_gateway'] = isset($settings['payment_gateway']) ? sanitize_key($settings['payment_gateway']) : 'zarinpal';
            $sanitized_settings['limit_total_entries'] = isset($settings['limit_total_entries']) ? absint($settings['limit_total_entries']) : '';
            $sanitized_settings['limit_per_user'] = isset($settings['limit_per_user']) ? 1 : 0;
            $sanitized_settings['schedule_enabled'] = isset($settings['schedule_enabled']) ? 1 : 0;
            $sanitized_settings['schedule_start'] = isset($settings['schedule_start']) ? sanitize_text_field($settings['schedule_start']) : '';
            $sanitized_settings['schedule_end'] = isset($settings['schedule_end']) ? sanitize_text_field($settings['schedule_end']) : '';
            $sanitized_settings['webhook_enabled'] = isset($settings['webhook_enabled']) ? 1 : 0;
            $sanitized_settings['webhook_url'] = isset($settings['webhook_url']) ? esc_url_raw($settings['webhook_url']) : '';

            // Sanitize conditional logic rules
            $sanitized_settings['conditional_logic'] = [];
            if (isset($settings['conditional_logic']) && is_array($settings['conditional_logic'])) {
                foreach ($settings['conditional_logic'] as $rule) {
                    if (!empty($rule['field']) && !empty($rule['value'])) {
                        $sanitized_settings['conditional_logic'][] = [
                            'field' => sanitize_text_field($rule['field']),
                            'operator' => sanitize_key($rule['operator']),
                            'value' => sanitize_text_field($rule['value']),
                            'action' => sanitize_key($rule['action']),
                            'action_value' => sanitize_text_field($rule['action_value']),
                        ];
                    }
                }
            }
            
            update_post_meta($post_id, '_nfb_settings', $sanitized_settings);
        }
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		global $post_type;

		if ( 'nfb_form' === $post_type && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
			// Styles
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
            wp_enqueue_style( 'jquery-ui-datepicker', NFB_PLUGIN_URL . 'assets/css/jquery-ui.css' );
			wp_enqueue_style( 'nfb-admin-style', NFB_PLUGIN_URL . 'assets/css/admin.css', [], NFB_PLUGIN_VERSION );

			// Scripts
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'nfb-admin-script', NFB_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-dialog', 'jquery-ui-sortable', 'jquery-ui-datepicker' ], NFB_PLUGIN_VERSION, true );
		
            wp_localize_script('nfb-admin-script', 'nfb_admin_vars', [
                'fields' => NFB_Fields::get_fields_config()
            ]);
        }
	}
}

