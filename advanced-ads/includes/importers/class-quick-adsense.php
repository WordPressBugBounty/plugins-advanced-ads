<?php // phpcs:ignoreFile
/**
 * Quick Adsense.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Importers;

use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Interfaces\Importer as Interface_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Quick Adsense.
 */
class Quick_Adsense extends Importer implements Interface_Importer {

	/**
	 * Get the unique identifier (ID) of the importer.
	 *
	 * @return string The unique ID of the importer.
	 */
	public function get_id(): string {
		return 'quick_adsense';
	}

	/**
	 * Get the title or name of the importer.
	 *
	 * @return string The title of the importer.
	 */
	public function get_title(): string {
		return __( 'Quick Adsense', 'advanced-ads' );
	}

	/**
	 * Get a description of the importer.
	 *
	 * @return string The description of the importer.
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Get the icon to this importer.
	 *
	 * @return string The icon for the importer.
	 */
	public function get_icon(): string {
		return '<span class="dashicons dashicons-insert"></span>';
	}

	/**
	 * Detect the importer in database.
	 *
	 * @return bool True if detected; otherwise, false.
	 */
	public function detect(): bool {
		return false;
	}

	/**
	 * Render form.
	 *
	 * @return void
	 */
	public function render_form(): void {
		?>
		<fieldset>
			<p><label><input type="radio" name="import_type" checked="checked" /> <?php esc_html_e( 'Import Ads', 'advanced-ads' ); ?></label></p>
			<p><label><input type="radio" name="import_type" /> <?php esc_html_e( 'Import Groups', 'advanced-ads' ); ?></label></p>
			<p><label><input type="radio" name="import_type" /> <?php esc_html_e( 'Import Placements', 'advanced-ads' ); ?></label></p>
			<p><label><input type="radio" name="import_type" /> <?php esc_html_e( 'Import Settings', 'advanced-ads' ); ?></label></p>
		</fieldset>
		<?php
	}

	/**
	 * Import data.
	 *
	 * @return WP_Error|string
	 */
	public function import() {
		return '';
	}

	/**
	 * Import all Quick Adsense ads into adsforwp post type.
	 *
	 * @return array|void
	 */
	public function adsforwp_import_all_quick_adsense_ads() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = Params::get( 'adsforwp_security_nonce', '' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'adsforwp_ajax_check_nonce' ) ) {
			return;
		}

		$wpdb->query( 'START TRANSACTION' );
		$result   = [];
		$user_id  = get_current_user_id();
		$settings = get_option( 'quick_adsense_settings' );

		$this->import_onpost_ads( $settings, $user_id, $result );
		$this->import_widget_ads( $settings, $user_id, $result );

		if ( is_wp_error( $result ) ) {
			echo $result->get_error_message();
			$wpdb->query( 'ROLLBACK' );
		} else {
			$wpdb->query( 'COMMIT' );
			return $result;
		}
	}

	/**
	 * Import on-post ads (slots 1–10).
	 *
	 * @param array $settings Quick Adsense settings.
	 * @param int   $user_id  Current user ID.
	 * @param array $result   Accumulated update_post_meta results.
	 *
	 * @return void
	 */
	private function import_onpost_ads( $settings, $user_id, array &$result ): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			$content_key = 'onpost_ad_' . $i . '_content';
			if ( empty( $settings[ $content_key ] ) ) {
				continue;
			}

			$post_id = $this->insert_migrated_ad(
				$user_id,
				'Custom Ad ' . $i . ' (Migrated from Quick Adsense)'
			);

			$alignment = $this->get_alignment_data(
				$settings[ 'onpost_ad_' . $i . '_alignment' ] ?? null,
				$settings[ 'onpost_ad_' . $i . '_margin' ] ?? null
			);
			$display   = $this->resolve_onpost_display( $settings, $i );

			$this->save_ad_meta(
				$post_id,
				[
					'select_adtype'                  => 'custom',
					'custom_code'                    => $settings[ $content_key ],
					'adposition'                     => $display['numberofparas'],
					'paragraph_number'               => $display['paragraph_number'],
					'adsforwp_ad_align'              => $alignment['align'],
					'adsforwp_ad_margin'             => $alignment['margin'],
					'imported_from'                  => 'quick_adsense',
					'wheretodisplay'                 => $display['wheretodisplay'],
					'display_tag_name'               => $display['display_tag_name'],
					'ads_on_every_paragraphs_number' => $display['ads_on_every_paras'],
					'data_group_array'               => $this->build_post_page_targeting( $settings ),
				],
				$result
			);
		}
	}

	/**
	 * Import widget ads (slots 1–10).
	 *
	 * @param array $settings Quick Adsense settings.
	 * @param int   $user_id  Current user ID.
	 * @param array $result   Accumulated update_post_meta results.
	 *
	 * @return void
	 */
	private function import_widget_ads( $settings, $user_id, array &$result ): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			$content_key = 'widget_ad_' . $i . '_content';
			if ( empty( $settings[ $content_key ] ) ) {
				continue;
			}

			$post_id = $this->insert_migrated_ad(
				$user_id,
				'Custom widget Ad ' . $i . ' (Migrated from Quick Adsense)'
			);

			// Legacy source used onpost alignment/margin keys for widget ads.
			$alignment = $this->get_alignment_data(
				$settings[ 'onpost_ad_' . $i . '_alignment' ] ?? null,
				$settings[ 'onpost_ad_' . $i . '_margin' ] ?? null
			);

			$this->save_ad_meta(
				$post_id,
				[
					'select_adtype'     => 'custom',
					'custom_code'       => $settings[ $content_key ],
					'adposition'        => '',
					'paragraph_number'  => '',
					'adsforwp_ad_align' => $alignment['align'],
					'imported_from'     => 'quick_adsense',
					'wheretodisplay'    => '',
					'data_group_array'  => [
						'group-0' => $this->build_condition_group( 'show_globally', 'post' ),
					],
				],
				$result
			);
		}
	}

	/**
	 * Create a migrated adsforwp post.
	 *
	 * @param int    $user_id Author ID.
	 * @param string $title   Post title / slug.
	 *
	 * @return int Post ID.
	 */
	private function insert_migrated_ad( $user_id, $title ): int {
		return (int) wp_insert_post(
			[
				'post_author' => $user_id,
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_name'   => $title,
				'post_type'   => 'adsforwp',
			]
		);
	}

	/**
	 * Persist ad meta keys and collect results.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Meta key => value map.
	 * @param array $result  Accumulated results.
	 *
	 * @return void
	 */
	private function save_ad_meta( $post_id, array $meta, array &$result ): void {
		foreach ( $meta as $key => $val ) {
			$result[] = update_post_meta( $post_id, $key, $val );
		}
	}

	/**
	 * Map Quick Adsense alignment/margin to adsforwp meta values.
	 *
	 * @param mixed $alignment Alignment setting (1–4).
	 * @param mixed $margin    Margin setting.
	 *
	 * @return array{align: string, margin: array}
	 */
	private function get_alignment_data( $alignment, $margin ): array {
		$map = [
			1 => [
				'align'  => 'left',
				'margin' => [
					'ad_margin_top'    => $margin,
					'ad_margin_bottom' => $margin,
					'ad_margin_left'   => 0,
					'ad_margin_right'  => $margin,
				],
			],
			2 => [
				'align'  => 'center',
				'margin' => [
					'ad_margin_top'    => $margin,
					'ad_margin_bottom' => $margin,
					'ad_margin_left'   => 0,
					'ad_margin_right'  => 0,
				],
			],
			3 => [
				'align'  => 'right',
				'margin' => [
					'ad_margin_top'    => $margin,
					'ad_margin_bottom' => $margin,
					'ad_margin_left'   => $margin,
					'ad_margin_right'  => 0,
				],
			],
			4 => [
				'align'  => 'none',
				'margin' => [
					'ad_margin_top'    => 0,
					'ad_margin_bottom' => 0,
					'ad_margin_left'   => 0,
					'ad_margin_right'  => 0,
				],
			],
		];

		$alignment = (int) $alignment;
		if ( ! isset( $map[ $alignment ] ) ) {
			return [
				'align'  => '',
				'margin' => [],
			];
		}

		$data = $map[ $alignment ];
		if ( empty( $margin ) && 4 !== $alignment ) {
			$data['margin'] = [];
		}

		return $data;
	}

	/**
	 * Build post/page targeting groups from settings.
	 *
	 * @param array $settings Quick Adsense settings.
	 *
	 * @return array
	 */
	private function build_post_page_targeting( $settings ): array {
		$data_group_array = [];

		if ( ! empty( $settings['enable_on_posts'] ) && (int) $settings['enable_on_posts'] === 1 ) {
			$data_group_array['group-0'] = $this->build_condition_group( 'post_type', 'post' );
		}

		if ( ! empty( $settings['enable_on_pages'] ) && (int) $settings['enable_on_pages'] === 1 ) {
			$data_group_array['group-1'] = $this->build_condition_group( 'post_type', 'page' );
		}

		return $data_group_array;
	}

	/**
	 * Build a single condition group.
	 *
	 * @param string $key_1 Condition key.
	 * @param string $key_3 Condition value.
	 *
	 * @return array
	 */
	private function build_condition_group( $key_1, $key_3 ): array {
		return [
			'data_array' => [
				[
					'key_1' => $key_1,
					'key_2' => 'equal',
					'key_3' => $key_3,
				],
			],
		];
	}

	/**
	 * Resolve where/how an on-post ad should display.
	 *
	 * @param array $settings Quick Adsense settings.
	 * @param int   $i        Ad slot index (1–10).
	 *
	 * @return array{wheretodisplay: string, display_tag_name: string, numberofparas: string, paragraph_number: mixed, ads_on_every_paras: mixed}
	 */
	private function resolve_onpost_display( $settings, $i ): array {
		$display = [
			'wheretodisplay'     => '',
			'display_tag_name'   => '',
			'numberofparas'      => '',
			'paragraph_number'   => '',
			'ads_on_every_paras' => '',
		];

		if ( isset( $settings['ad_beginning_of_post'] ) && (int) $settings['ad_beginning_of_post'] === $i
			&& ! empty( $settings['enable_position_beginning_of_post'] ) ) {
			$display['wheretodisplay'] = 'before_the_content';
		} elseif ( isset( $settings['ad_end_of_post'] ) && (int) $settings['ad_end_of_post'] === $i
			&& ! empty( $settings['enable_position_end_of_post'] ) ) {
			$display['wheretodisplay'] = 'after_the_content';
		} elseif ( isset( $settings['ad_middle_of_post'] ) && (int) $settings['ad_middle_of_post'] === $i
			&& ! empty( $settings['enable_position_middle_of_post'] ) ) {
			$display['wheretodisplay'] = 'between_the_content';
		}

		for ( $j = 1; $j <= 3; $j++ ) {
			if ( ! isset( $settings[ 'ad_after_para_option_' . $j ] )
				|| (int) $settings[ 'ad_after_para_option_' . $j ] !== $i ) {
				continue;
			}

			$tag = null;
			if ( ! empty( $settings[ 'enable_position_after_para_option_' . $j ] ) ) {
				$tag = 'p_tag';
			} elseif ( ! empty( $settings[ 'enable_position_after_image_option_' . $j ] ) ) {
				$tag = 'img_tag';
			}

			if ( null === $tag ) {
				continue;
			}

			$display['wheretodisplay']   = 'between_the_content';
			$display['numberofparas']    = 'number_of_paragraph';
			$display['display_tag_name'] = $tag;
			$display['paragraph_number'] = $settings[ 'position_after_para_option_' . $j ] ?? '';

			if ( ! empty( $settings[ 'enable_jump_position_after_para_option_' . $j ] ) ) {
				$display['ads_on_every_paras'] = 1;
			}
		}

		return $display;
	}
}
