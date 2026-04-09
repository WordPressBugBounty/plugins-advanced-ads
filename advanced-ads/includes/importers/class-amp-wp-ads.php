<?php
/**
 * AMP for WP Ads Importer.
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
 * AMP for WP Ads.
 */
class Amp_WP_Ads extends Importer implements Interface_Importer {

	/**
	 * Cached AMP options from the database.
	 *
	 * @var array|null
	 */
	private $amp_options = null;

	/**
	 * Mapping of AMP for WP position keys to Advanced Ads placement types.
	 */
	private const PLACEMENT_MAP = [
		'amp_below_the_header'       => 'header',
		'amp_below_the_footer'       => 'footer',
		'amp_above_the_footer'       => 'footer',
		'amp_above_the_post_content' => 'post_top',
		'amp_below_the_post_content' => 'post_bottom',
		'amp_below_the_title'        => 'post_top',
		'amp_above_related_post'     => 'post_bottom',
		'amp_below_author_box'       => 'post_bottom',
		'amp_after_featured_image'   => 'post_top',
		'amp_ads_in_loops'           => 'post_top',
		'after_paragraph'            => 'post_content',
		'after_the_percentage'       => 'post_content',
	];

	/**
	 * Mapping of AMP for WP ad position indices (basic ads) to position slugs.
	 */
	private const BASIC_POSITION_MAP = [
		1 => 'amp_below_the_header',
		2 => 'amp_below_the_footer',
		3 => 'amp_above_the_post_content',
		4 => 'amp_below_the_post_content',
		5 => 'amp_below_the_title',
		6 => 'amp_above_related_post',
	];

	/**
	 * Mapping of AMP for WP standard ad position indices to position slugs.
	 */
	private const STANDARD_POSITION_MAP = [
		1  => 'amp_below_the_header',
		2  => 'amp_below_the_footer',
		3  => 'amp_above_the_footer',
		4  => 'amp_above_the_post_content',
		5  => 'amp_below_the_post_content',
		6  => 'amp_below_the_title',
		7  => 'amp_above_related_post',
		8  => 'amp_below_author_box',
		9  => 'amp_ads_in_loops',
		10 => 'amp_doubleclick_sticky_ad',
	];

	/**
	 * Mapping of AMP for WP ad size indices to width x height.
	 */
	private const SIZE_MAP = [
		'1' => [ 'width' => '300', 'height' => '250' ],
		'2' => [ 'width' => '336', 'height' => '280' ],
		'3' => [ 'width' => '728', 'height' => '90' ],
		'4' => [ 'width' => '300', 'height' => '600' ],
		'5' => [ 'width' => '320', 'height' => '100' ],
		'6' => [ 'width' => '200', 'height' => '50' ],
		'7' => [ 'width' => '320', 'height' => '50' ],
	];

	/**
	 * Mapping of AMP for WP percentage-based positions to numeric values.
	 */
	private const PERCENTAGE_MAP = [
		'20-percent' => '20',
		'40-percent' => '40',
		'50-percent' => '50',
		'60-percent' => '60',
		'80-percent' => '80',
	];

	// -------------------------------------------------------------------------
	// Interface methods
	// -------------------------------------------------------------------------

	/**
	 * Get the unique identifier (ID) of the importer.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'amp_wp_ads';
	}

	/**
	 * Get the title or name of the importer.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'AMP for WP Ads', 'advanced-ads' );
	}

	/**
	 * Get a description of the importer.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Migrate AMP for WP ads into Advanced Ads. Automatically detects AdSense, DoubleClick, MGID, and plain-text blocks.', 'advanced-ads' );
	}

	/**
	 * Get the icon for this importer.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return '<span class="dashicons dashicons-insert"></span>';
	}

	/**
	 * Detect whether AMP for WP ad data exists in the database.
	 *
	 * @return bool
	 */
	public function detect(): bool {
		$options = $this->get_amp_options();
		return ! empty( $options );
	}

	/**
	 * Render the import form shown to the user before they trigger the import.
	 *
	 * @return void
	 */
	public function render_form(): void {
		$counts = $this->count_importable_ads();
		?>
		<p class="text-base m-0">
			<?php
			printf(
				/* translators: %d: total number of importable ad slots */
				esc_html__( 'We found AMP for WP configuration with %d importable ad slot(s).', 'advanced-ads' ),
				(int) array_sum( $counts )
			);
			?>
		</p>
		<p>
			<label>
				<input type="checkbox" name="amp_import[basic]" checked="checked" />
				<?php
				printf(
					/* translators: %d: number of basic ad slots */
					esc_html__( 'Import Basic Ad Slots (%d found)', 'advanced-ads' ),
					(int) $counts['basic']
				);
				?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="amp_import[incontent]" checked="checked" />
				<?php
				printf(
					/* translators: %d: number of in-content ad slots */
					esc_html__( 'Import In-Content Ad Slots (%d found)', 'advanced-ads' ),
					(int) $counts['incontent']
				);
				?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="amp_import[standard]" checked="checked" />
				<?php
				printf(
					/* translators: %d: number of standard / general ad slots */
					esc_html__( 'Import Standard / General Ad Slots (%d found)', 'advanced-ads' ),
					(int) $counts['standard']
				);
				?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="amp_import[featured]" checked="checked" />
				<?php
				printf(
					/* translators: %d: number of after-featured-image ad slots */
					esc_html__( 'Import After-Featured-Image Ad Slot (%d found)', 'advanced-ads' ),
					(int) $counts['featured']
				);
				?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="amp_import[options]" checked="checked" />
				<?php esc_html_e( 'Import Global Settings (ad label, sponsorship text)', 'advanced-ads' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Run the import.
	 *
	 * @return \WP_Error|string
	 */
	public function import() {
		$what        = Params::post( 'amp_import', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$history_key = $this->generate_history_key();
		$count       = 0;

		if ( isset( $what['basic'] ) ) {
			$count += $this->import_basic_ads( $history_key );
		}

		if ( isset( $what['incontent'] ) ) {
			$count += $this->import_incontent_ads( $history_key );
		}

		if ( isset( $what['standard'] ) ) {
			$count += $this->import_standard_ads( $history_key );
		}

		if ( isset( $what['featured'] ) ) {
			$count += $this->import_featured_image_ad( $history_key );
		}

		if ( isset( $what['options'] ) ) {
			$this->import_options();
		}

		wp_advads()->importers->add_session_history( $this->get_id(), $history_key, $count );

		/* translators: 1: count of migrated ads 2: importer title */
		return sprintf( esc_html__( '%1$d ads migrated from %2$s', 'advanced-ads' ), $count, $this->get_title() );
	}

	// -------------------------------------------------------------------------
	// Option retrieval
	// -------------------------------------------------------------------------

	/**
	 * Return the cached AMP for WP option array.
	 *
	 * @return array|false
	 */
	private function get_amp_options() {
		if ( null !== $this->amp_options ) {
			return $this->amp_options;
		}

		$this->amp_options = get_option( 'redux_builder_amp' );

		return $this->amp_options;
	}

	// -------------------------------------------------------------------------
	// Counting helpers (used by render_form)
	// -------------------------------------------------------------------------

	/**
	 * Count how many ads of each group are importable.
	 *
	 * @return array { basic, incontent, standard, featured }
	 */
	private function count_importable_ads(): array {
		$amp    = $this->get_amp_options();
		$counts = [
			'basic'     => 0,
			'incontent' => 0,
			'standard'  => 0,
			'featured'  => 0,
		];

		if ( empty( $amp ) ) {
			return $counts;
		}

		// Basic ads (slots 1-6).
		for ( $i = 1; $i <= 6; $i++ ) {
			if ( ! empty( $amp[ 'enable-amp-ads-' . $i ] ) ) {
				++$counts['basic'];
			}
		}

		// In-content ads (slots 1-6, requires ADVANCED_AMP_ADS_VERSION).
		for ( $i = 1; $i <= 6; $i++ ) {
			if ( ! empty( $amp[ 'ampforwp-incontent-ad-' . $i ] ) ) {
				++$counts['incontent'];
			}
		}

		// Standard / general ads (slots 1-10).
		for ( $i = 1; $i <= 10; $i++ ) {
			if ( ! empty( $amp[ 'ampforwp-standard-ads-' . $i ] ) ) {
				++$counts['standard'];
			}
		}

		// After-featured-image slot.
		if ( ! empty( $amp['ampforwp-after-featured-image-ad'] ) ) {
			$counts['featured'] = 1;
		}

		return $counts;
	}

	// -------------------------------------------------------------------------
	// Ad-group importers
	// -------------------------------------------------------------------------

	/**
	 * Import basic AMP ad slots (positions 1-6).
	 *
	 * @param string $history_key Session key for rollback.
	 * @return int Number of ads created.
	 */
	private function import_basic_ads( string $history_key ): int {
		$amp   = $this->get_amp_options();
		$count = 0;

		for ( $i = 1; $i <= 6; $i++ ) {
			if ( empty( $amp[ 'enable-amp-ads-' . $i ] ) ) {
				continue;
			}

			$ad_type = $amp[ 'enable-amp-ads-type-' . $i ] ?? 'adsense';

			// Skip incomplete AdSense/MGID entries (mirrors QUADS validation).
			if ( 'adsense' === $ad_type &&
				(
					empty( $amp[ 'enable-amp-ads-text-feild-client-' . $i ] ) ||
					empty( $amp[ 'enable-amp-ads-text-feild-slot-' . $i ] )
				)
			) {
				continue;
			}

			if ( 'mgid' === $ad_type &&
				(
					empty( $amp[ 'enable-amp-ads-mgid-field-data-pub-' . $i ] ) ||
					empty( $amp[ 'enable-amp-ads-mgid-field-data-widget-' . $i ] )
				)
			) {
				continue;
			}

			$position = self::BASIC_POSITION_MAP[ $i ] ?? 'manual';

			// MGID slot 2 is always a shortcode / manual placement.
			if ( 'mgid' === $ad_type && 2 === $i ) {
				$position = 'manual';
			}

			$size   = self::SIZE_MAP[ $amp[ 'enable-amp-ads-select-' . $i ] ?? '1' ] ?? self::SIZE_MAP['1'];
			$width  = $size['width'];
			$height = $size['height'];

			if ( 'mgid' === $ad_type ) {
				$width  = $amp[ 'enable-amp-ads-mgid-width-' . $i ] ?? $width;
				$height = $amp[ 'enable-amp-ads-mgid-height-' . $i ] ?? $height;
			}

			$is_responsive = ! empty( $amp[ 'enable-amp-ads-resp-' . $i ] );

			if ( 'mgid' === $ad_type ) {
				$title    = sprintf( 'MGID Ad %d (Migrated from AMP)', $i );
				$aa_type  = 'plain';
				$raw_data = [
					'type'      => 'mgid',
					'publisher' => $amp[ 'enable-amp-ads-mgid-field-data-pub-' . $i ] ?? '',
					'widget'    => $amp[ 'enable-amp-ads-mgid-field-data-widget-' . $i ] ?? '',
					'container' => $amp[ 'enable-amp-ads-mgid-field-data-con-' . $i ] ?? '',
					'width'     => $width,
					'height'    => $height,
				];
			} else {
				$title    = sprintf( 'Adsense Ad %d (Migrated from AMP)', $i );
				$aa_type  = 'adsense';
				$raw_data = [
					'type'          => 'adsense',
					'client'        => $amp[ 'enable-amp-ads-text-feild-client-' . $i ] ?? '',
					'slot'          => $amp[ 'enable-amp-ads-text-feild-slot-' . $i ] ?? '',
					'width'         => $width,
					'height'        => $height,
					'is_responsive' => $is_responsive,
				];
			}

			// Visibility: slot 3 uses configurable conditions; others are global.
			$visibility = $this->build_basic_visibility( $amp, $i );

			$this->create_ad_and_placement(
				$title,
				$aa_type,
				$raw_data,
				$position,
				$visibility,
				[],
				$history_key,
				$count
			);
		}

		return $count;
	}

	/**
	 * Import in-content AMP ad slots (positions 1-6, advanced add-on only).
	 *
	 * @param string $history_key Session key for rollback.
	 * @return int Number of ads created.
	 */
	private function import_incontent_ads( string $history_key ): int {

		$amp   = $this->get_amp_options();
		$count = 0;

		for ( $i = 1; $i <= 6; $i++ ) {
			if ( empty( $amp[ 'ampforwp-incontent-ad-' . $i ] ) ) {
				continue;
			}

			$ad_type_raw = $amp[ 'ampforwp-advertisement-type-incontent-ad-' . $i ] ?? '';

			// Type 4 = unsupported; skip.
			if ( '4' === (string) $ad_type_raw ) {
				continue;
			}

			// Skip incomplete entries.
			if ( '1' === (string) $ad_type_raw &&
				(
					empty( $amp[ 'ampforwp-adsense-ad-data-ad-client-incontent-ad-' . $i ] ) ||
					empty( $amp[ 'ampforwp-adsense-ad-data-ad-slot-incontent-ad-' . $i ] )
				)
			) {
				continue;
			}

			if ( '5' === (string) $ad_type_raw &&
				(
					empty( $amp[ 'ampforwp-mgid-ad-Data-Publisher-incontent-ad-' . $i ] ) ||
					empty( $amp[ 'ampforwp-mgid-ad-Data-Widget-incontent-ad-' . $i ] )
				)
			) {
				continue;
			}

			[ $aa_type, $title, $raw_data, $position_raw ] = $this->resolve_incontent_slot( $amp, $i, $ad_type_raw );

			$is_responsive = ! empty( $amp[ 'adsense-rspv-ad-incontent-' . $i ] );
			if ( isset( $raw_data['is_responsive'] ) ) {
				$raw_data['is_responsive'] = $is_responsive;
			}

			[ $position, $paragraph_props ] = $this->resolve_incontent_position( $position_raw );

			// In-content ads always display on posts.
			$visibility = [
				[
					'type'     => 'general',
					'operator' => 'is',
					'value'    => [ 'is_single' ],
				],
			];

			$this->create_ad_and_placement(
				$title,
				$aa_type,
				$raw_data,
				$position,
				$visibility,
				$paragraph_props,
				$history_key,
				$count
			);
		}

		return $count;
	}

	/**
	 * Import standard / general AMP ad slots (positions 1-10, advanced add-on only).
	 *
	 * @param string $history_key Session key for rollback.
	 * @return int Number of ads created.
	 */
	private function import_standard_ads( string $history_key ): int {

		$amp   = $this->get_amp_options();
		$count = 0;

		for ( $i = 1; $i <= 10; $i++ ) {
			if ( empty( $amp[ 'ampforwp-standard-ads-' . $i ] ) ) {
				continue;
			}

			$ad_type_raw = $amp[ 'ampforwp-advertisement-type-standard-' . $i ] ?? '';

			// Skip incomplete entries.
			if ( '1' === (string) $ad_type_raw &&
				(
					empty( $amp[ 'ampforwp-adsense-ad-data-ad-client-standard-' . $i ] ) ||
					empty( $amp[ 'ampforwp-adsense-ad-data-ad-slot-standard-' . $i ] )
				)
			) {
				continue;
			}

			if ( '2' === (string) $ad_type_raw && empty( $amp[ 'ampforwp-doubleclick-ad-data-slot-standard-' . $i ] ) ) {
				continue;
			}

			if ( '5' === (string) $ad_type_raw &&
				(
					empty( $amp[ 'ampforwp-mgid-data-ad-data-publisher-standard-' . $i ] ) ||
					empty( $amp[ 'ampforwp-mgid-data-ad-data-widget-standard-' . $i ] )
				)
			) {
				continue;
			}

			[ $aa_type, $title, $raw_data ] = $this->resolve_standard_slot( $amp, $i, $ad_type_raw );

			$position          = self::STANDARD_POSITION_MAP[ $i ] ?? 'manual';
			$aa_placement_type = self::PLACEMENT_MAP[ $position ] ?? 'manual';

			// Standard ads display on posts globally.
			$visibility = [
				[
					'type'     => 'general',
					'operator' => 'is',
					'value'    => [ 'is_single' ],
				],
			];

			$this->create_ad_and_placement(
				$title,
				$aa_type,
				$raw_data,
				$aa_placement_type,
				$visibility,
				[],
				$history_key,
				$count
			);
		}

		return $count;
	}

	/**
	 * Import the after-featured-image ad slot (advanced add-on only).
	 *
	 * @param string $history_key Session key for rollback.
	 * @return int Number of ads created (0 or 1).
	 */
	private function import_featured_image_ad( string $history_key ): int {

		$amp = $this->get_amp_options();

		if ( empty( $amp['ampforwp-after-featured-image-ad'] ) ) {
			return 0;
		}

		$ad_type_raw = $amp['ampforwp-after-featured-image-ad-type'] ?? '';
		$count       = 0;

		[ $aa_type, $title, $raw_data ] = $this->resolve_featured_image_slot( $amp, $ad_type_raw );

		$visibility = [
			[
				'type'     => 'general',
				'operator' => 'is',
				'value'    => [ 'is_single' ],
			],
		];

		$this->create_ad_and_placement(
			$title,
			$aa_type,
			$raw_data,
			'post_top',
			$visibility,
			[],
			$history_key,
			$count
		);

		return $count;
	}

	// -------------------------------------------------------------------------
	// Slot resolution helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve ad type, title, and raw data for a basic ad slot.
	 * Returns visibility rules for slot i (slot 3 is configurable).
	 *
	 * @param array $amp AMP options.
	 * @param int   $i   Slot index.
	 * @return array Visibility condition array.
	 */
	private function build_basic_visibility( array $amp, int $i ): array {
		if ( 3 !== $i ) {
			return [
				[
					'type'     => 'general',
					'operator' => 'is',
					'value'    => [ 'show_globally' ],
				],
			];
		}

		$conditions = $amp['made-amp-ad-3-global'] ?? [];
		if ( empty( $conditions ) ) {
			return [
				[
					'type'     => 'general',
					'operator' => 'is',
					'value'    => [ 'show_globally' ],
				],
			];
		}

		$display_conditions = [];
		foreach ( $conditions as $cond ) {
			switch ( (string) $cond ) {
				case '1': // Single posts.
					$display_conditions[] = [
						'type'     => 'general',
						'operator' => 'is',
						'value'    => [ 'is_single' ],
					];
					break;
				case '2': // Pages.
					$display_conditions[] = [
						'type'     => 'general',
						'operator' => 'is',
						'value'    => [ 'is_page' ],
					];
					break;
				case '4': // Global.
					return [
						[
							'type'     => 'general',
							'operator' => 'is',
							'value'    => [ 'show_globally' ],
						],
					];
			}
		}

		return $display_conditions ?? [
			[
				'type'     => 'general',
				'operator' => 'is',
				'value'    => [ 'show_globally' ],
			],
		];
	}

	/**
	 * Resolve ad type label, title, and raw data for an in-content slot.
	 *
	 * @param array  $amp         AMP options.
	 * @param int    $i           Slot index.
	 * @param string $ad_type_raw Numeric ad-type string from AMP options.
	 * @return array { aa_type, title, raw_data, position_raw }
	 */
	private function resolve_incontent_slot( array $amp, int $i, string $ad_type_raw ): array {
		switch ( $ad_type_raw ) {
			case '1':
				return [
					'adsense',
					sprintf( 'Adsense Ad %d Incontent (Migrated from AMP)', $i ),
					[
						'type'          => 'adsense',
						'client'        => $amp[ 'ampforwp-adsense-ad-data-ad-client-incontent-ad-' . $i ] ?? '',
						'slot'          => $amp[ 'ampforwp-adsense-ad-data-ad-slot-incontent-ad-' . $i ] ?? '',
						'width'         => $amp[ 'ampforwp-adsense-ad-width-incontent-ad-' . $i ] ?? '',
						'height'        => $amp[ 'ampforwp-adsense-ad-height-incontent-ad-' . $i ] ?? '',
						'is_responsive' => false,
					],
					$amp[ 'ampforwp-adsense-ad-position-incontent-ad-' . $i ] ?? '',
				];

			case '2':
				return [
					'plain',
					sprintf( 'DoubleClick Ad %d Incontent (Migrated from AMP)', $i ),
					[
						'type'   => 'doubleclick',
						'slot'   => $amp[ 'ampforwp-doubleclick-ad-data-slot-incontent-ad-' . $i ] ?? '',
						'width'  => $amp[ 'ampforwp-doubleclick-ad-width-incontent-ad-' . $i ] ?? '',
						'height' => $amp[ 'ampforwp-doubleclick-ad-height-incontent-ad-' . $i ] ?? '',
					],
					$amp[ 'ampforwp-doubleclick-ad-position-incontent-ad-' . $i ] ?? '',
				];

			case '3':
				return [
					'plain',
					sprintf( 'Plain Text Ad %d Incontent (Migrated from AMP)', $i ),
					[
						'type' => 'plain',
						'code' => $amp[ 'ampforwp-custom-advertisement-incontent-ad-' . $i ] ?? '',
					],
					$amp[ 'ampforwp-custom-ads-ad-position-incontent-ad-' . $i ] ?? '',
				];

			case '5':
				return [
					'plain',
					sprintf( 'MGID Ad %d Incontent (Migrated from AMP)', $i ),
					[
						'type'      => 'mgid',
						'publisher' => $amp[ 'ampforwp-mgid-ad-Data-Publisher-incontent-ad-' . $i ] ?? '',
						'widget'    => $amp[ 'ampforwp-mgid-ad-Data-Widget-incontent-ad-' . $i ] ?? '',
						'container' => $amp[ 'ampforwp-mgid-ad-Data-Container-incontent-ad-' . $i ] ?? '',
						'width'     => $amp[ 'ampforwp-mgid-ad-width-incontent-ad-' . $i ] ?? '',
						'height'    => $amp[ 'ampforwp-mgid-ad-height-incontent-ad-' . $i ] ?? '',
					],
					$amp[ 'ampforwp-mgid-ad-position-incontent-ad-' . $i ] ?? '',
				];

			default:
				return [ 'plain', sprintf( 'Ad %d Incontent (Migrated from AMP)', $i ), [ 'type' => 'plain', 'code' => '' ], '' ];
		}
	}

	/**
	 * Resolve placement type and paragraph props from a raw in-content position string.
	 *
	 * @param string $position_raw Raw position value from AMP options.
	 * @return array { placement_type, paragraph_props }
	 */
	private function resolve_incontent_position( string $position_raw ): array {
		// Percentage-based positions.
		if ( isset( self::PERCENTAGE_MAP[ $position_raw ] ) ) {
			return [
				'post_content',
				[ 'index' => (int) self::PERCENTAGE_MAP[ $position_raw ], 'position' => 'after' ],
			];
		}

		// Numeric value = paragraph number.
		if ( is_numeric( $position_raw ) ) {
			return [
				'post_content',
				[ 'index' => (int) $position_raw, 'position' => 'after' ],
			];
		}

		// Custom / shortcode falls back to manual.
		if ( 'custom' === $position_raw ) {
			return [ 'manual', [] ];
		}

		// Known position slug.
		$type = self::PLACEMENT_MAP[ $position_raw ] ?? 'manual';

		return [ $type, [] ];
	}

	/**
	 * Resolve ad type label, title, and raw data for a standard ad slot.
	 *
	 * @param array  $amp         AMP options.
	 * @param int    $i           Slot index.
	 * @param string $ad_type_raw Numeric ad-type string from AMP options.
	 * @return array { aa_type, title, raw_data }
	 */
	private function resolve_standard_slot( array $amp, int $i, string $ad_type_raw ): array {
		switch ( $ad_type_raw ) {
			case '1':
				$is_responsive = ! empty( $amp[ 'adsense-rspv-ad-type-standard-' . $i ] );
				return [
					'adsense',
					sprintf( 'Adsense Ad %d General Options (Migrated from AMP)', $i ),
					[
						'type'          => 'adsense',
						'client'        => $amp[ 'ampforwp-adsense-ad-data-ad-client-standard-' . $i ] ?? '',
						'slot'          => $amp[ 'ampforwp-adsense-ad-data-ad-slot-standard-' . $i ] ?? '',
						'width'         => $amp[ 'ampforwp-adsense-ad-width-standard-' . $i ] ?? '',
						'height'        => $amp[ 'ampforwp-adsense-ad-height-standard-' . $i ] ?? '',
						'is_responsive' => $is_responsive,
					],
				];

			case '2':
				return [
					'plain',
					sprintf( 'DoubleClick Ad %d General Options (Migrated from AMP)', $i ),
					[
						'type'   => 'doubleclick',
						'slot'   => $amp[ 'ampforwp-doubleclick-ad-data-slot-standard-' . $i ] ?? '',
						'width'  => $amp[ 'ampforwp-doubleclick-ad-width-standard-' . $i ] ?? '',
						'height' => $amp[ 'ampforwp-doubleclick-ad-height-standard-' . $i ] ?? '',
					],
				];

			case '3':
				return [
					'plain',
					sprintf( 'Ad %d General Options (Migrated from AMP)', $i ),
					[
						'type' => 'plain',
						'code' => $amp[ 'ampforwp-custom-advertisement-standard-' . $i ] ?? '',
					],
				];

			case '5':
				return [
					'plain',
					sprintf( 'MGID Ad %d General Options (Migrated from AMP)', $i ),
					[
						'type'      => 'mgid',
						'publisher' => $amp[ 'ampforwp-mgid-Data-Publisher-standard-' . $i ] ?? '',
						'widget'    => $amp[ 'ampforwp-mgid-Data-Widget-standard-' . $i ] ?? '',
						'container' => $amp[ 'ampforwp-mgid-Data-Container-standard-' . $i ] ?? '',
						'width'     => $amp[ 'ampforwp-mgid-ad-width-standard-' . $i ] ?? '',
						'height'    => $amp[ 'ampforwp-mgid-ad-height-standard-' . $i ] ?? '',
					],
				];

			default:
				return [ 'plain', sprintf( 'Ad %d General Options (Migrated from AMP)', $i ), [ 'type' => 'plain', 'code' => '' ] ];
		}
	}

	/**
	 * Resolve ad type label, title, and raw data for the after-featured-image slot.
	 *
	 * @param array  $amp         AMP options.
	 * @param string $ad_type_raw Numeric ad-type string from AMP options.
	 * @return array { aa_type, title, raw_data }
	 */
	private function resolve_featured_image_slot( array $amp, string $ad_type_raw ): array {
		switch ( $ad_type_raw ) {
			case '1':
				$is_responsive = ! empty( $amp['adsense-rspv-ad-after-featured-img'] );
				return [
					'adsense',
					'Adsense Ad After Featured Image (Migrated from AMP)',
					[
						'type'          => 'adsense',
						'client'        => $amp['ampforwp-after-featured-image-ad-type-1-data-ad-client'] ?? '',
						'slot'          => $amp['ampforwp-after-featured-image-ad-type-1-data-ad-slot'] ?? '',
						'width'         => $amp['ampforwp-after-featured-image-ad-type-1-width'] ?? '',
						'height'        => $amp['ampforwp-after-featured-image-ad-type-1-height'] ?? '',
						'is_responsive' => $is_responsive,
					],
				];

			case '2':
				return [
					'plain',
					'DoubleClick Ad After Featured Image (Migrated from AMP)',
					[
						'type'   => 'doubleclick',
						'slot'   => $amp['ampforwp-after-featured-image-ad-type-2-ad-data-slot'] ?? '',
						'width'  => $amp['ampforwp-after-featured-image-ad-type-2-width'] ?? '',
						'height' => $amp['ampforwp-after-featured-image-ad-type-2-height'] ?? '',
					],
				];

			case '3':
				return [
					'plain',
					'Plain Text Ad After Featured Image (Migrated from AMP)',
					[
						'type' => 'plain',
						'code' => $amp['ampforwp-after-featured-image-ad-custom-advertisement'] ?? '',
					],
				];

			case '5':
				return [
					'plain',
					'MGID Ad After Featured Image (Migrated from AMP)',
					[
						'type'      => 'mgid',
						'publisher' => $amp['ampforwp-after-featured-image-ad-type-5-Data-publisher'] ?? '',
						'widget'    => $amp['ampforwp-after-featured-image-ad-type-5-Data-widget'] ?? '',
						'container' => $amp['ampforwp-after-featured-image-ad-type-5-Data-Container'] ?? '',
						'width'     => $amp['ampforwp-after-featured-image-ad-type-5-width'] ?? '',
						'height'    => $amp['ampforwp-after-featured-image-ad-type-5-height'] ?? '',
					],
				];

			default:
				return [ 'plain', 'Ad After Featured Image (Migrated from AMP)', [ 'type' => 'plain', 'code' => '' ] ];
		}
	}

	// -------------------------------------------------------------------------
	// Ad / Placement creation
	// -------------------------------------------------------------------------

	/**
	 * Create an Advanced Ads ad and (optionally) a placement, then track history.
	 *
	 * @param string $title           Human-readable ad title.
	 * @param string $aa_type         Advanced Ads ad type slug ('adsense', 'plain', 'image').
	 * @param array  $raw_data        Structured data resolved from AMP options.
	 * @param string $placement_type  Advanced Ads placement type slug (or 'manual').
	 * @param array  $visibility      Display / visitor conditions array.
	 * @param array  $paragraph_props Extra placement props for post_content placements.
	 * @param string $history_key     Session key for rollback.
	 * @param int    $count           Running import counter (passed by reference).
	 * @return void
	 */
	private function create_ad_and_placement(
		string $title,
		string $aa_type,
		array $raw_data,
		string $placement_type,
		array $visibility,
		array $paragraph_props,
		string $history_key,
		int &$count
	): void {
		$ad = wp_advads_create_new_ad( $aa_type );
		$ad->set_title( '[AMP] ' . $title );

		// Populate ad content / options by type.
		switch ( $raw_data['type'] ?? '' ) {
			case 'adsense':
				$this->apply_adsense_data( $ad, $raw_data );
				break;

			case 'doubleclick':
				$this->apply_doubleclick_data( $ad, $raw_data );
				break;

			case 'mgid':
				$this->apply_mgid_data( $ad, $raw_data );
				break;

			case 'plain':
			default:
				$ad->set_content( $raw_data['code'] ?? '' );
				break;
		}

		// Display conditions on the ad itself.
		if ( ! empty( $visibility ) ) {
			$ad->set_display_conditions( $visibility );
		}

		$ad_id = $ad->save();

		if ( $ad_id > 0 ) {
			++$count;
			$placement = null;

			if ( 'manual' !== $placement_type ) {
				$placement = $this->create_placement( $ad_id, $title, $placement_type, $paragraph_props );
			}

			$this->add_session_key( $ad, $placement, $history_key );
		}
	}

	/**
	 * Apply AdSense-specific data to an ad object.
	 *
	 * @param object $ad       Advanced Ads ad object.
	 * @param array  $raw_data Resolved AdSense data.
	 * @return void
	 */
	private function apply_adsense_data( $ad, array $raw_data ): void {
		$ad->set_content( wp_json_encode( [ 'slotId' => $raw_data['slot'] ?? '' ] ) );

		$options = $ad->get_prop( 'options', [] );

		if ( ! empty( $raw_data['client'] ) ) {
			$options['adsense_id'] = $raw_data['client'];
		}

		if ( ! empty( $raw_data['width'] ) ) {
			$options['width'] = $raw_data['width'];
		}

		if ( ! empty( $raw_data['height'] ) ) {
			$options['height'] = $raw_data['height'];
		}

		if ( ! empty( $raw_data['is_responsive'] ) ) {
			$options['responsive'] = 1;
		}

		$ad->set_prop( 'options', $options );
	}

	/**
	 * Apply DoubleClick / DFP data as plain HTML to an ad object.
	 *
	 * DoubleClick is not a native Advanced Ads type, so we store it as plain
	 * HTML and preserve the slot / dimensions in options for reference.
	 *
	 * @param object $ad       Advanced Ads ad object.
	 * @param array  $raw_data Resolved DoubleClick data.
	 * @return void
	 */
	private function apply_doubleclick_data( $ad, array $raw_data ): void {
		$slot   = $raw_data['slot'] ?? '';
		$width  = $raw_data['width'] ?? '300';
		$height = $raw_data['height'] ?? '250';

		// Build a minimal amp-ad tag as plain HTML so the slot is not lost.
		$html = sprintf(
			'<!-- DoubleClick slot: %s | %sx%s (imported from AMP for WP) -->',
			esc_html( $slot ),
			esc_html( $width ),
			esc_html( $height )
		);

		$ad->set_content( $html );

		$options                     = $ad->get_prop( 'options', [] );
		$options['doubleclick_slot'] = $slot;
		$options['width']            = $width;
		$options['height']           = $height;
		$ad->set_prop( 'options', $options );
	}

	/**
	 * Apply MGID network data as plain HTML to an ad object.
	 *
	 * @param object $ad       Advanced Ads ad object.
	 * @param array  $raw_data Resolved MGID data.
	 * @return void
	 */
	private function apply_mgid_data( $ad, array $raw_data ): void {
		// Build a minimal comment so no data is silently dropped.
		$html = sprintf(
			'<!-- MGID publisher: %s | widget: %s | container: %s (imported from AMP for WP) -->',
			esc_html( $raw_data['publisher'] ?? '' ),
			esc_html( $raw_data['widget'] ?? '' ),
			esc_html( $raw_data['container'] ?? '' )
		);

		$ad->set_content( $html );

		$options              = $ad->get_prop( 'options', [] );
		$options['publisher'] = $raw_data['publisher'] ?? '';
		$options['widget']    = $raw_data['widget'] ?? '';
		$options['container'] = $raw_data['container'] ?? '';
		$options['width']     = $raw_data['width'] ?? '';
		$options['height']    = $raw_data['height'] ?? '';
		$ad->set_prop( 'options', $options );
	}

	/**
	 * Create an Advanced Ads placement.
	 *
	 * @param int    $ad_id           The ID of the newly-created ad.
	 * @param string $title           Human-readable title for the placement.
	 * @param string $placement_type  Advanced Ads placement type slug.
	 * @param array  $paragraph_props Extra props for post_content placements.
	 * @return object|null
	 */
	private function create_placement( int $ad_id, string $title, string $placement_type, array $paragraph_props ) {
		$placement = wp_advads_create_new_placement( $placement_type );

		if ( ! $placement ) {
			return null;
		}

		$placement->set_title( '[AMP] ' . $title );
		$placement->set_prop( 'item', 'ad_' . $ad_id );

		if ( 'post_content' === $placement_type && ! empty( $paragraph_props ) ) {
			$placement->set_prop( 'index', $paragraph_props['index'] ?? 1 );
			$placement->set_prop( 'position', $paragraph_props['position'] ?? 'after' );
		}

		$placement->save();

		return $placement;
	}

	// -------------------------------------------------------------------------
	// Global options import
	// -------------------------------------------------------------------------

	/**
	 * Import global AMP for WP settings into Advanced Ads options.
	 *
	 * Maps: ad sponsorship label text → Advanced Ads custom ad label.
	 *
	 * @return void
	 */
	private function import_options(): void {
		$amp = $this->get_amp_options();

		if ( empty( $amp ) ) {
			return;
		}

		$aa_general = get_option( 'advanced-ads', [] );

		// Ad sponsorship / label.
		$label_enabled = ! empty( $amp['ampforwp-ads-sponsorship'] );
		$label_text    = $amp['ampforwp-ads-sponsorship-label'] ?? '';

		if ( $label_enabled && ! empty( $label_text ) ) {
			$aa_general['custom-label'] = [
				'enabled'      => 1,
				'text'         => $label_text,
				'html_enabled' => '1',
			];
		}

		update_option( 'advanced-ads', $aa_general );
	}
}
