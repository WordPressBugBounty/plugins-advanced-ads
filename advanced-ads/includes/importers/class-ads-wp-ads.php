<?php
/**
 * Ads for WP Ads.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Importers;

use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Interfaces\Importer as Interface_Importer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Ads for WP Ads.
 */
class Ads_WP_Ads extends Importer implements Interface_Importer {

	/**
	 * Alignment mapping.
	 * Adsforwp uses strings: 'left', 'right', 'center', 'none'
	 */
	private const ALIGNMENT_MAP = [
		'default' => [
			'position' => 'default',
			'float'    => 0,
		],
		'left'    => [
			'position' => 'left',
			'float'    => 0,
		],
		'right'   => [
			'position' => 'right',
			'float'    => 0,
		],
		'center'  => [
			'position' => 'center',
			'float'    => 0,
		],
		'none'    => [
			'position' => 'default',
			'float'    => 0,
		],
	];


	/**
	 * Placement mapping.
	 * Note: 'manual' is used when no direct mapping is available or when the position is 'manual' in Adsforwp.
	 */
	private const PLACEMENT_MAP = [
		'before_the_content'              => 'post_top',
		'adsforwp_above_the_post_content' => 'post_top',
		'after_the_content'               => 'post_bottom',
		'adsforwp_below_the_post_content' => 'post_bottom',
		'between_the_content'             => 'post_content',
		'adsforwp_below_the_header'       => 'header',
		'adsforwp_below_the_footer'       => 'footer',
	];

	/**
	 * Get the unique identifier (ID) of the importer.
	 *
	 * @return string The unique ID of the importer.
	 */
	public function get_id(): string {
		return 'ads_wp_ads';
	}

	/**
	 * Get the title or name of the importer.
	 *
	 * @return string The title of the importer.
	 */
	public function get_title(): string {
		return __( 'Ads for WP Ads', 'advanced-ads' );
	}

	/**
	 * Get a description of the importer.
	 *
	 * @return string The description of the importer.
	 */
	public function get_description(): string {
		return __( 'Cleanly import Ads for WP blocks, groups, and global settings into Advanced Ads native structures.', 'advanced-ads' );
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
		return (bool) get_option( 'adsforwp_settings' ) || post_type_exists( 'adsforwp' );
	}

	/**
	 * Render form.
	 *
	 * @return void
	 */
	public function render_form(): void {
		?>
		<p><label><input type="checkbox" name="wp_ads_import[ads]" checked="checked" /> <?php esc_html_e( 'Import Ads & Placements', 'advanced-ads' ); ?></label></p>
		<p><label><input type="checkbox" name="wp_ads_import[groups]" checked="checked" /> <?php esc_html_e( 'Import Groups', 'advanced-ads' ); ?></label></p>
		<p><label><input type="checkbox" name="wp_ads_import[options]" checked="checked" /> <?php esc_html_e( 'Import Settings', 'advanced-ads' ); ?></label></p>
		<?php
	}

	/**
	 * Import data.
	 *
	 * @return WP_Error|string Success message on success, WP_Error on failure.
	 */
	public function import() {
		$what        = Params::post( 'wp_ads_import', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$history_key = $this->generate_history_key();
		$ad_map      = []; // Map old_id => new_id for group migration.
		$count       = 0;

		if ( isset( $what['ads'] ) ) {
			$count = $this->process_import( $history_key, $ad_map );
		}

		if ( isset( $what['options'] ) ) {
			$this->import_options();
		}

		if ( isset( $what['groups'] ) ) {
			$this->import_groups( $ad_map );
		}

		wp_advads()->importers->add_session_history( $this->get_id(), $history_key, $count );

		// translators: %1$d is the number of ads imported, %2$s is the title of the importer.
		return sprintf( esc_html__( '%1$d ads migrated from %2$s', 'advanced-ads' ), $count, $this->get_title() );
	}

	/**
	 * Import Ads from Adsforwp CPT.
	 *
	 * @param string $history_key Unique key for this import session, used for rollback.
	 * @param array  $ad_map      Reference to the ad ID mapping array.
	 * @return int Count of imported ads.
	 * @throws \Exception If any error occurs during the import process.
	 * @since 1.50.0
	 */
	private function process_import( $history_key, &$ad_map ): int {
		$count = 0;
		$posts = get_posts(
			[
				'post_type'      => 'adsforwp',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		foreach ( $posts as $old_ad ) {
			$ad_id = $this->create_ad( $old_ad, $history_key );
			if ( $ad_id ) {
				$ad_map[ $old_ad->ID ] = $ad_id;
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Create Ad and its corresponding Placement.
	 *
	 * @param object $old_ad     The original ad post from Adsforwp.
	 * @param string $history_key Unique key for this import session, used for rollback.
	 * @return int|false The new ad ID on success, or false on failure.
	 * @throws \Exception If any error occurs during the ad creation process.
	 * @since 1.50.0
	 */
	private function create_ad( $old_ad, $history_key ) {
		$meta    = array_map( fn( $item ) => is_array( $item ) ? $item[0] : $item, get_post_meta( $old_ad->ID ) );
		$type    = $meta['select_adtype'] ?? 'custom';
		$ad_type = ( 'ad_image' === $type ) ? 'image' : ( ( 'adsense' === $type ) ? 'adsense' : 'plain' );

		$ad = wp_advads_create_new_ad( $ad_type );
		$ad->set_title( $old_ad->post_title );

		// Map Content and Data based on type.
		if ( 'adsense' === $ad_type ) {
			$this->map_adsense_data( $ad, $meta );
		} elseif ( 'image' === $ad_type ) {
			$this->map_image_data( $ad, $meta );
		} else {
			$ad->set_content( $meta['custom_code'] ?? $old_ad->post_content );
		}

		// Apply Expiry.
		if ( ! empty( $meta['adsforwp_ad_expire_enable'] ) && ! empty( $meta['adsforwp_ad_expire_to'] ) ) {
			$options                = $ad->get_prop( 'options', [] );
			$options['expiry_date'] = strtotime( $meta['adsforwp_ad_expire_to'] );
			$ad->set_prop( 'options', $options );
		}

		// Apply Alignment.
		$this->apply_alignment( $ad, $meta );

		// Apply Styling (Margins).
		$this->apply_styling( $ad, $old_ad->ID );

		// Apply Conditions.
		$this->apply_conditions( $ad, $old_ad->ID );

		$ad_id = $ad->save();

		if ( $ad_id ) {
			$placement = $this->create_placement( $ad_id, $old_ad->post_title, $meta );
			$this->add_session_key( $ad, $placement, $history_key );
			return $ad_id;
		}

		return false;
	}

	/**
	 * Map AdSense specific meta.
	 *
	 * @param object $ad The new Ad instance being created.
	 * @param array  $meta The meta data from the old ad post, containing the AdSense information.
	 * @return void
	 * @since 1.50.0
	 */
	private function map_adsense_data( $ad, $meta ) {
		$slot   = $meta['data_ad_slot'] ?? '';
		$client = $meta['data_client_id'] ?? '';

		$ad->set_content( wp_json_encode( [ 'slotId' => $slot ] ) );

		$options = $ad->get_prop( 'options', [] );
		if ( $client ) {
			$options['adsense_id'] = $client;
		}
		$ad->set_prop( 'options', $options );
	}

	/**
	 * Map Image specific meta.
	 *
	 * @param object $ad The new Ad instance being created.
	 * @param array  $meta The meta data from the old ad post, containing the image information.
	 * @return void
	 * @since 1.50.0
	 */
	private function map_image_data( $ad, $meta ) {
		$image_url = $meta['adsforwp_ad_image'] ?? '';
		global $wpdb;
		$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_type = 'attachment'", $image_url ) );

		if ( $attachment_id ) {
			$ad->set_image_id( $attachment_id );
		}

		$options        = $ad->get_prop( 'options', [] );
		$options['url'] = $meta['adsforwp_ad_redirect_url'] ?? '';
		$ad->set_prop( 'options', $options );
	}

	/**
	 * Apply Alignment based on Adsforwp meta.
	 *
	 * @param object $ad The Ad instance being created.
	 * @param array  $meta The meta data from the old ad post, containing the alignment information.
	 * @return void
	 * @since 1.50.0
	 */
	private function apply_alignment( $ad, $meta ) {
		$align              = $meta['adsforwp_ad_align'] ?? 'default';
		$mapping            = self::ALIGNMENT_MAP[ $align ] ?? self::ALIGNMENT_MAP['default'];
		$output             = $ad->get_prop( 'output', [] );
		$output['position'] = $mapping['position'];
		$output['float']    = $mapping['float'];
		$ad->set_prop( 'output', $output );
	}

	/**
	 * Map margins from Adsforwp to Advanced Ads output options.
	 *
	 * @param object $ad The Ad instance being created.
	 * @param int    $old_ad_id The ID of the original ad post.
	 * @return void
	 * @since 1.50.0
	 */
	private function apply_styling( $ad, $old_ad_id ) {
		$margins = get_post_meta( $old_ad_id, 'adsforwp_ad_margin', true );
		if ( ! is_array( $margins ) ) {
			return;
		}

		$output = $ad->get_prop( 'output', [] );

		$output['margin'] = [
			'top'    => (int) $margins['ad_margin_top'] ?? 0,
			'right'  => (int) $margins['ad_margin_right'] ?? 0,
			'bottom' => (int) $margins['ad_margin_bottom'] ?? 0,
			'left'   => (int) $margins['ad_margin_left'] ?? 0,
		];

		$ad->set_prop( 'output', $output );
	}

	/**
	 * Create Placement.
	 *
	 * @param int    $ad_id The ID of the newly created Ad.
	 * @param string $title The title for the Placement, typically the same as the Ad.
	 * @param array  $meta The meta data from the old ad post, containing the placement information.
	 * @return object|null The created Placement instance, or null if no placement was created.
	 * @since 1.50.0
	 * @throws \Exception If any error occurs during the placement creation process.
	 */
	private function create_placement( $ad_id, $title, $meta ) {
		$pos = $meta['wheretodisplay'] ?? '';
		if ( ! $pos || 'manual' === $pos ) {
			return null;
		}
		$type  = self::PLACEMENT_MAP[ $pos ] ?? 'manual';
		$props = [ 'item' => 'ad_' . $ad_id ];

		if ( 'between_the_content' === $pos ) {
			$props['index'] = (int) ( $meta['paragraph_number'] ?? 1 );
		}

		$placement = wp_advads_create_new_placement( $type );
		if ( $placement ) {
			$placement->set_title( $title );
			foreach ( $props as $key => $val ) {
				$placement->set_prop( $key, $val );
			}
			$placement->save();
		}

		return $placement;
	}

	/**
	 * Import Global Options.
	 *
	 * @return void
	 */
	private function import_options(): void {
		$afw_settings = get_option( 'adsforwp_settings' );
		if ( empty( $afw_settings ) ) {
			return;
		}

		$aa_settings = get_option( 'advanced-ads', [] );
		if ( ! empty( $afw_settings['ad_sponsorship_label_text'] ) ) {
			$aa_settings['custom-label']['enabled'] = 1;
			$aa_settings['custom-label']['html']    = 1;
			$aa_settings['custom-label']['text']    = $afw_settings['ad_sponsorship_label_text'];
		}

		if ( isset( $afw_settings['ad_performance_tracker'] ) ) {
			$aa_settings['stats_enabled'] = (int) $afw_settings['ad_performance_tracker'];
		}

		if ( ! empty( $afw_settings['ad_blocker_support'] ) ) {
			$aa_settings['adblocker_support']      = 1;
			$aa_settings['adblocker_notice_title'] = $afw_settings['notice_title'] ?? '';
		}

		update_option( 'advanced-ads', $aa_settings );

		if ( ! empty( $afw_settings['data_client_id'] ) ) {
			$adsense_opt               = get_option( 'advanced-ads-adsense', [] );
			$adsense_opt['adsense-id'] = $afw_settings['data_client_id'];
			update_option( 'advanced-ads-adsense', $adsense_opt );
		}
	}

	/**
	 * Convert Group CPT to native Taxonomy.
	 *
	 * @param array $ad_map A mapping of old ad IDs to new ad IDs, used to maintain group associations during the import process.
	 * @return void
	 * @since 1.50.0
	 * @throws \Exception If any error occurs during the group import process.
	 */
	private function import_groups( $ad_map ) {
		$groups = get_posts(
			[
				'post_type'      => 'adsforwp-groups',
				'posts_per_page' => -1,
			]
		);
		foreach ( $groups as $old_group ) {
			$term    = wp_insert_term( $old_group->post_title, 'advanced_ads_groups' );
			$term_id = ! is_wp_error( $term ) ? $term['term_id'] : ( $term->error_data['term_exists'] ?? 0 );

			if ( $term_id ) {
				// Fetch group styling (margins) from Ads for WP Group.
				$group_margins = get_post_meta( $old_group->ID, 'adsforwp_ad_margin', true );

				$ads_in_group = get_post_meta( $old_group->ID, 'adsforwp_ads', true );
				if ( is_array( $ads_in_group ) ) {
					foreach ( array_keys( $ads_in_group ) as $old_ad_id ) {
						if ( isset( $ad_map[ $old_ad_id ] ) ) {
							$new_ad_id = $ad_map[ $old_ad_id ];
							wp_set_object_terms( $new_ad_id, (int) $term_id, 'advanced_ads_groups', true );

							// Apply Group styling to individual Advanced Ad objects.
							if ( is_array( $group_margins ) ) {
								$ad_obj = wp_advads_get_ad( $new_ad_id );

								$margin = [
									'top'    => (int) $group_margins['ad_margin_top'] ?? 0,
									'right'  => (int) $group_margins['ad_margin_right'] ?? 0,
									'bottom' => (int) $group_margins['ad_margin_bottom'] ?? 0,
									'left'   => (int) $group_margins['ad_margin_left'] ?? 0,
								];

								$ad_obj->set_margin( $margin );
								$ad_obj->save();
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Map Display Conditions from Adsforwp meta.
	 *
	 * @param object $ad The Ad instance being created.
	 * @param int    $old_ad_id The ID of the original ad post.
	 * @return void
	 * @since 1.50.0
	 */
	private function apply_conditions( $ad, $old_ad_id ) {
		$data_groups = get_post_meta( $old_ad_id, 'data_group_array', true );
		if ( ! is_array( $data_groups ) ) {
			return;
		}

		$display_conditions = [];

		foreach ( $data_groups as $group ) {
			if ( empty( $group['data_array'] ) || ! is_array( $group['data_array'] ) ) {
				continue;
			}

			foreach ( $group['data_array'] as $rule ) {
				$key      = $rule['key_1'] ?? '';
				$operator = $rule['key_2'] ?? 'equal';
				$value    = $rule['key_3'] ?? '';

				if ( 'show_globally' === $key ) {
					$display_conditions[] = [
						'type'     => 'general',
						'operator' => 'is',
						'value'    => [ 'show_globally' ],
					];
				} elseif ( 'post_type' === $key ) {
					$mapped_val = ( 'post' === $value ) ? 'is_single' : ( ( 'page' === $value ) ? 'is_page' : '' );

					if ( $mapped_val ) {
						$display_conditions[] = [
							'type'     => 'general',
							'operator' => ( 'equal' === $operator ) ? 'is' : 'is_not',
							'value'    => [ $mapped_val ],
						];
					}
				}
			}
		}

		if ( ! empty( $display_conditions ) ) {
			$ad->set_display_conditions( $display_conditions );
		}
	}
}
