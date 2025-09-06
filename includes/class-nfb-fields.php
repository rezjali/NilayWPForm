<?php
/**
 * NFB_Fields Class
 *
 * این کلاس به عنوان یک رجیستری مرکزی برای تعریف انواع فیلدهای موجود در فرم ساز عمل می کند.
 * این نسخه به طور کامل بازنویسی شده تا شامل تمام فیلدهای پیشرفته و تنظیمات آنها شود.
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes
 * @author     Reza Jalali
 * @since      2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NFB_Fields {

	public static function get_all_fields() {
		return [
            'base_fields' => [
                'label' => __('فیلدهای پایه', 'nilay-form-builder'),
                'fields' => [
                    'text' => ['label' => __('متن', 'nilay-form-builder'), 'icon' => 'dashicons-editor-textcolor'],
                    'textarea' => ['label' => __('متن بلند', 'nilay-form-builder'), 'icon' => 'dashicons-editor-alignleft'],
                    'number' => ['label' => __('عدد', 'nilay-form-builder'), 'icon' => 'dashicons-editor-ol'],
                    'email' => ['label' => __('ایمیل', 'nilay-form-builder'), 'icon' => 'dashicons-email-alt'],
                    'url' => ['label' => __('وب‌سایت', 'nilay-form-builder'), 'icon' => 'dashicons-admin-links'],
                    'time' => ['label' => __('ساعت', 'nilay-form-builder'), 'icon' => 'dashicons-clock'],
                    'date' => ['label' => __('تاریخ', 'nilay-form-builder'), 'icon' => 'dashicons-calendar-alt'],
                ]
            ],
            'validation_fields' => [
                'label' => __('فیلدهای اعتبارسنجی', 'nilay-form-builder'),
                'fields' => [
                    'mobile' => ['label' => __('شماره موبایل', 'nilay-form-builder'), 'icon' => 'dashicons-smartphone'],
                    'phone' => ['label' => __('تلفن ثابت', 'nilay-form-builder'), 'icon' => 'dashicons-phone'],
                    'postal_code' => ['label' => __('کد پستی', 'nilay-form-builder'), 'icon' => 'dashicons-location'],
                    'national_id' => ['label' => __('کد ملی', 'nilay-form-builder'), 'icon' => 'dashicons-id'],
                ]
            ],
            'choice_fields' => [
                'label' => __('فیلدهای انتخاب', 'nilay-form-builder'),
                'fields' => [
                    'select' => ['label' => __('لیست کشویی', 'nilay-form-builder'), 'icon' => 'dashicons-menu'],
                    'multiselect' => ['label' => __('چند انتخابی', 'nilay-form-builder'), 'icon' => 'dashicons-menu-alt3'],
                    'checkbox' => ['label' => __('چک‌باکس', 'nilay-form-builder'), 'icon' => 'dashicons-yes-alt'],
                    'radio' => ['label' => __('دکمه رادیویی', 'nilay-form-builder'), 'icon' => 'dashicons-forms'],
                ]
            ],
            'structure_fields' => [
                'label' => __('فیلدهای ساختاری', 'nilay-form-builder'),
                'fields' => [
                    'section_title' => ['label' => __('عنوان بخش (Heading)', 'nilay-form-builder'), 'icon' => 'dashicons-editor-bold'],
                    'html_content' => ['label' => __('محتوای HTML', 'nilay-form-builder'), 'icon' => 'dashicons-editor-code'],
                    'page_break' => ['label' => __('صفحه‌بندی / گام بعدی', 'nilay-form-builder'), 'icon' => 'dashicons-files-alt'],
                ]
            ],
            'upload_fields' => [
                'label' => __('فیلدهای آپلود', 'nilay-form-builder'),
                'fields' => [
                    'image' => ['label' => __('تصویر', 'nilay-form-builder'), 'icon' => 'dashicons-format-image'],
                    'file' => ['label' => __('فایل', 'nilay-form-builder'), 'icon' => 'dashicons-media-default'],
                    'gallery' => ['label' => __('گالری تصاویر', 'nilay-form-builder'), 'icon' => 'dashicons-format-gallery'],
                ]
            ],
            'advanced_fields' => [
                'label' => __('فیلدهای پیشرفته', 'nilay-form-builder'),
                'fields' => [
                    'map' => ['label' => __('نقشه', 'nilay-form-builder'), 'icon' => 'dashicons-location-alt'],
                    'repeater' => ['label' => __('تکرار شونده', 'nilay-form-builder'), 'icon' => 'dashicons-controls-repeat'],
                    'social_networks' => ['label' => __('شبکه‌های اجتماعی', 'nilay-form-builder'), 'icon' => 'dashicons-share'],
                    'simple_list' => ['label' => __('فیلد لیستی', 'nilay-form-builder'), 'icon' => 'dashicons-editor-ul'],
                    'product' => ['label' => __('محصول/خدمت', 'nilay-form-builder'), 'icon' => 'dashicons-cart'],
                    'signature'  => ['label' => __( 'امضای دیجیتال', 'nilay-form-builder' ), 'icon' => 'dashicons-edit-page'],
                ]
            ],
            'compound_fields' => [
                'label' => __('فیلدهای ترکیبی', 'nilay-form-builder'),
                'fields' => [
                    'address' => ['label' => __('آدرس پستی', 'nilay-form-builder'), 'icon' => 'dashicons-admin-home'],
                    'identity' => ['label' => __('اطلاعات هویتی', 'nilay-form-builder'), 'icon' => 'dashicons-admin-users'],
                ]
            ]
		];
	}
}

