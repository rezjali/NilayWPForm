<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// برای استفاده از کلاس WP_List_Table
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
require_once(NFB_PLUGIN_PATH . 'includes/class-nfb-entries-list-table.php');


/**
 * کلاس NFB_Admin
 * مسئولیت مدیریت تمام بخش‌های پنل مدیریت افزونه را بر عهده دارد.
 */
class NFB_Admin
{
    private $entries_list_table;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes_nilay_form', [$this, 'add_form_meta_boxes']);
        add_action('save_post_nilay_form', [NFB_Core::class, 'save_form_meta_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // **بخش جدید: ثبت اکشن ایجکس برای دریافت داده‌های نمودار**
        add_action('wp_ajax_nfb_get_chart_data', [$this, 'ajax_get_chart_data']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('فرم‌ساز نیلای', 'nilay-form-builder'),
            __('فرم‌ساز نیلای', 'nilay-form-builder'),
            'manage_options',
            'nfb-main-menu',
            null,
            'dashicons-feedback',
            30
        );

        add_submenu_page('nfb-main-menu', __('همه فرم‌ها', 'nilay-form-builder'), __('همه فرم‌ها', 'nilay-form-builder'), 'manage_options', 'edit.php?post_type=nilay_form');
        add_submenu_page('nfb-main-menu', __('افزودن فرم', 'nilay-form-builder'), __('افزودن فرم', 'nilay-form-builder'), 'manage_options', 'post-new.php?post_type=nilay_form');

        $hook = add_submenu_page('nfb-main-menu', __('ورودی‌ها', 'nilay-form-builder'), __('ورودی‌ها', 'nilay-form-builder'), 'manage_options', 'nfb-entries', [$this, 'render_entries_page']);
        add_action("load-$hook", [$this, 'screen_options_for_entries']);

        add_submenu_page('nfb-main-menu', __('ابزارها', 'nilay-form-builder'), __('ابزارها', 'nilay-form-builder'), 'manage_options', 'nfb-tools', [$this, 'render_tools_page']);
    }

    public function enqueue_admin_scripts($hook)
    {
        global $post_type;

        // فقط در صفحات مربوط به افزونه فایل‌ها را بارگذاری کن
        if ($post_type === 'nilay_form' || strpos($hook, 'nfb-') !== false) {
            wp_enqueue_style('nfb-admin-style', NFB_PLUGIN_URL . 'assets/css/admin.css', [], NFB_VERSION);
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_media();
            
            // **بخش جدید: بارگذاری کتابخانه نمودار و اسکریپت ادمین**
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.1', true);
            wp_enqueue_script('nfb-admin-script', NFB_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable', 'chart-js'], NFB_VERSION, true);

            // **بخش جدید: ارسال داده به جاوااسکریپت**
            wp_localize_script('nfb-admin-script', 'nfb_admin_params', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nfb_admin_ajax_nonce'),
                'text' => [
                    'confirm_delete' => __('آیا از حذف این مورد مطمئن هستید؟', 'nilay-form-builder'),
                ]
            ]);
        }
    }

    public function add_form_meta_boxes()
    {
        add_meta_box('nfb_form_builder_mb', __('فیلد ساز', 'nilay-form-builder'), [NFB_Core::class, 'render_field_builder_metabox'], 'nilay_form', 'normal', 'high');
        add_meta_box('nfb_form_settings_mb', __('تنظیمات فرم', 'nilay-form-builder'), [$this, 'render_form_settings_metabox'], 'nilay_form', 'normal', 'default');
        add_meta_box('nfb_shortcode_mb', __('کد کوتاه', 'nilay-form-builder'), [$this, 'render_shortcode_metabox'], 'nilay_form', 'side', 'default');
    }

    public function render_shortcode_metabox($post)
    {
        if ($post->post_status === 'publish') {
            echo '<p>' . __('برای نمایش این فرم در نوشته‌ها و برگه‌ها، از کد کوتاه زیر استفاده کنید:', 'nilay-form-builder') . '</p>';
            echo '<input type="text" value="[nilay_form id=&quot;' . $post->ID . '&quot;]" readonly onfocus="this.select();" style="width:100%; text-align:left; direction:ltr;">';
        } else {
            echo '<p>' . __('پس از انتشار فرم، کد کوتاه در اینجا نمایش داده خواهد شد.', 'nilay-form-builder') . '</p>';
        }
    }

    public function render_form_settings_metabox($post)
    {
        wp_nonce_field('nfb_save_form_settings_nonce', 'nfb_form_settings_nonce');
        $settings = get_post_meta($post->ID, '_nfb_form_settings', true);
        $settings = is_array($settings) ? $settings : [];
        $fields = get_post_meta($post->ID, '_nfb_form_fields', true);
        ?>
        <div class="nfb-settings-tabs">
            <ul class="nfb-tabs-nav">
                <li><a href="#nfb-tab-confirmation" class="active"><?php _e('تاییدها', 'nilay-form-builder'); ?></a></li>
                <li><a href="#nfb-tab-notifications"><?php _e('اعلان‌ها', 'nilay-form-builder'); ?></a></li>
                <li><a href="#nfb-tab-payment"><?php _e('پرداخت', 'nilay-form-builder'); ?></a></li>
                <li><a href="#nfb-tab-restrictions"><?php _e('محدودیت‌ها', 'nilay-form-builder'); ?></a></li>
                <li><a href="#nfb-tab-actions"><?php _e('اقدامات', 'nilay-form-builder'); ?></a></li>
            </ul>

            <div id="nfb-tab-confirmation" class="nfb-tab-content active">
                <?php $this->render_confirmation_tab($settings['confirmation'] ?? []); ?>
            </div>
            <div id="nfb-tab-notifications" class="nfb-tab-content">
                <?php $this->render_notifications_tab($settings['notifications'] ?? [], $fields); ?>
            </div>
            <div id="nfb-tab-payment" class="nfb-tab-content">
                 <?php $this->render_payment_tab($settings['payment'] ?? []); ?>
            </div>
            <div id="nfb-tab-restrictions" class="nfb-tab-content">
                <?php $this->render_restrictions_tab($settings['restrictions'] ?? []); ?>
            </div>
            <div id="nfb-tab-actions" class="nfb-tab-content">
                <?php $this->render_actions_tab($settings['actions'] ?? [], $fields); ?>
            </div>
        </div>
        <?php
    }

    public function render_entries_page()
    {
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('ورودی‌های فرم', 'nilay-form-builder'); ?></h1>
            <hr class="wp-header-end">

            <div class="nfb-entries-filter">
                <form method="get">
                    <input type="hidden" name="page" value="nfb-entries">
                    <?php
                    wp_dropdown_posts([
                        'post_type'        => 'nilay_form',
                        'name'             => 'form_id',
                        'selected'         => $form_id,
                        'show_option_none' => __('یک فرم انتخاب کنید', 'nilay-form-builder'),
                    ]);
                    ?>
                    <button type="submit" class="button"><?php _e('نمایش ورودی‌ها', 'nilay-form-builder'); ?></button>
                </form>
            </div>
            
            <?php if ($form_id) : ?>
                <div class="nfb-entries-tabs-wrapper">
                     <h2 class="nav-tab-wrapper">
                        <a href="#entries-list" data-tab="list" class="nav-tab nav-tab-active"><?php _e('لیست ورودی‌ها', 'nilay-form-builder'); ?></a>
                        <a href="#entries-reports" data-tab="reports" class="nav-tab"><?php _e('گزارش‌ها', 'nilay-form-builder'); ?></a>
                    </h2>

                    <div id="tab-content-list" class="nfb-tab-pane active">
                        <form method="post">
                            <?php
                            $this->entries_list_table->prepare_items();
                            $this->entries_list_table->display();
                            ?>
                        </form>
                    </div>

                    <div id="tab-content-reports" class="nfb-tab-pane">
                        <?php $this->render_reports_tab($form_id); ?>
                    </div>
                </div>

            <?php else : ?>
                <p><?php _e('برای مشاهده ورودی‌ها، لطفا یک فرم را از لیست بالا انتخاب کنید.', 'nilay-form-builder'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * **بخش جدید: رندر کردن محتوای تب گزارش‌ها**
     */
    private function render_reports_tab($form_id)
    {
        $fields = get_post_meta($form_id, '_nfb_form_fields', true);
        $filterable_fields = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (in_array($field['type'], ['select', 'radio', 'checkbox', 'image_checkbox'])) {
                    $filterable_fields[$field['key']] = $field['label'];
                }
            }
        }
        ?>
        <div class="nfb-reports-container" data-form-id="<?php echo esc_attr($form_id); ?>">
            <h3><?php _e('تحلیل ورودی‌ها', 'nilay-form-builder'); ?></h3>
            
            <div class="nfb-reports-controls">
                <label for="nfb-reports-field-selector"><?php _e('یک فیلد برای تحلیل انتخاب کنید:', 'nilay-form-builder'); ?></label>
                <select id="nfb-reports-field-selector">
                    <option value=""><?php _e('انتخاب کنید...', 'nilay-form-builder'); ?></option>
                    <?php foreach ($filterable_fields as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nfb-chart-wrapper">
                <canvas id="nfbReportChart"></canvas>
            </div>
            
        </div>
        <?php
    }

    /**
     * **بخش جدید: دریافت داده‌های نمودار از طریق ایجکس**
     */
    public function ajax_get_chart_data()
    {
        check_ajax_referer('nfb_admin_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access Denied']);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';

        if (empty($form_id) || empty($field_key)) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        $chart_data = self::get_chart_data($form_id, $field_key);

        wp_send_json_success($chart_data);
    }
    
    /**
     * **بخش جدید: پردازش و آماده‌سازی داده‌ها برای نمودار**
     */
    private static function get_chart_data($form_id, $field_key)
    {
        global $wpdb;
        $meta_key = '_nfb_field_' . $field_key;
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'nilay_form_entry' AND p.post_parent = %d AND pm.meta_key = %s",
            $form_id,
            $meta_key
        ));

        $data_counts = [];
        foreach ($results as $result) {
            $values = maybe_unserialize($result);
            if (!is_array($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                if (!isset($data_counts[$value])) {
                    $data_counts[$value] = 0;
                }
                $data_counts[$value]++;
            }
        }
        
        arsort($data_counts);

        return [
            'labels' => array_keys($data_counts),
            'data'   => array_values($data_counts),
        ];
    }


    // سایر توابع کلاس (render_confirmation_tab و ...) بدون تغییر باقی می‌مانند
    // ...
    public function render_tools_page() { /* ... code ... */ }
    private function render_confirmation_tab($settings) { /* ... code ... */ }
    private function render_notifications_tab($settings, $fields) { /* ... code ... */ }
    private function render_payment_tab($settings) { /* ... code ... */ }
    private function render_restrictions_tab($settings) { /* ... code ... */ }
    private function render_actions_tab($settings, $fields) { /* ... code ... */ }
    public function screen_options_for_entries() {
        $option = 'per_page';
        $args = [
            'label' => __('تعداد ورودی‌ها در هر صفحه', 'nilay-form-builder'),
            'default' => 20,
            'option' => 'entries_per_page'
        ];
        add_screen_option($option, $args);
        $this->entries_list_table = new NFB_Entries_List_Table();
    }
}

