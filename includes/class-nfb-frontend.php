<?php
/**
 * NFB_Frontend Class
 * Handles the display of forms on the frontend.
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

class NFB_Frontend {

	/**
	 * The single instance of the class.
	 * @var NFB_Frontend
	 */
	private static $_instance = null;

	/**
	 * Main NFB_Frontend Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'nilay-form', [ $this, 'render_form_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts() {
        // Scripts and styles are now enqueued conditionally inside the shortcode render function.
	}


	/**
	 * Render the form using a shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string The form HTML.
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'nilay-form' );
		$form_id = intval( $atts['id'] );

		if ( ! $form_id || get_post_type( $form_id ) !== 'nfb_form' ) {
			return sprintf( '<p>%s</p>', __( 'فرم مورد نظر یافت نشد.', 'nilay-form-builder' ) );
		}

        // Check submission limits before rendering the form
        $limit_message = NFB_Services::instance()->check_submission_limits($form_id);
        if ($limit_message !== true) {
            return '<div class="nfb-form-notice nfb-form-error">' . esc_html($limit_message) . '</div>';
        }

        // Enqueue scripts and styles here, only when form is rendered
        wp_enqueue_style('nfb-frontend-style', NFB_PLUGIN_URL . 'assets/css/frontend.css', [], NFB_PLUGIN_VERSION);
        wp_enqueue_script('nfb-signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', [], '4.0.0', true);
        wp_enqueue_script('nfb-frontend-script', NFB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'nfb-signature-pad'], NFB_PLUGIN_VERSION, true);
        wp_localize_script('nfb-frontend-script', 'nfb_frontend_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nfb_form_nonce')
        ]);

		$fields_json = get_post_meta( $form_id, '_nfb_fields', true );
		$fields      = json_decode( $fields_json, true );

		if ( empty( $fields ) ) {
			return sprintf( '<p>%s</p>', __( 'این فرم هیچ فیلدی ندارد.', 'nilay-form-builder' ) );
		}

		ob_start();
		?>
        <div class="nfb-form-container">
            <form id="nfb-form-<?php echo esc_attr( $form_id ); ?>" class="nfb-form" data-form-id="<?php echo esc_attr( $form_id ); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <div class="nfb-form-steps">
                    <?php
                    $step = 1;
                    echo '<div class="nfb-form-step active" data-step="' . $step . '">';

                    foreach ($fields as $field) {
                        if ($field['type'] === 'page_break') {
                            $step++;
                            echo '</div>'; // Close previous step
                            echo '<div class="nfb-form-step" data-step="' . $step . '">';
                            continue;
                        }
                        $this->render_field($field);
                    }
                    echo '</div>'; // Close the last step
                    ?>
                </div>

                <div class="nfb-form-navigation">
                    <button type="button" class="nfb-prev-btn" style="display: none;"><?php _e('قبلی', 'nilay-form-builder'); ?></button>
                    <button type="button" class="nfb-next-btn"><?php _e('بعدی', 'nilay-form-builder'); ?></button>
                    <button type="submit" class="nfb-submit-btn" style="display: none;"><?php echo esc_html(NFB_Fields::get_field_config('submit_button')['default_label']); ?></button>
                </div>
                
                <div class="nfb-form-response"></div>
            </form>
        </div>
		<?php
		return ob_get_clean();
	}
    
    /**
     * Render a single form field.
     *
     * @param array $field The field configuration.
     */
    private function render_field($field) {
        $meta_key = esc_attr($field['meta_key']);
        $label = esc_html($field['label']);
        $required = !empty($field['required']) ? 'required' : '';
        $placeholder = isset($field['placeholder']) ? esc_attr($field['placeholder']) : '';
        $options = isset($field['options']) ? $field['options'] : [];
        ?>
        <div class="nfb-form-field nfb-field-type-<?php echo esc_attr($field['type']); ?>">
            <label for="nfb-field-<?php echo $meta_key; ?>"><?php echo $label; ?> <?php if ($required): ?><span class="nfb-required">*</span><?php endif; ?></label>
            <?php switch ($field['type']):
                case 'text':
                case 'email':
                case 'number':
                case 'url': ?>
                    <input type="<?php echo esc_attr($field['type']); ?>" id="nfb-field-<?php echo $meta_key; ?>" name="<?php echo $meta_key; ?>" <?php echo $required; ?> placeholder="<?php echo $placeholder; ?>">
                    <?php break;
                case 'textarea': ?>
                    <textarea id="nfb-field-<?php echo $meta_key; ?>" name="<?php echo $meta_key; ?>" <?php echo $required; ?> placeholder="<?php echo $placeholder; ?>"></textarea>
                    <?php break;
                case 'select': ?>
                    <select id="nfb-field-<?php echo $meta_key; ?>" name="<?php echo $meta_key; ?>" <?php echo $required; ?>>
                        <?php foreach ($options as $option): ?>
                            <option value="<?php echo esc_attr($option['value']); ?>"><?php echo esc_html($option['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php break;
                case 'radio': ?>
                    <div class="nfb-radio-group">
                        <?php foreach ($options as $index => $option): ?>
                            <label><input type="radio" name="<?php echo $meta_key; ?>" value="<?php echo esc_attr($option['value']); ?>" <?php echo $required; ?>> <?php echo esc_html($option['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?php break;
                 case 'checkbox': ?>
                    <div class="nfb-checkbox-group">
                        <?php foreach ($options as $index => $option): ?>
                            <label><input type="checkbox" name="<?php echo $meta_key; ?>[]" value="<?php echo esc_attr($option['value']); ?>"> <?php echo esc_html($option['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?php break;
                case 'signature': ?>
                     <div class="nfb-signature-pad-wrapper">
                        <canvas id="nfb-field-<?php echo $meta_key; ?>-canvas" class="nfb-signature-canvas"></canvas>
                        <button type="button" class="nfb-clear-signature" data-target="<?php echo $meta_key; ?>"><?php _e('پاک کردن امضا', 'nilay-form-builder'); ?></button>
                        <input type="hidden" id="nfb-field-<?php echo $meta_key; ?>" name="<?php echo $meta_key; ?>" <?php echo $required; ?>>
                    </div>
                    <?php break;
            endswitch; ?>
        </div>
        <?php
    }
}

