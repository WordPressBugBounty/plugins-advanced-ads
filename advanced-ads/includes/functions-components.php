<?php
/**
 * Functions for UI Toolkit components.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

/**
 * Render modal.
 *
 * @param array $args The passed view arguments, overwriting the default values.
 *
 * @return void
 */
function advads_modal( $args ): void {
	$args = wp_parse_args(
		$args,
		[
			'id'          => '',
			'title'       => '',
			'content'     => '',
			'file_path'   => '',
			'show_footer' => true,
			'wrap_class'  => '',
			'close_label' => __( 'Close', 'advanced-ads' ),
			'save_label'  => __( 'Save', 'advanced-ads' ),
		]
	);

	if ( is_callable( $args['content'] ) ) {
		ob_start();
		call_user_func( $args['content'] );
		$args['content'] = ob_get_clean();
	}

	if ( $args['file_path'] ) {
		ob_start();
		require $args['file_path'];
		$args['content'] = ob_get_clean();
	}

	require ADVADS_ABSPATH . 'views/components/modal.php';
}
