<?php
// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس NFB_Core
 * مسئولیت مدیریت انواع پست سفارشی (فرم‌ها و ورودی‌ها) و فیلدساز را بر عهده دارد.
 */
class NFB_Core
{
    /**
     * سازنده کلاس که هوک‌های اصلی را ثبت می‌کند.
     */
    public function __construct()
    {
        add_action('init', [self::class, 'register_post_types']);
    }

    /**
     * ثبت انواع پست سفارشی (CPT) برای فرم‌ها و ورودی‌ها.
     * این متد باید static باشد تا در هنگام فعال‌سازی افزونه قابل فراخوانی باشد.
     */
    public static function register_post_types()
    {
        // CPT for Forms
        $form_labels = [
            'name'               => __('فرم‌ها', 'nilay-form-builder'),
            'singular_name'      => __('فرم', 'nilay-form-builder'),
            'menu_name'          => __('فرم‌ساز نیلای', 'nilay-form-builder'),
            'name_admin_bar'     => __('فرم', 'nilay-form-builder'),
            'add_new'            => __('افزودن فرم', 'nilay-form-builder'),
            'add_new_item'       => __('افزودن فرم جدید', 'nilay-form-builder'),
            'new_item'           => __('فرم جدید', 'nilay-form-builder'),
            'edit_item'          => __('ویرایش فرم', 'nilay-form-builder'),
            'view_item'          => __('مشاهده فرم', 'nilay-form-builder'),
            'all_items'          => __('همه فرم‌ها', 'nilay-form-builder'),
        ];
        $form_args = [
            'labels'             => $form_labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
            'menu_icon'          => 'dashicons-feedback',
        ];
        register_post_type('nilay_form', $form_args);

        // CPT for Entries
        $entry_labels = [
            'name'               => __('ورودی‌ها', 'nilay-form-builder'),
            'singular_name'      => __('ورودی', 'nilay-form-builder'),
            'edit_item'          => __('مشاهده ورودی', 'nilay-form-builder'),
            'all_items'          => __('همه ورودی‌ها', 'nilay-form-builder'),
        ];
        $entry_args = [
            'labels'             => $entry_labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => [],
        ];
        register_post_type('nilay_form_entry', $entry_args);
    }

    /**
     * رندر کردن متاباکس فیلدساز.
     */
    public static function render_field_builder_metabox($post)
    {
        wp_nonce_field('nfb_save_fields_nonce', 'nfb_fields_nonce');
        $fields = get_post_meta($post->ID, '_nfb_form_fields', true);
        if (!is_array($fields)) $fields = [];
        ?>
        <div id="nfb-field-builder-wrapper">
            <div id="nfb-fields-container" class="nfb-sortable-list">
                <?php if (!empty($fields)) : foreach ($fields as $index => $field) : ?>
                    <?php self::render_field_builder_row($index, $field, $fields); ?>
                <?php endforeach; endif; ?>
            </div>
            <a href="#" id="nfb-add-field" class="button button-primary"><?php _e('افزودن فیلد جدید', 'nilay-form-builder'); ?></a>
        </div>
        <?php
    }

    /**
     * رندر کردن یک ردیف در فیلدساز.
     */
    public static function render_field_builder_row($index, $field_data = [], $all_fields = [])
    {
        $label = $field_data['label'] ?? '';
        $key = $field_data['key'] ?? '';
        $type = $field_data['type'] ?? 'text';
        $options = $field_data['options'] ?? '';
        $required = $field_data['required'] ?? 0;
        $width_class = $field_data['width_class'] ?? 'full';
        $help_text = $field_data['help_text'] ?? '';
        $placeholder = $field_data['placeholder'] ?? '';
        $conditional_logic = wp_parse_args($field_data['conditional_logic'] ?? [], [
            'enabled'      => 0,
            'action'       => 'show',
            'target_field' => '',
            'operator'     => 'is',
            'value'        => '',
        ]);
        $image_options = $field_data['image_options'] ?? [];
        $display_columns = $field_data['display_columns'] ?? 3;
        ?>
        <div class="nfb-field-row" data-index="<?php echo esc_attr($index); ?>" data-field-key="<?php echo esc_attr($key); ?>">
            <div class="nfb-field-header">
                <span class="dashicons dashicons-move handle"></span>
                <strong><?php echo esc_html($label) ?: __('فیلد جدید', 'nilay-form-builder'); ?></strong> (<span class="nfb-field-type-display"><?php echo esc_html($type); ?></span>)
                <a href="#" class="nfb-copy-field" title="<?php _e('کپی کردن فیلد', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                <a href="#" class="nfb-toggle-field-details"><?php _e('جزئیات', 'nilay-form-builder'); ?></a>
                <a href="#" class="nfb-remove-field" title="<?php _e('حذف فیلد', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-trash"></span></a>
            </div>
            <div class="nfb-field-details" style="display:none;">
                <div class="nfb-field-inputs">
                    <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php _e('عنوان فیلد', 'nilay-form-builder'); ?>" class="field-label-input">
                    <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>" placeholder="<?php _e('کلید متا (انگلیسی)', 'nilay-form-builder'); ?>" class="field-key-input">
                    <select class="nfb-field-type-selector" name="nfb_fields[<?php echo esc_attr($index); ?>][type]">
                        <optgroup label="<?php _e('فیلدهای پایه', 'nilay-form-builder'); ?>">
                            <option value="text" <?php selected($type, 'text'); ?>><?php _e('متن', 'nilay-form-builder'); ?></option>
                            <option value="textarea" <?php selected($type, 'textarea'); ?>><?php _e('متن بلند', 'nilay-form-builder'); ?></option>
                            <option value="number" <?php selected($type, 'number'); ?>><?php _e('عدد', 'nilay-form-builder'); ?></option>
                            <option value="email" <?php selected($type, 'email'); ?>><?php _e('ایمیل', 'nilay-form-builder'); ?></option>
                            <option value="url" <?php selected($type, 'url'); ?>><?php _e('وب‌سایت', 'nilay-form-builder'); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('فیلدهای انتخاب', 'nilay-form-builder'); ?>">
                            <option value="select" <?php selected($type, 'select'); ?>><?php _e('لیست کشویی', 'nilay-form-builder'); ?></option>
                            <option value="checkbox" <?php selected($type, 'checkbox'); ?>><?php _e('چک‌باکس', 'nilay-form-builder'); ?></option>
                            <option value="image_checkbox" <?php selected($type, 'image_checkbox'); ?>><?php _e('چک‌باکس تصویری', 'nilay-form-builder'); ?></option>
                            <option value="radio" <?php selected($type, 'radio'); ?>><?php _e('دکمه رادیویی', 'nilay-form-builder'); ?></option>
                        </optgroup>
                         <optgroup label="<?php _e('فیلدهای پیشرفته', 'nilay-form-builder'); ?>">
                            <option value="signature" <?php selected( $type, 'signature' ); ?>><?php _e( 'امضا', 'nilay-form-builder' ); ?></option>
                            <option value="file" <?php selected( $type, 'file' ); ?>><?php _e( 'آپلود فایل', 'nilay-form-builder' ); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('فیلدهای ساختاری', 'nilay-form-builder'); ?>">
                            <option value="section_title" <?php selected($type, 'section_title'); ?>><?php _e('عنوان بخش', 'nilay-form-builder'); ?></option>
                            <option value="html_content" <?php selected($type, 'html_content'); ?>><?php _e('محتوای HTML', 'nilay-form-builder'); ?></option>
                            <option value="page_break" <?php selected( $type, 'page_break' ); ?>><?php _e( 'صفحه‌بندی/گام بعدی', 'nilay-form-builder' ); ?></option>
                        </optgroup>
                    </select>
                    <textarea name="nfb_fields[<?php echo esc_attr($index); ?>][options]" class="nfb-field-options" placeholder="<?php _e('گزینه‌ها (هر کدام در یک خط) یا محتوای HTML', 'nilay-form-builder'); ?>" style="<?php echo in_array($type, ['select', 'checkbox', 'radio', 'html_content']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea($options); ?></textarea>
                </div>

                <div class="nfb-field-settings-panel nfb-image-checkbox-settings" style="<?php echo ($type === 'image_checkbox') ? '' : 'display:none;'; ?>">
                    <!-- Image Checkbox settings UI -->
                </div>

                <div class="nfb-field-settings-panel">
                    <h4><?php _e('تنظیمات پایه', 'nilay-form-builder'); ?></h4>
                    <label><?php _e('Placeholder:', 'nilay-form-builder'); ?> <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>"></label>
                    <label><?php _e('متن راهنما:', 'nilay-form-builder'); ?> <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][help_text]" value="<?php echo esc_attr($help_text); ?>"></label>
                </div>

                <div class="nfb-field-rules">
                     <h4><?php _e('تنظیمات نمایش و اعتبارسنجی', 'nilay-form-builder'); ?></h4>
                    <label>
                        <?php _e('عرض فیلد:', 'nilay-form-builder'); ?>
                        <select name="nfb_fields[<?php echo esc_attr($index); ?>][width_class]">
                            <option value="full" <?php selected('full', $width_class); ?>><?php _e('عرض کامل', 'nilay-form-builder'); ?></option>
                            <option value="half" <?php selected('half', $width_class); ?>><?php _e('یک دوم', 'nilay-form-builder'); ?></option>
                        </select>
                    </label>
                    <label><input type="checkbox" name="nfb_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(1, $required); ?>> <?php _e('الزامی', 'nilay-form-builder'); ?></label>
                </div>
                 <div class="nfb-field-rules nfb-conditional-logic-wrapper">
                    <h4><?php _e('منطق شرطی', 'nilay-form-builder'); ?></h4>
                    <!-- Conditional logic UI -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * ذخیره کردن متادیتای فیلدها و تنظیمات فرم.
     */
    public static function save_form_meta_data($post_id)
    {
        // Save Fields
        if (isset($_POST['nfb_fields_nonce']) && wp_verify_nonce($_POST['nfb_fields_nonce'], 'nfb_save_fields_nonce')) {
            $fields_data = isset($_POST['nfb_fields']) ? self::sanitize_field_builder_data($_POST['nfb_fields']) : [];
            update_post_meta($post_id, '_nfb_form_fields', $fields_data);
        }

        // Save Settings
        if (isset($_POST['nfb_form_settings_nonce']) && wp_verify_nonce($_POST['nfb_form_settings_nonce'], 'nfb_save_form_settings_nonce')) {
            $settings_data = isset($_POST['nfb_settings']) ? $_POST['nfb_settings'] : [];
            // Add sanitization for settings here
            update_post_meta($post_id, '_nfb_form_settings', $settings_data);
        }
    }

    /**
     * پاک‌سازی داده‌های فیلدساز.
     */
    private static function sanitize_field_builder_data($fields)
    {
        $sanitized_data = [];
        if (!is_array($fields)) return $sanitized_data;

        foreach ($fields as $field) {
            if (empty($field['label']) || empty($field['key'])) continue;
            // Add detailed sanitization for each field property here
            $sanitized_field = [
                'label'   => sanitize_text_field($field['label']),
                'key'     => sanitize_key($field['key']),
                'type'    => sanitize_text_field($field['type']),
                'options' => sanitize_textarea_field($field['options']),
                'required' => isset($field['required']) ? 1 : 0,
                'width_class' => sanitize_key($field['width_class'] ?? 'full'),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'help_text' => sanitize_text_field($field['help_text'] ?? ''),
            ];
            $sanitized_data[] = $sanitized_field;
        }
        return $sanitized_data;
    }

    /**
     * رندر کردن فیلدها در فرانت‌اند.
     */
    public static function render_fields_recursive($fields, $post_id, $row_data = [], $name_prefix = 'nfb_fields')
    {
        foreach ($fields as $field) {
            $field_key = $field['key'];
            $field_name = $name_prefix . '[' . $field_key . ']';
            $field_id = str_replace(['][', '[', ']'], '_', $field_name);
            $field_id = rtrim($field_id, '_');
            $width_class = 'nfb-form-group nfb-field-col-' . ($field['width_class'] ?? 'full');

            if ($field['type'] === 'page_break') {
                echo '</div><div class="nfb-step">'; // Close previous step, open new one
                continue;
            }
             if ($field['type'] === 'section_title') {
                echo '<div class="nfb-form-group nfb-field-col-full"><h3>' . esc_html($field['label']) . '</h3></div>';
                continue;
            }

            echo '<div class="' . esc_attr($width_class) . '">';
            echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field['label']) . (!empty($field['required']) ? ' <span class="nfb-required">*</span>' : '') . '</label>';

            switch ($field['type']) {
                case 'text':
                case 'email':
                case 'number':
                case 'url':
                    echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                    break;
                case 'textarea':
                    echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '"></textarea>';
                    break;
                case 'select':
                    echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                    $options = preg_split('/\\r\\n|\\r|\\n/', $field['options']);
                    foreach ($options as $option) echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                    echo '</select>';
                    break;
                case 'signature':
                    echo '<div class="nfb-signature-wrapper">';
                    echo '<canvas id="' . esc_attr($field_id) . '_canvas" class="nfb-signature-canvas"></canvas>';
                    echo '<a href="#" class="nfb-signature-clear">' . __('پاک کردن', 'nilay-form-builder') . '</a>';
                    echo '<input type="hidden" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="nfb-signature-input">';
                    echo '</div>';
                    break;
            }

            if (!empty($field['help_text'])) {
                echo '<p class="nfb-help-text">' . esc_html($field['help_text']) . '</p>';
            }
            echo '</div>';
        }
    }

    /**
     * ذخیره کردن مقادیر فیلدهای یک ورودی.
     */
    public static function save_entry_fields($entry_id, $fields, $posted_data)
    {
        foreach ($fields as $field) {
            $field_key = $field['key'];
            if (isset($posted_data[$field_key])) {
                $value = self::sanitize_field_value($posted_data[$field_key], $field['type']);
                update_post_meta($entry_id, '_nfb_field_' . $field_key, $value);
            }
        }
    }

    /**
     * پاک‌سازی مقدار یک فیلد بر اساس نوع آن.
     */
    private static function sanitize_field_value($value, $type)
    {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'textarea':
                 return sanitize_textarea_field($value);
            case 'signature':
                // The value is a base64 data URL, it's generally safe but we can add checks
                if (preg_match('/^data:image\/png;base64,/', $value)) {
                    return $value;
                }
                return '';
            default:
                return sanitize_text_field($value);
        }
    }
}

