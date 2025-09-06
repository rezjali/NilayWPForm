<?php
/**
 * View for the Form Builder metabox.
 * This file contains the HTML structure and JS templates for the advanced field builder.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes/Views
 * @author     Reza Jalali
 * @since      2.1.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
$form_fields_json = get_post_meta( $post->ID, '_nfb_fields', true );
if ( empty( $form_fields_json ) ) $form_fields_json = '[]';
?>

<input type="hidden" name="_nfb_fields" id="nfb_form_fields_json" value="<?php echo esc_attr( $form_fields_json ); ?>">

<div id="nfb-form-builder-wrapper">
    <div id="nfb-field-types-container">
        <?php
        $field_groups = NFB_Fields::get_all_fields();
        foreach ( $field_groups as $group_key => $group ) : ?>
            <div class="nfb-field-group-wrapper">
                <h3><?php echo esc_html( $group['label'] ); ?></h3>
                <ul class="nfb-field-types">
                    <?php foreach ( $group['fields'] as $type => $field ) : ?>
                    <li>
                        <button type="button" class="button nfb-add-field-btn" data-field-type="<?php echo esc_attr( $type ); ?>">
                            <span class="dashicons <?php echo esc_attr( $field['icon'] ); ?>"></span>
                            <?php echo esc_html( $field['label'] ); ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="nfb-form-builder-main">
        <div id="nfb-form-fields-list" class="nfb-sortable-list">
             <!-- Fields will be rendered here by JavaScript -->
        </div>
        <p class="nfb-empty-message" style="display:none; text-align: center; padding: 50px; border: 2px dashed #ccc; margin-top: 15px;"><?php _e( 'برای شروع، یک فیلد را از ستون کناری به اینجا بکشید یا روی آن کلیک کنید.', 'nilay-form-builder' ); ?></p>
    </div>
</div>

<!-- All JavaScript templates are included below -->
<div id="nfb-js-templates" style="display: none;">

    <!-- Main Field Row Template -->
    <script type="text/html" id="tmpl-nfb-field-row">
        <div class="nfb-field-row" data-field-id="{{data.id}}" data-field-type="{{data.type}}" data-field-key="{{data.meta_key}}">
            <div class="nfb-field-header">
                <span class="dashicons dashicons-move handle"></span>
                <strong>{{data.label}}</strong> (<span class="nfb-field-type-display">{{data.typeLabel}}</span>)
                <a href="#" class="nfb-copy-field" title="<?php _e('کپی کردن فیلد', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-admin-page"></span></a>
                <a href="#" class="nfb-toggle-field-details"><?php _e('جزئیات', 'nilay-form-builder'); ?></a>
                <a href="#" class="nfb-quick-remove-field" title="<?php _e('حذف سریع', 'nilay-form-builder'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
            </div>
            <div class="nfb-field-details" style="display:none;">
                <!-- Field settings content will be injected here by JS -->
            </div>
        </div>
    </script>
    
    <!-- Settings Tabs Template -->
    <script type="text/html" id="tmpl-nfb-settings-tabs">
        <div class="nfb-settings-tabs">
            <a href="#" class="nfb-tab-link active" data-tab="base"><?php _e('پایه', 'nilay-form-builder'); ?></a>
            <a href="#" class="nfb-tab-link" data-tab="display"><?php _e('نمایش', 'nilay-form-builder'); ?></a>
            <a href="#" class="nfb-tab-link" data-tab="validation"><?php _e('اعتبارسنجی', 'nilay-form-builder'); ?></a>
            <a href="#" class="nfb-tab-link" data-tab="conditional"><?php _e('منطق شرطی', 'nilay-form-builder'); ?></a>
        </div>
        <div class="nfb-settings-panels">
            <!-- Panels will be injected here -->
        </div>
        <a href="#" class="button nfb-remove-field"><?php _e( 'حذف فیلد', 'nilay-form-builder' ); ?></a>
    </script>

    <!-- Base Settings Panel -->
    <script type="text/html" id="tmpl-nfb-panel-base">
        <div class="nfb-settings-panel active" data-panel="base">
            <p><label><?php _e('عنوان فیلد:', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input field-label-input" data-setting="label" value="{{data.label}}"></p>
            <p><label><?php _e('کلید متا (انگلیسی):', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input field-key-input" data-setting="meta_key" value="{{data.meta_key}}"></p>
            <div class="nfb-options-wrapper" style="display:none;"><label><?php _e('گزینه‌ها (هر گزینه در یک خط):', 'nilay-form-builder'); ?></label><textarea class="nfb-setting-input" data-setting="options" rows="4" placeholder="<?php _e('برچسب|مقدار', 'nilay-form-builder'); ?>">{{data.options}}</textarea></div>
            <div class="nfb-html-content-wrapper" style="display:none;"><label><?php _e('محتوای HTML:', 'nilay-form-builder'); ?></label><textarea class="nfb-setting-input" data-setting="html_content" rows="6">{{data.html_content}}</textarea></div>
        </div>
    </script>
    
    <!-- Display Settings Panel -->
    <script type="text/html" id="tmpl-nfb-panel-display">
        <div class="nfb-settings-panel" data-panel="display">
            <p class="nfb-placeholder-wrapper" style="display:none;"><label><?php _e('متن راهنما (Placeholder):', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input" data-setting="placeholder" value="{{data.placeholder}}"></p>
            <p><label><?php _e('متن کمکی:', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input" data-setting="help_text" value="{{data.help_text}}"></p>
            <p><label><?php _e('مقدار پیش‌فرض:', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input" data-setting="default_value" value="{{data.default_value}}"></p>
            <p><label><?php _e('عرض فیلد:', 'nilay-form-builder'); ?>
                <select class="nfb-setting-input" data-setting="width_class">
                    <option value="full" <# if(data.width_class === 'full') { #>selected<# } #>><?php _e('عرض کامل', 'nilay-form-builder'); ?></option>
                    <option value="half" <# if(data.width_class === 'half') { #>selected<# } #>><?php _e('یک دوم', 'nilay-form-builder'); ?></option>
                    <option value="third" <# if(data.width_class === 'third') { #>selected<# } #>><?php _e('یک سوم', 'nilay-form-builder'); ?></option>
                    <option value="quarter" <# if(data.width_class === 'quarter') { #>selected<# } #>><?php _e('یک چهارم', 'nilay-form-builder'); ?></option>
                </select>
            </label></p>
        </div>
    </script>

    <!-- Validation Settings Panel -->
    <script type="text/html" id="tmpl-nfb-panel-validation">
        <div class="nfb-settings-panel" data-panel="validation">
            <p><label><input type="checkbox" class="nfb-setting-input" data-setting="required" value="1" <# if(data.required){ #>checked<# } #>> <?php _e('الزامی', 'nilay-form-builder'); ?></label></p>
            <p><label><input type="checkbox" class="nfb-setting-input" data-setting="unique" value="1" <# if(data.unique){ #>checked<# } #>> <?php _e('مقدار یکتا', 'nilay-form-builder'); ?></label></p>
        </div>
    </script>
    
    <!-- Conditional Logic Settings Panel -->
    <script type="text/html" id="tmpl-nfb-panel-conditional">
        <div class="nfb-settings-panel" data-panel="conditional">
            <p><label><input type="checkbox" class="nfb-setting-input nfb-conditional-enable" data-setting="conditional_logic_enabled" value="1" <# if(data.conditional_logic_enabled){ #>checked<# } #>> <?php _e('فعال کردن منطق شرطی', 'nilay-form-builder'); ?></label></p>
            <div class="nfb-conditional-rules" style="<# if(!data.conditional_logic_enabled){ #>display:none;<# } #>">
                <select class="nfb-setting-input" data-setting="conditional_logic_action">
                    <option value="show" <# if(data.conditional_logic_action === 'show') { #>selected<# } #>><?php _e('نمایش بده', 'nilay-form-builder'); ?></option>
                    <option value="hide" <# if(data.conditional_logic_action === 'hide') { #>selected<# } #>><?php _e('پنهان کن', 'nilay-form-builder'); ?></option>
                </select>
                <?php _e('این فیلد را اگر', 'nilay-form-builder'); ?>
                <select class="nfb-setting-input nfb-conditional-target-field" data-setting="conditional_logic_target_field">
                    <option value=""><?php _e('یک فیلد انتخاب کنید', 'nilay-form-builder'); ?></option>
                </select>
                <input type="text" class="nfb-setting-input" data-setting="conditional_logic_value" value="{{data.conditional_logic_value}}" placeholder="<?php _e('مقدار', 'nilay-form-builder'); ?>">
            </div>
        </div>
    </script>
    
    <!-- Field-Specific Settings Panels -->
    <script type="text/html" id="tmpl-nfb-panel-file-settings">
        <hr><h4><?php _e('تنظیمات آپلود', 'nilay-form-builder'); ?></h4>
        <p><label><?php _e('فرمت‌های مجاز (جدا شده با کاما):', 'nilay-form-builder'); ?></label><input type="text" class="nfb-setting-input" data-setting="file_formats" value="{{data.file_formats}}" placeholder="jpg,png,pdf"></p>
        <p><label><?php _e('حداکثر حجم (به کیلوبایت):', 'nilay-form-builder'); ?></label><input type="number" class="nfb-setting-input" data-setting="max_file_size" value="{{data.max_file_size}}"></p>
    </script>

    <script type="text/html" id="tmpl-nfb-panel-product-settings">
        <hr><h4><?php _e('تنظیمات محصول', 'nilay-form-builder'); ?></h4>
        <p><label><?php _e('حالت قیمت‌گذاری:', 'nilay-form-builder'); ?>
            <select class="nfb-setting-input" data-setting="pricing_mode">
                <option value="fixed" <# if(data.pricing_mode === 'fixed') { #>selected<# } #>><?php _e('قیمت ثابت', 'nilay-form-builder'); ?></option>
                <option value="user_defined" <# if(data.pricing_mode === 'user_defined') { #>selected<# } #>><?php _e('قیمت توسط کاربر', 'nilay-form-builder'); ?></option>
            </select>
        </label></p>
        <p><label><?php _e('قیمت ثابت (تومان):', 'nilay-form-builder'); ?></label><input type="number" class="nfb-setting-input" data-setting="fixed_price" value="{{data.fixed_price}}"></p>
        <p><label><input type="checkbox" class="nfb-setting-input" data-setting="enable_quantity" value="1" <# if(data.enable_quantity){ #>checked<# } #>> <?php _e('فعال‌سازی انتخاب تعداد', 'nilay-form-builder'); ?></label></p>
    </script>

    <!-- Repeater Sub-fields Wrapper -->
    <script type="text/html" id="tmpl-nfb-repeater-wrapper">
         <div class="nfb-repeater-wrapper">
            <h4><?php _e('فیلدهای داخلی تکرارشونده', 'nilay-form-builder'); ?></h4>
            <div class="nfb-sortable-list nfb-repeater-sub-fields"></div>
            <a href="#" class="button nfb-add-sub-field"><?php _e('افزودن فیلد داخلی', 'nilay-form-builder'); ?></a>
         </div>
    </script>

    <!-- Modal for adding sub-field to repeater -->
    <div id="nfb-repeater-field-modal" style="display:none;" title="<?php _e('یک فیلد برای افزودن انتخاب کنید', 'nilay-form-builder'); ?>">
        <div id="nfb-modal-field-types-container">
            <?php foreach ( $field_groups as $group_key => $group ) : ?>
                <?php if ($group_key === 'structural') continue; // Prevent adding structural fields inside repeater ?>
                <div class="nfb-field-group-wrapper">
                    <h4><?php echo esc_html( $group['label'] ); ?></h4>
                    <ul class="nfb-modal-field-types">
                        <?php foreach ( $group['fields'] as $type => $field ) : ?>
                            <?php if (in_array($type, ['repeater', 'page_break'])) continue; // Prevent nesting these fields ?>
                            <li><a href="#" class="nfb-modal-add-field" data-field-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $field['label'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

