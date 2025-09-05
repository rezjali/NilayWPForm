<?php
/**
 * NFB_Fields Class
 *
 * این کلاس به عنوان یک رجیستری مرکزی برای تعریف انواع فیلدهای موجود در فرم ساز عمل می کند.
 * با افزودن اطلاعات به این کلاس، می توان به راحتی فیلدهای جدیدی به سیستم اضافه کرد.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      1.0.0
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NFB_Fields {

	/**
	 * آرایه ای از تمام انواع فیلدهای قابل استفاده در فرم ساز
	 *
	 * @return array
	 */
	public static function get_all_fields() {
		return [
			'text'       => [
				'label'   => __( 'متن تک خطی', 'nilay-form-builder' ),
				'icon'    => 'dashicons-editor-textcolor',
				'settings' => [ 'label', 'name', 'placeholder', 'required', 'class' ],
			],
			'textarea'   => [
				'label'   => __( 'متن چند خطی', 'nilay-form-builder' ),
				'icon'    => 'dashicons-editor-alignleft',
				'settings' => [ 'label', 'name', 'placeholder', 'required', 'class' ],
			],
			'email'      => [
				'label'   => __( 'ایمیل', 'nilay-form-builder' ),
				'icon'    => 'dashicons-email-alt',
				'settings' => [ 'label', 'name', 'placeholder', 'required', 'class' ],
			],
			'number'     => [
				'label'   => __( 'عدد', 'nilay-form-builder' ),
				'icon'    => 'dashicons-editor-ol',
				'settings' => [ 'label', 'name', 'placeholder', 'required', 'class' ],
			],
			'select'     => [
				'label'   => __( 'لیست کشویی', 'nilay-form-builder' ),
				'icon'    => 'dashicons-menu',
				'settings' => [ 'label', 'name', 'options', 'required', 'class' ],
			],
			'radio'      => [
				'label'   => __( 'گزینه‌های رادیویی', 'nilay-form-builder' ),
				'icon'    => 'dashicons-forms',
				'settings' => [ 'label', 'name', 'options', 'required', 'class' ],
			],
			'checkbox'   => [
				'label'   => __( 'چک باکس', 'nilay-form-builder' ),
				'icon'    => 'dashicons-yes',
				'settings' => [ 'label', 'name', 'options', 'required', 'class' ],
			],
            'page_break' => [
                'label'   => __( 'صفحه‌بندی / گام بعدی', 'nilay-form-builder' ),
                'icon'    => 'dashicons-files-alt',
                'settings' => [],
            ],
            // -- فیلد جدید --
            'signature'  => [
                'label'   => __( 'امضای دیجیتال', 'nilay-form-builder' ),
                'icon'    => 'dashicons-edit',
                'settings' => [ 'label', 'name', 'required', 'class' ],
            ],
		];
	}

	/**
	 * دریافت اطلاعات یک فیلد خاص بر اساس نوع آن
	 *
	 * @param string $type نوع فیلد
	 * @return array|null
	 */
	public static function get_field( $type ) {
		$fields = self::get_all_fields();
		return isset( $fields[ $type ] ) ? $fields[ $type ] : null;
	}
}


