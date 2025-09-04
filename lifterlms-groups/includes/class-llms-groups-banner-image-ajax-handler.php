<?php
/**
 * LifterLMS Groups Banner image AJAX handler
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.2
 */

defined( 'ABSPATH' ) || exit;

class LLMS_Groups_Banner_Image_Ajax_Handler {

	public function __construct() {
		add_action( 'wp_ajax_llms_groups_upload_image', array( $this, 'upload_image' ) );
	}

	/**
	 * Upload the banner to the media library and associate with the group.
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function upload_image() {

		check_ajax_referer( 'llms_group_banner_image_upload', 'nonce' );

		$student = llms_get_student();
		if ( ! $student ) {
			return $this->send_response( __( 'Invalid user.', 'lifterlms-groups' ), false );
		}

		$img_type = $_REQUEST['img_type'];
		if ( ! in_array( $img_type, array( 'banner', 'logo' ) ) ) {
			return $this->send_response( __( 'Invalid image type.', 'lifterlms-groups' ), false );
		}

		$this->verify_parameters(
			array(
				'group_id' => 'is_numeric',
			)
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$group_id = absint( filter_input( INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT ) );

		if ( ! current_user_can( 'manage_group_information', $group_id ) ) {
			return $this->send_response( __( 'Invalid group.', 'lifterlms-groups' ), false );
		}

		$group = get_llms_group( $group_id );

		$id = media_handle_upload(
			'file',
			$group_id,
			array(
				// Translators: %1$s = Assignment title; %2$s = Student name.
				'post_title' => sprintf( esc_html__( '%1$s group banner image by %2$s', 'lifterlms-groups' ), strip_tags( $group->get( 'title' ) ), $student->get_name() ),
			)
		);

		if ( is_wp_error( $id ) ) {
			$this->send_response( $id->get_error_message(), false, $id );
		}

		if ( 'logo' === $img_type && ! set_post_thumbnail( $group->get( 'id' ), $id ) ) {
			$this->send_response( __( 'Error setting logo.', 'lifterlms-groups' ), false );
		}

		if ( 'banner' === $img_type && ! $group->set( 'banner', $id ) ) {
			$this->send_response( __( 'Error setting banner.', 'lifterlms-groups' ), false );
		}

		$this->send_response( __( 'Success', 'lifterlms-groups' ), true, array( 'source_url' => ( ( 'logo' === $img_type ) ? $group->get_logo() : $group->get_banner() ) ) );
	}

	/**
	 * Verify the parameters for an ajax call
	 *
	 * @since 1.0.2
	 */
	private function verify_parameters( $params ) {

		foreach ( $params as $var => $func ) {

			if ( ! isset( $_POST[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in `upload_file()`
				// Translators: %s = Missing paramter name.
				$this->send_response( sprintf( esc_html__( 'Missing required parameter: "%s"', 'lifterlms-groups' ), $var ), false );
			}

			$val = llms_filter_input( INPUT_POST, $var, FILTER_UNSAFE_RAW );
			if ( ! $func( $val ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in `upload_file()`
				// Translators: %s = Missing paramter name.
				$this->send_response( sprintf( esc_html__( 'Invalid value submitted for parameter: "%s"', 'lifterlms-groups' ), $var ), false );
			}
		}
	}

	/**
	 * Send a JSON response (&die)
	 *
	 * @since 1.0.2
	 *
	 * @param string  $message Message.
	 * @param boolean $success Success.
	 * @param array   $data    Data to send with the message.
	 * @return void
	 */
	private function send_response( $message, $success = true, $data = array() ) {

		wp_send_json(
			array(
				'data'    => $data,
				'message' => $message,
				'success' => $success,
			)
		);
	}
}

new LLMS_Groups_Banner_Image_Ajax_Handler();
