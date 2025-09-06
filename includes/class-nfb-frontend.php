<?php
/**
 * NFB_Frontend Class
 * Handles the display of forms on the frontend and enqueues necessary scripts.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NFB_Frontend {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		add_shortcode( 'nilay-form', [ $this, 'render_form_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function enqueue_scripts() {
        wp_register_style('nfb-frontend-style', NFB_PLUGIN_URL . 'assets/css/frontend.css', [], NFB_PLUGIN_VERSION);
        wp_register_script('nfb-frontend-script', NFB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], NFB_PLUGIN_VERSION, true);
        wp_localize_script('nfb-frontend-script', 'nfb_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nfb_form_nonce')
        ]);
        
        // Leaflet for Map Field
        wp_register_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', [], '1.7.1');
        wp_register_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], '1.7.1', true);
        
        // Signature Pad
        wp_register_script('nfb-signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', [], '4.0.0', true);
	}

	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'nilay-form' );
		$form_id = intval( $atts['id'] );

		if ( ! $form_id || get_post_type( $form_id ) !== 'nfb_form' ) return '<p>' . __( 'فرم مورد نظر یافت نشد.', 'nilay-form-builder' ) . '</p>';

        $limit_message = NFB_Services::instance()->check_submission_limits($form_id);
        if ($limit_message !== true) {
            return '<div class="nfb-response-message error" style="display:block;">' . esc_html($limit_message) . '</div>';
        }

        wp_enqueue_style('nfb-frontend-style');
        wp_enqueue_script('nfb-frontend-script');

		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields      = json_decode( $fields_json, true );

		if ( empty( $fields ) ) return '<p>' . __( 'این فرم هیچ فیلدی ندارد.', 'nilay-form-builder' ) . '</p>';
        
        $has_map = $has_signature = $has_date = $has_upload = false;
        array_walk_recursive($fields, function($val, $key) use (&$has_map, &$has_signature, &$has_date, &$has_upload) {
            if ($key === 'type') {
                if ($val === 'map') $has_map = true;
                if ($val === 'signature') $has_signature = true;
                if ($val === 'date') $has_date = true;
                if (in_array($val, ['image', 'file', 'gallery'])) $has_upload = true;
            }
        });

        if ($has_map) { wp_enqueue_style('leaflet-css'); wp_enqueue_script('leaflet-js'); }
        if ($has_signature) { wp_enqueue_script('nfb-signature-pad'); }
        if ($has_date) { wp_enqueue_script('jquery-ui-datepicker'); wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css'); }
        if ($has_upload) { wp_enqueue_media(); }
        
        $settings = get_post_meta($form_id, '_nfb_settings', true);
        $submit_button_text = !empty($settings['submit_button_text']) ? esc_html($settings['submit_button_text']) : __('ارسال', 'nilay-form-builder');

		ob_start();
		?>
        <div class="nfb-form-wrapper nfb-multi-step-form">
            <div class="nfb-step-indicator"></div>
            <form id="nfb-form-<?php echo esc_attr( $form_id ); ?>" class="nfb-form" data-form-id="<?php echo esc_attr( $form_id ); ?>" novalidate>
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
                
                <div class="nfb-pages">
                    <div class="nfb-page active">
                    <?php
                    foreach ($fields as $field) {
                        if ($field['type'] === 'page_break') {
                            echo '</div><div class="nfb-page">';
                            continue;
                        }
                        $this->render_field_recursive($field);
                    }
                    ?>
                    </div>
                </div>

                <div class="nfb-response-message"></div>

                <div class="nfb-nav-buttons">
                    <button type="button" class="nfb-prev-btn" style="display: none;"><?php _e('قبلی', 'nilay-form-builder'); ?></button>
                    <button type="button" class="nfb-next-btn"><?php _e('بعدی', 'nilay-form-builder'); ?></button>
                    <button type="submit" class="nfb-submit-btn"><?php echo $submit_button_text; ?></button>
                </div>
            </form>
        </div>
		<?php
		return ob_get_clean();
	}
    
    private function render_field_recursive($field, $name_prefix = '', $is_repeater_row = false) {
        $defaults = ['meta_key' => '', 'label' => '', 'type' => 'text', 'required' => false, 'placeholder' => '', 'options' => '', 'width_class' => 'full', 'help_text' => ''];
        $field = wp_parse_args($field, $defaults);

        if ($is_repeater_row) {
            $field_name = $name_prefix . '[' . $field['meta_key'] . ']';
        } else {
            $field_name = $name_prefix ? $name_prefix . '[__INDEX__][' . $field['meta_key'] . ']' : $field['meta_key'];
        }
        
        $field_id = 'nfb-field-' . uniqid();
        
        $wrapper_classes = "nfb-field-group nfb-field-type-{$field['type']} nfb-field-width-{$field['width_class']}";
        $wrapper_attributes = '';
        if (!empty($field['conditional_logic_enabled'])) {
            $wrapper_attributes = sprintf(
                ' data-conditional-logic="true" data-conditional-action="%s" data-conditional-target="%s" data-conditional-value="%s"',
                esc_attr($field['conditional_logic_action']),
                esc_attr($field['conditional_logic_target_field']),
                esc_attr($field['conditional_logic_value'])
            );
        }
        
        if (in_array($field['type'], ['section_title', 'html_content'])) {
            echo "<div class='{$wrapper_classes}' {$wrapper_attributes}>";
            if($field['type'] === 'section_title') echo "<h3>" . esc_html($field['label']) . "</h3>";
            if($field['type'] === 'html_content') echo wp_kses_post($field['html_content']);
            echo "</div>";
            return;
        }

        echo "<div class='{$wrapper_classes}' {$wrapper_attributes}>";
        echo "<label for='{$field_id}'>{$field['label']}" . ($field['required'] ? ' <span class="nfb-required">*</span>' : '') . "</label>";

        $required_attr = $field['required'] ? 'required' : '';
        $placeholder_attr = 'placeholder="' . esc_attr($field['placeholder']) . '"';

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
            case 'mobile':
            case 'phone':
            case 'postal_code':
            case 'national_id':
                $type = $field['type'] === 'mobile' || $field['type'] === 'phone' || $field['type'] === 'postal_code' || $field['type'] === 'national_id' ? 'text' : $field['type'];
                echo "<input type='{$type}' id='{$field_id}' name='{$field_name}' {$required_attr} {$placeholder_attr}>";
                break;
            case 'textarea':
                echo "<textarea id='{$field_id}' name='{$field_name}' {$required_attr} {$placeholder_attr}></textarea>";
                break;
            case 'select':
            case 'multiselect':
                $multiple = ($field['type'] === 'multiselect') ? 'multiple' : '';
                $name = $multiple ? $field_name . '[]' : $field_name;
                echo "<select id='{$field_id}' name='{$name}' {$multiple} {$required_attr}>";
                if (!$multiple) echo '<option value="">' . __('انتخاب کنید', 'nilay-form-builder') . '</option>';
                $options_arr = explode("\n", trim($field['options']));
                foreach ($options_arr as $option_line) {
                    $parts = explode('|', $option_line);
                    $label = trim($parts[0]);
                    $value = isset($parts[1]) ? trim($parts[1]) : $label;
                    echo "<option value='" . esc_attr($value) . "'>" . esc_html($label) . "</option>";
                }
                echo "</select>";
                break;
            case 'checkbox':
            case 'radio':
                $options_arr = explode("\n", trim($field['options']));
                foreach ($options_arr as $option_line) {
                    $parts = explode('|', $option_line);
                    $label = trim($parts[0]);
                    $value = isset($parts[1]) ? trim($parts[1]) : $label;
                    $name = ($field['type'] === 'checkbox') ? $field_name . '[]' : $field_name;
                    echo "<label class='nfb-choice-label'><input type='{$field['type']}' name='{$name}' value='" . esc_attr($value) . "' {$required_attr}> " . esc_html($label) . "</label>";
                }
                break;
            case 'date':
                echo "<input type='text' id='{$field_id}' name='{$field_name}' class='nfb-datepicker' {$required_attr} {$placeholder_attr} autocomplete='off'>";
                break;
            case 'time':
                echo "<input type='time' id='{$field_id}' name='{$field_name}' {$required_attr} {$placeholder_attr}>";
                break;
            case 'image':
            case 'file':
            case 'gallery':
                echo '<div class="nfb-gallery-field-wrapper">';
                echo '<div class="nfb-gallery-preview"></div>';
                $button_text = ($field['type'] === 'gallery') ? __('انتخاب تصاویر', 'nilay-form-builder') : __('انتخاب فایل', 'nilay-form-builder');
                $is_multiple = ($field['type'] === 'gallery');
                echo '<a href="#" class="button nfb-upload-button" data-multiple="' . esc_attr($is_multiple) . '">' . $button_text . '</a>';
                echo '<input type="hidden" id="'.esc_attr($field_id).'" name="'.esc_attr($field_name).'" ' . $required_attr . '>';
                echo '</div>';
                break;
            case 'map':
                echo '<div class="nfb-map-field-wrapper">';
                echo '<input type="text" id="'.esc_attr($field_id).'" name="'.esc_attr($field_name).'" readonly ' . $required_attr . '>';
                echo '<div class="nfb-map-preview" style="height: 250px; margin-top: 10px;"></div>';
                echo '</div>';
                break;
            case 'signature':
                echo '<div class="nfb-signature-wrapper">';
                echo '<canvas class="nfb-signature-pad"></canvas>';
                echo '<button type="button" class="nfb-signature-clear">' . __('پاک کردن', 'nilay-form-builder') . '</button>';
                echo '<input type="hidden" name="' . esc_attr($field_name) . '" ' . $required_attr . '>';
                echo '</div>';
                break;
            case 'repeater':
                echo '<div class="nfb-repeater-field-wrapper">';
                echo '<div class="nfb-repeater-rows-container">';
                // A single empty row is added by JS
                echo '</div>';
                echo '<div class="nfb-repeater-template" style="display:none;">';
                echo '<div class="nfb-repeater-row">';
                foreach($field['sub_fields'] as $sub_field) {
                    $this->render_field_recursive($sub_field, $field_name, false);
                }
                echo '<div class="nfb-repeater-row-actions"><a href="#" class="button nfb-repeater-remove-row-btn">' . __('حذف ردیف', 'nilay-form-builder') . '</a></div>';
                echo '</div>';
                echo '</div>';
                echo '<a href="#" class="button nfb-repeater-add-row-btn">' . __('افزودن ردیف جدید', 'nilay-form-builder') . '</a>';
                echo '</div>';
                break;
            // Add other advanced field types here...
        }

        if (!empty($field['help_text'])) {
            echo "<small class='nfb-help-text'>{$field['help_text']}</small>";
        }
        echo "</div>";
    }
}

