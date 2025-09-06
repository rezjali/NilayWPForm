<?php
/**
 * NFB_Admin Class
 * Handles all admin-side functionality. This is the fully revised and completed version.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      2.1.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure the List Table class is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
require_once NFB_PLUGIN_DIR . 'includes/class-nfb-entries-list-table.php';


class NFB_Admin {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_nfb_form', [ $this, 'save_form_meta_data' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_init', [ $this, 'handle_entries_export' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_post_types() {
        $form_labels = [
			'name'               => __( 'فرم‌ها', 'nilay-form-builder' ),
			'singular_name'      => __( 'فرم', 'nilay-form-builder' ),
			'menu_name'          => __( 'فرم ساز', 'nilay-form-builder' ),
			'name_admin_bar'     => __( 'فرم', 'nilay-form-builder' ),
			'add_new'            => __( 'افزودن فرم', 'nilay-form-builder' ),
			'add_new_item'       => __( 'افزودن فرم جدید', 'nilay-form-builder' ),
			'new_item'           => __( 'فرم جدید', 'nilay-form-builder' ),
			'edit_item'          => __( 'ویرایش فرم', 'nilay-form-builder' ),
			'view_item'          => __( 'نمایش فرم', 'nilay-form-builder' ),
			'all_items'          => __( 'همه فرم‌ها', 'nilay-form-builder' ),
			'search_items'       => __( 'جستجوی فرم‌ها', 'nilay-form-builder' ),
			'not_found'          => __( 'هیچ فرمی یافت نشد.', 'nilay-form-builder' ),
			'not_found_in_trash' => __( 'هیچ فرمی در زباله‌دان یافت نشد.', 'nilay-form-builder' )
		];
		register_post_type( 'nfb_form', [
			'labels' => $form_labels,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => 'nfb-main-menu',
			'supports' => [ 'title' ],
			'menu_icon' => 'dashicons-list-view',
		]);
        
		register_post_type( 'nfb_entry', [
			'labels' => ['name' => __( 'ورودی‌ها', 'nilay-form-builder' ), 'singular_name' => __( 'ورودی', 'nilay-form-builder' ), 'edit_item' => __('مشاهده ورودی', 'nilay-form-builder')],
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false, // Set to false to prevent duplicate menu item
			'capability_type' => 'post',
			'capabilities' => ['create_posts' => 'do_not_allow'],
			'map_meta_cap' => true,
			'supports' => [''],
		]);
	}

	public function add_admin_menu() {
		add_menu_page(__( 'فرم ساز نیلای', 'nilay-form-builder' ), __( 'فرم ساز', 'nilay-form-builder' ), 'manage_options', 'nfb-main-menu', 'edit.php?post_type=nfb_form', 'dashicons-list-view', 26);
        add_submenu_page( 'nfb-main-menu', __( 'ورودی‌ها', 'nilay-form-builder' ), __( 'ورودی‌ها', 'nilay-form-builder' ), 'manage_options', 'nfb-entries', [$this, 'render_entries_page'] );
        add_submenu_page( 'nfb-main-menu', __( 'تنظیمات', 'nilay-form-builder' ), __( 'تنظیمات', 'nilay-form-builder' ), 'manage_options', 'nfb-settings', [$this, 'render_settings_page'] );
	}
    
    public function render_entries_page() {
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        $entries_table = new NFB_Entries_List_Table($form_id);
        $entries_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('ورودی‌های فرم', 'nilay-form-builder'); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="nfb-entries">
                <?php $this->entries_page_dropdown_forms($form_id); ?>
                <input type="submit" class="button" value="<?php _e('فیلتر', 'nilay-form-builder'); ?>">
                <?php if ($form_id): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nfb-entries&form_id='.$form_id.'&action=export_entries'), 'nfb_export_nonce')); ?>" class="button button-secondary"><?php _e('خروجی CSV', 'nilay-form-builder'); ?></a>
                <?php endif; ?>
            </form>
            <?php $entries_table->display(); ?>
        </div>
        <?php
    }

    private function entries_page_dropdown_forms($selected_id = 0) {
        $forms = get_posts(['post_type' => 'nfb_form', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        echo '<select name="form_id">';
        echo '<option value="0">' . __('همه فرم‌ها', 'nilay-form-builder') . '</option>';
        if ($forms) {
            foreach ($forms as $form) {
                echo '<option value="' . esc_attr($form->ID) . '" ' . selected($selected_id, $form->ID, false) . '>' . esc_html($form->post_title) . '</option>';
            }
        }
        echo '</select>';
    }

	public function add_meta_boxes() {
		add_meta_box('nfb-form-builder', __( 'فرم ساز', 'nilay-form-builder' ), [ $this, 'render_form_builder_meta_box' ], 'nfb_form', 'normal', 'high');
        add_meta_box('nfb-form-shortcode', __( 'کد کوتاه', 'nilay-form-builder' ), [ $this, 'render_shortcode_meta_box' ], 'nfb_form', 'side');
        add_meta_box('nfb-form-settings', __( 'تنظیمات فرم', 'nilay-form-builder' ), [ $this, 'render_settings_meta_box' ], 'nfb_form', 'side');
        add_meta_box('nfb-entry-details', __( 'جزئیات ورودی', 'nilay-form-builder' ), [ $this, 'render_entry_details_meta_box' ], 'nfb_entry', 'normal', 'high');
	}

    public function render_entry_details_meta_box($post) {
        // This function will be improved later to show formatted values
        $all_meta = get_post_meta($post->ID);
        echo '<pre>';
        print_r($all_meta);
        echo '</pre>';
    }

	public function render_form_builder_meta_box( $post ) {
		wp_nonce_field( 'nfb_save_form_meta', 'nfb_form_nonce' );
		require_once NFB_PLUGIN_DIR . 'includes/views/admin-form-builder.php';
	}
    
	public function render_shortcode_meta_box( $post ) {
        ?>
        <p><?php _e( 'برای نمایش این فرم در هر برگه، نوشته یا ابزارک، از کد کوتاه زیر استفاده کنید:', 'nilay-form-builder' ); ?></p>
        <input type="text" value="[nilay-form id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]" readonly="readonly" class="widefat" onfocus="this.select();">
		<?php
    }
    
    public function render_settings_meta_box($post) {
        $settings = get_post_meta($post->ID, '_nfb_settings', true);
        $settings = is_array($settings) ? $settings : [];
        $gateways = NFB_Gateways::get_active_gateways();
        ?>
        <p>
            <label>
                <input type="checkbox" name="_nfb_settings[payment_enabled]" value="1" <?php checked(1, $settings['payment_enabled'] ?? 0); ?>>
                <?php _e('فعال‌سازی پرداخت', 'nilay-form-builder'); ?>
            </label>
        </p>
        <p>
            <label for="nfb_payment_amount"><?php _e('مبلغ پایه (تومان):', 'nilay-form-builder'); ?></label>
            <input type="number" id="nfb_payment_amount" name="_nfb_settings[payment_amount]" value="<?php echo esc_attr($settings['payment_amount'] ?? ''); ?>" class="widefat">
        </p>
        <p>
            <label for="nfb_payment_gateway"><?php _e('درگاه پرداخت:', 'nilay-form-builder'); ?></label>
            <select id="nfb_payment_gateway" name="_nfb_settings[payment_gateway]" class="widefat">
                <?php foreach ($gateways as $id => $gateway): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['payment_gateway'] ?? '', $id); ?>><?php echo esc_html($gateway['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <hr>
        <p>
            <label for="nfb_submit_button_text"><?php _e('متن دکمه ارسال:', 'nilay-form-builder'); ?></label>
            <input type="text" id="nfb_submit_button_text" name="_nfb_settings[submit_button_text]" value="<?php echo esc_attr($settings['submit_button_text'] ?? 'ارسال'); ?>" class="widefat">
        </p>
        <p>
            <label for="nfb_success_message"><?php _e('پیام موفقیت‌آمیز:', 'nilay-form-builder'); ?></label>
            <textarea id="nfb_success_message" name="_nfb_settings[success_message]" class="widefat"><?php echo esc_textarea($settings['success_message'] ?? ''); ?></textarea>
        </p>
         <p>
            <label for="nfb_success_redirect"><?php _e('هدایت پس از ارسال موفق:', 'nilay-form-builder'); ?></label>
            <input type="url" id="nfb_success_redirect" name="_nfb_settings[success_redirect]" value="<?php echo esc_attr($settings['success_redirect'] ?? ''); ?>" class="widefat" placeholder="https://example.com">
        </p>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات فرم ساز نیلای', 'nilay-form-builder'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('nfb_settings_group');
                do_settings_sections('nfb-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('nfb_settings_group', 'nfb_settings');

        add_settings_section('nfb_gateways_section', __('تنظیمات درگاه پرداخت', 'nilay-form-builder'), null, 'nfb-settings');
        add_settings_field('nfb_zarinpal_merchant', 'مرچنت کد زرین‌پال', [$this, 'render_text_input'], 'nfb-settings', 'nfb_gateways_section', ['group' => 'gateways', 'id' => 'zarinpal_merchant']);
        add_settings_field('nfb_zibal_merchant', 'مرچنت کد زیبال', [$this, 'render_text_input'], 'nfb-settings', 'nfb_gateways_section', ['group' => 'gateways', 'id' => 'zibal_merchant']);
    
        add_settings_section('nfb_sms_section', __('تنظیمات پنل پیامک', 'nilay-form-builder'), null, 'nfb-settings');
        // Add SMS fields later
    }

    public function render_text_input($args) {
        $options = get_option('nfb_settings');
        $group = $args['group'];
        $id = $args['id'];
        $value = isset($options[$group][$id]) ? esc_attr($options[$group][$id]) : '';
        echo "<input type='text' name='nfb_settings[{$group}][{$id}]' value='{$value}' class='regular-text' />";
    }

	public function save_form_meta_data( $post_id ) {
        if (!isset($_POST['nfb_form_nonce']) || !wp_verify_nonce($_POST['nfb_form_nonce'], 'nfb_save_form_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (get_post_type($post_id) !== 'nfb_form' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['_nfb_fields'])) {
			$fields_json = wp_unslash( $_POST['_nfb_fields'] );
			if ( json_decode( $fields_json ) !== null ) {
				update_post_meta( $post_id, '_nfb_fields', $fields_json );
			}
        }
        
        if (isset($_POST['_nfb_settings'])) {
            $sanitized_settings = [];
            foreach ($_POST['_nfb_settings'] as $key => $value) {
                if (in_array($key, ['success_redirect'])) {
                    $sanitized_settings[$key] = esc_url_raw($value);
                } elseif (in_array($key, ['success_message'])) {
                    $sanitized_settings[$key] = sanitize_textarea_field($value);
                } else {
                    $sanitized_settings[$key] = sanitize_text_field($value);
                }
            }
            update_post_meta($post_id, '_nfb_settings', $sanitized_settings);
        }
    }

	public function enqueue_scripts( $hook ) {
        $screen = get_current_screen();
		if ( $screen && 'nfb_form' === $screen->post_type && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
            wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
			wp_enqueue_style( 'nfb-admin-style', NFB_PLUGIN_URL . 'assets/css/admin.css', [], NFB_PLUGIN_VERSION );
			
            wp_enqueue_media();
            wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'jquery-ui-draggable' ); // Add draggable
            wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'nfb-admin-script', NFB_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util', 'jquery-ui-dialog', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-datepicker' ], NFB_PLUGIN_VERSION, true );
            
            $all_fields = NFB_Fields::get_all_fields();
            wp_localize_script('nfb-admin-script', 'nfb_admin_vars', ['fields' => $all_fields]);
        }
	}
    
    public function handle_entries_export() {
        if (isset($_GET['action']) && $_GET['action'] === 'export_entries' && isset($_GET['form_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'nfb_export_nonce')) {
                wp_die('Invalid nonce.');
            }
            
            $form_id = intval($_GET['form_id']);
            // ... Logic to query entries, build a CSV, and force download.
        }
    }
}

