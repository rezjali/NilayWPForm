<?php
/**
 * View for the Form Builder metabox.
 * این فایل شامل ساختار HTML فرم ساز پیشرفته است.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes/Views
 * @author     Reza Jalali
 * @since      1.0.0
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

// دریافت داده های ذخیره شده فیلدها (در صورت وجود)
$form_fields_json = get_post_meta( $post->ID, '_nfb_form_fields_json', true );
if ( empty( $form_fields_json ) ) {
	$form_fields_json = '[]';
}
?>

<!-- فیلد مخفی برای ذخیره سازی ساختار فرم به صورت JSON -->
<input type="hidden" name="nfb_form_fields" id="nfb_form_fields_json" value="<?php echo esc_attr( $form_fields_json ); ?>">

<div id="nfb-form-builder-wrapper">

    <!-- ستون سمت راست: انواع فیلدهای قابل افزودن -->
    <div id="nfb-field-types-container">
        <h3><?php _e( 'فیلدهای استاندارد', 'nilay-form-builder' ); ?></h3>
        <ul id="nfb-field-types">
			<?php
			$all_fields = NFB_Fields::get_all_fields();
			foreach ( $all_fields as $type => $field ) {
				?>
                <li>
                    <button type="button" class="button nfb-add-field-btn" data-field-type="<?php echo esc_attr( $type ); ?>">
                        <span class="dashicons <?php echo esc_attr( $field['icon'] ); ?>"></span>
						<?php echo esc_html( $field['label'] ); ?>
                    </button>
                </li>
				<?php
			}
			?>
        </ul>
    </div>

    <!-- پنل اصلی فرم ساز: جایی که فیلدها کشیده و رها می شوند -->
    <div id="nfb-form-builder-main">
        <div id="nfb-form-fields-list" class="empty">
            <!-- فیلدهای فرم در اینجا با جاوااسکریپت اضافه می شوند -->
            <p class="nfb-empty-message"><?php _e( 'برای شروع، یک فیلد را از ستون کناری به اینجا بکشید یا روی آن کلیک کنید.', 'nilay-form-builder' ); ?></p>
        </div>
    </div>
</div>

<!-- TEMPLATES: الگوهای HTML که توسط جاوااسکریپت برای ساخت فیلدها و تنظیماتشان استفاده می شوند -->
<div id="nfb-js-templates" style="display: none;">

    <!-- الگوی کلی یک فیلد در فرم ساز -->
    <script type="text/html" id="tmpl-nfb-field-template">
        <div class="nfb-field-item" data-field-id="{{data.id}}" data-field-type="{{data.type}}">
            <div class="nfb-field-header">
                <span class="nfb-field-icon dashicons {{data.icon}}"></span>
                <span class="nfb-field-label">{{data.label}}</span>
                <span class="nfb-field-type-badge">{{data.typeLabel}}</span>
                <div class="nfb-field-actions">
                    <button type="button" class="nfb-duplicate-field" title="<?php _e( 'کپی کردن', 'nilay-form-builder' ); ?>"><span class="dashicons dashicons-admin-page"></span></button>
                    <button type="button" class="nfb-delete-field" title="<?php _e( 'حذف کردن', 'nilay-form-builder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                    <button type="button" class="nfb-toggle-settings" title="<?php _e( 'تنظیمات', 'nilay-form-builder' ); ?>"><span class="dashicons dashicons-arrow-down"></span></button>
                </div>
            </div>
            <div class="nfb-field-settings">
                <!-- تنظیمات فیلد در اینجا بارگذاری می شود -->
            </div>
        </div>
    </script>

    <!-- الگوی تنظیمات پایه فیلدها -->
    <script type="text/html" id="tmpl-nfb-settings-common">
        <div class="nfb-setting-row">
            <label for="field-{{data.id}}-label"><?php _e( 'برچسب', 'nilay-form-builder' ); ?></label>
            <input type="text" id="field-{{data.id}}-label" class="nfb-setting-input" data-setting="label" value="{{data.label}}">
        </div>
        <div class="nfb-setting-row">
            <label for="field-{{data.id}}-name"><?php _e( 'نام (متا کی)', 'nilay-form-builder' ); ?></label>
            <input type="text" id="field-{{data.id}}-name" class="nfb-setting-input" data-setting="name" value="{{data.name}}">
        </div>
    </script>

    <!-- الگوی تنظیمات برای فیلدهای دارای Placeholder -->
    <script type="text/html" id="tmpl-nfb-settings-placeholder">
        <div class="nfb-setting-row">
            <label for="field-{{data.id}}-placeholder"><?php _e( 'متن راهنما (Placeholder)', 'nilay-form-builder' ); ?></label>
            <input type="text" id="field-{{data.id}}-placeholder" class="nfb-setting-input" data-setting="placeholder" value="{{data.placeholder}}">
        </div>
    </script>

    <!-- الگوی تنظیمات برای فیلدهای دارای گزینه‌ها (Select, Radio, Checkbox) -->
    <script type="text/html" id="tmpl-nfb-settings-options">
        <div class="nfb-setting-row">
            <label><?php _e( 'گزینه ها', 'nilay-form-builder' ); ?></label>
            <textarea class="nfb-setting-input" data-setting="options" rows="4" placeholder="<?php _e( 'هر گزینه در یک خط جداگانه', 'nilay-form-builder' ); ?>">{{data.options}}</textarea>
        </div>
    </script>
    
    <!-- الگوی تنظیمات پایانی (الزامی بودن و کلاس CSS) -->
     <script type="text/html" id="tmpl-nfb-settings-common-end">
        <div class="nfb-setting-row">
            <label for="field-{{data.id}}-class"><?php _e( 'کلاس CSS', 'nilay-form-builder' ); ?></label>
            <input type="text" id="field-{{data.id}}-class" class="nfb-setting-input" data-setting="class" value="{{data.class}}">
        </div>
        <div class="nfb-setting-row">
            <label>
                <input type="checkbox" class="nfb-setting-input" data-setting="required" value="1" {{ data.required ? 'checked' : '' }}>
                <?php _e( 'این فیلد الزامی است', 'nilay-form-builder' ); ?>
            </label>
        </div>
    </script>

</div>
