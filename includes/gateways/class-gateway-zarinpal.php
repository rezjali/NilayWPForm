<?php
/**
 * Zarinpal Gateway Class
 *
 * @package    Nilay_Form_Builder
 * @subpackage Includes/Gateways
 * @author     Reza Jalali
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NFB_Gateway_Zarinpal {

	public $id = 'zarinpal';
	public $title = 'زرین‌پال';

	/**
	 * هدایت کاربر به صفحه پرداخت
	 *
	 * @param int $entry_id
	 * @param float $amount
	 * @param int $form_id
	 */
	public function send_to_gateway( $entry_id, $amount, $form_id ) {
		// اطلاعات مورد نیاز برای زرین پال
		$merchant_id = 'YOUR-MERCHANT-ID'; // این باید از تنظیمات خوانده شود
		$callback_url = add_query_arg( [
			'nfb_action' => 'verify_payment',
			'gateway'    => $this->id,
			'entry_id'   => $entry_id
		], home_url( '/' ) );
		$description = sprintf( __( 'پرداخت برای فرم %s', 'nilay-form-builder' ), get_the_title( $form_id ) );

		// شبیه سازی درخواست به زرین پال
		// در پروژه واقعی، اینجا باید با استفاده از SoapClient یا cURL به وب سرویس زرین پال متصل شوید
		$fake_authority = 'ZARINPAL-FAKE-AUTHORITY-' . time();

		// ذخیره کد تراکنش موقت
		update_post_meta( $entry_id, '_nfb_payment_authority', $fake_authority );

		// هدایت کاربر به صفحه پرداخت
		// $zarinpal_url = 'https://www.zarinpal.com/pg/StartPay/' . $fake_authority;
		// wp_redirect( $zarinpal_url );

		// برای تست، به صفحه بازگشت شبیه سازی شده ریدایرکت می کنیم
		$fake_callback = add_query_arg( [
			'Authority' => $fake_authority,
			'Status'    => 'OK'
		], $callback_url );
		
		echo '<p style="text-align: center; font-family: sans-serif; margin-top: 50px;">' . __( 'در حال انتقال به درگاه پرداخت...', 'nilay-form-builder' ) . '</p>';
		echo "<script>window.location.href = '" . $fake_callback . "';</script>";

		exit;
	}

	/**
	 * بررسی و تایید پرداخت پس از بازگشت از درگاه
	 *
	 * @param int $entry_id
	 * @param float $amount
	 * @return bool
	 */
	public function verify_payment( $entry_id, $amount ) {
		if ( ! isset( $_GET['Authority'] ) || ! isset( $_GET['Status'] ) ) {
			return false;
		}

		$authority = sanitize_text_field( $_GET['Authority'] );
		$status    = sanitize_text_field( $_GET['Status'] );
		$saved_authority = get_post_meta( $entry_id, '_nfb_payment_authority', true );
		
		if ( $authority !== $saved_authority || $status !== 'OK' ) {
			return false;
		}

		// شبیه سازی تایید تراکنش با زرین پال
		// در پروژه واقعی، اینجا باید با متد Verify به وب سرویس زرین پال متصل شوید
		$ref_id = time(); // یک کد رهگیری جعلی

		// اگر تایید موفق بود
		update_post_meta( $entry_id, '_nfb_payment_status', 'completed' );
		update_post_meta( $entry_id, '_nfb_payment_ref_id', $ref_id );
		update_post_meta( $entry_id, '_nfb_payment_gateway', $this->title );
		update_post_meta( $entry_id, '_nfb_payment_amount', $amount );
		
		// تغییر وضعیت ورودی از "در انتظار پرداخت" به "منتشر شده"
		wp_update_post( [ 'ID' => $entry_id, 'post_status' => 'publish' ] );

		return true;
	}
}

