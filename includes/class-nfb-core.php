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
    public function __construct()
    {
        add_action('init', [self::class, 'register_post_types']);
    }

    public static function register_post_types()
    {
        $form_labels = [
            'name'               => __('فرم‌ها', 'nilay-form-builder'),
            'singular_name'      => __('فرم', 'nilay-form-builder'),
            'menu_name'          => __('فرم‌ها', 'nilay-form-builder'),
            'add_new'            => __('افزودن فرم', 'nilay-form-builder'),
            'add_new_item'       => __('افزودن فرم جدید', 'nilay-form-builder'),
        ];
        $form_args = [
            'labels'             => $form_labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'supports'           => ['title'],
        ];
        register_post_type('nilay_form', $form_args);

        $entry_labels = [
            'name'               => __('ورودی‌ها', 'nilay-form-builder'),
            'singular_name'      => __('ورودی', 'nilay-form-builder'),
            'edit_item'          => __('مشاهده ورودی', 'nilay-form-builder'),
        ];
        $entry_args = [
            'labels'             => $entry_labels,
            'public'             => false,
            'show_ui'            => false,
            'capability_type'    => 'post',
            'capabilities'       => ['create_posts' => 'do_not_allow'],
            'map_meta_cap'       => true,
            'supports'           => [],
        ];
        register_post_type('nilay_form_entry', $entry_args);
    }

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
        
        <script type="text/html" id="nfb-field-template-wrapper">
            <?php self::render_field_builder_row('__INDEX__'); ?>
        </script>
        <?php
    }

    public static function render_field_builder_row($index, $field_data = [], $all_fields = [])
    {
        $field_data = wp_parse_args($field_data, [
            'label' => '', 'key' => '', 'type' => 'text', 'options' => '', 'required' => 0,
            'width_class' => 'full', 'help_text' => '', 'placeholder' => '',
            'conditional_logic' => ['enabled' => 0, 'action' => 'show', 'target_field' => '', 'operator' => 'is', 'value' => ''],
        ]);
        extract($field_data);
        ?>
        <div class="nfb-field-row" data-index="<?php echo esc_attr($index); ?>" data-field-key="<?php echo esc_attr($key); ?>">
            <div class="nfb-field-header">
                <span class="dashicons dashicons-move handle"></span>
                <strong><?php echo esc_html($label) ?: __('فیلد جدید', 'nilay-form-builder'); ?></strong> (<span class="nfb-field-type-display"><?php echo esc_html($type); ?></span>)
                <div class="nfb-field-actions">
                    <a href="#" class="nfb-copy-field" title="<?php _e('کپی کردن فیلد', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                    <a href="#" class="nfb-toggle-field-details"><?php _e('ویرایش', 'nilay-form-builder'); ?></a>
                    <a href="#" class="nfb-remove-field" title="<?php _e('حذف فیلد', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-trash"></span></a>
                </div>
            </div>
            <div class="nfb-field-details" style="display:none;">
                <div class="nfb-field-inputs">
                    <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php _e('عنوان فیلد', 'nilay-form-builder'); ?>" class="field-label-input">
                    <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>" placeholder="<?php _e('کلید متا (انگلیسی)', 'nilay-form-builder'); ?>" class="field-key-input">
                    <select class="nfb-field-type-selector" name="nfb_fields[<?php echo esc_attr($index); ?>][type]">
                        <optgroup label="<?php _e('پایه', 'nilay-form-builder'); ?>">
                            <option value="text" <?php selected($type, 'text'); ?>><?php _e('متن', 'nilay-form-builder'); ?></option>
                            <option value="textarea" <?php selected($type, 'textarea'); ?>><?php _e('متن بلند', 'nilay-form-builder'); ?></option>
                            <option value="number" <?php selected($type, 'number'); ?>><?php _e('عدد', 'nilay-form-builder'); ?></option>
                            <option value="email" <?php selected($type, 'email'); ?>><?php _e('ایمیل', 'nilay-form-builder'); ?></option>
                            <option value="url" <?php selected($type, 'url'); ?>><?php _e('وب‌سایت', 'nilay-form-builder'); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('انتخاب', 'nilay-form-builder'); ?>">
                            <option value="select" <?php selected($type, 'select'); ?>><?php _e('لیست کشویی', 'nilay-form-builder'); ?></option>
                            <option value="checkbox" <?php selected($type, 'checkbox'); ?>><?php _e('چک‌باکس', 'nilay-form-builder'); ?></option>
                            <option value="radio" <?php selected($type, 'radio'); ?>><?php _e('دکمه رادیویی', 'nilay-form-builder'); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('پیشرفته', 'nilay-form-builder'); ?>">
                            <option value="signature" <?php selected($type, 'signature'); ?>><?php _e('امضا', 'nilay-form-builder'); ?></option>
                            <option value="file" <?php selected($type, 'file'); ?>><?php _e('آپلود فایل', 'nilay-form-builder'); ?></option>
                        </optgroup>
                        <optgroup label="<?php _e('ساختاری', 'nilay-form-builder'); ?>">
                            <option value="section_title" <?php selected($type, 'section_title'); ?>><?php _e('عنوان بخش', 'nilay-form-builder'); ?></option>
                            <option value="html_content" <?php selected($type, 'html_content'); ?>><?php _e('محتوای HTML', 'nilay-form-builder'); ?></option>
                            <option value="page_break" <?php selected($type, 'page_break'); ?>><?php _e('صفحه‌بندی/گام بعدی', 'nilay-form-builder'); ?></option>
                        </optgroup>
                    </select>
                </div>
                <textarea name="nfb_fields[<?php echo esc_attr($index); ?>][options]" class="nfb-field-options" placeholder="<?php _e('گزینه‌ها (هر کدام در یک خط) یا محتوای HTML', 'nilay-form-builder'); ?>" style="<?php echo in_array($type, ['select', 'checkbox', 'radio', 'html_content']) ? '' : 'display:none;'; ?>"><?php echo esc_textarea($options); ?></textarea>
                
                <div class="nfb-field-rules">
                    <label><?php _e('Placeholder:', 'nilay-form-builder'); ?> <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>"></label>
                    <label><?php _e('متن راهنما:', 'nilay-form-builder'); ?> <input type="text" name="nfb_fields[<?php echo esc_attr($index); ?>][help_text]" value="<?php echo esc_attr($help_text); ?>"></label>
                    <label><?php _e('عرض فیلد:', 'nilay-form-builder'); ?>
                        <select name="nfb_fields[<?php echo esc_attr($index); ?>][width_class]">
                            <option value="full" <?php selected('full', $width_class); ?>><?php _e('عرض کامل', 'nilay-form-builder'); ?></option>
                            <option value="half" <?php selected('half', $width_class); ?>><?php _e('یک دوم', 'nilay-form-builder'); ?></option>
                        </select>
                    </label>
                    <label><input type="checkbox" name="nfb_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(1, $required); ?>> <?php _e('الزامی', 'nilay-form-builder'); ?></label>
                </div>
            </div>
        </div>
        <?php
    }

    public static function save_form_meta_data($post_id) { /* Full implementation remains the same */ }
    private static function sanitize_field_builder_data($fields) { /* Full implementation remains the same */ }
    public static function render_fields_recursive($fields, $post_id) { /* Full implementation remains the same */ }
    public static function save_entry_fields($entry_id, $fields, $posted_data) { /* Full implementation remains the same */ }
    private static function sanitize_field_value($value, $type) { /* Full implementation remains the same */ }
}

