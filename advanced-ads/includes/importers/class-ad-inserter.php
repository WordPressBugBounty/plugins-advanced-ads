<?php
/**
 * Ad Inserter.
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
 * Ad Inserter.
 */
class Ad_Inserter extends Importer implements Interface_Importer {

	/**
	 * Hold AI options
	 *
	 * @var array
	 */
	private $ai_options = null;

	/**
	 * Mapping of Ad Inserter alignment types to Advanced Ads output settings.
	 *
	 * AI Types: 1=Left, 2=Right, 3=Center, 4=Float Left, 5=Float Right
	 */
	private const ALIGNMENT_MAP = [
		0 => [ 'position' => 'default', 'float' => 0 ],
		2 => [ 'position' => 'right', 'float' => 0 ],
		3 => [ 'position' => 'center', 'float' => 0 ],
		4 => [ 'position' => 'left', 'float' => 1 ],
		5 => [ 'position' => 'right', 'float' => 1 ],
	];

	/**
	 *  Mapping of Ad Inserter special placement settings.
	 *
	 * AI uses special keys for global header, footer, and manual code blocks: [h], [f], and [a] respectively.
	 */
	private const SPECIAL_PLACEMENT_MAP = [
		'h' => [ 'title' => 'Global Header', 'type' => 'header' ],
		'f' => [ 'title' => 'Global Footer', 'type' => 'footer' ],
		'a' => [ 'title' => 'Global Manual Code', 'type' => 'default' ],
	];

	/**
	 * Mapping of Ad Inserter placement types to Advanced Ads output settings.
	 *
	 * AI Types: 1=Post Top, 2=Post Bottom, 3=Post Top (after title), 4=Post Bottom (before meta), 5=Paragraph (before), 6=Paragraph (after)
	 */
	private const PLACEMENT_TYPE_MAP = [
		1 => [ 'type' => 'post_top' ],
		3 => [ 'type' => 'post_top' ],
		2 => [ 'type' => 'post_bottom' ],
		4 => [ 'type' => 'post_bottom' ],
		5 => [ 'type' => 'post_content', 'position' => 'before' ],
		6 => [ 'type' => 'post_content', 'position' => 'after' ],
	];

	/**
	 * Mapping of Ad Inserter viewport settings to Advanced Ads output settings.
	 */
	private const VIEWPORT_MAP = [
		'detect_viewport_1' => 'desktop',
		'detect_viewport_2' => 'tablet',
		'detect_viewport_3' => 'mobile',
	];

	/**
	 * Mapping of Ad Inserter page type settings to Advanced Ads output settings.
	 */
	private const PAGE_TYPE_MAP = [
		'enable_posts'    => 'is_single',
		'enable_pages'    => 'is_page',
		'enable_homepage' => 'is_front_page',
		'enable_category' => 'is_archive',
	];

	/**
	 * Get the unique identifier (ID) of the importer.
	 *
	 * @return string The unique ID of the importer.
	 */
	public function get_id(): string {
		return 'ad_inserter';
	}

	/**
	 * Get the title or name of the importer.
	 *
	 * @return string The title of the importer.
	 */
	public function get_title(): string {
		return __( 'Ad Inserter', 'advanced-ads' );
	}

	/**
	 * Get a description of the importer.
	 *
	 * @return string The description of the importer.
	 */
	public function get_description(): string {
		return __( 'Migrate Ad Inserter blocks. Automatically detects AdSense, Images, and HTML blocks.', 'advanced-ads' );
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
		$options = $this->get_ai_options();

		return ! empty( $options );
	}

	/**
	 * Render form.
	 *
	 * @return void
	 */
	public function render_form(): void {
		$blocks = $this->get_ai_options();
		unset( $blocks['global'], $blocks['extract'] );
		?>
		<p class="text-base m-0">
			<?php
			/* translators: %d: number of ad blocks */
			printf( __( 'We found Ad Inserter configuration with <strong>%d ad blocks</strong>.', 'advanced-ads' ), count( $blocks ) ); // phpcs:ignore
			?>
		</p>
		<p><label><input type="checkbox" name="ai_import[blocks]" checked="checked" /> <?php esc_html_e( 'Import Ad Blocks', 'advanced-ads' ); ?></label></p>
		<p><label><input type="checkbox" name="ai_import[options]" checked="checked" /> <?php esc_html_e( 'Import Settings', 'advanced-ads' ); ?></label></p>
		<?php
	}

	/**
	 * Import data.
	 *
	 * @return \WP_Error|string
	 */
	public function import() {
		$count       = 0;
		$what        = Params::post( 'ai_import', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$history_key = $this->generate_history_key();

		if ( isset( $what['blocks'] ) ) {
			$count = $this->process_import( $history_key );
		}

		if ( isset( $what['options'] ) ) {
			$this->import_options( $history_key );
		}

		wp_advads()->importers->add_session_history( $this->get_id(), $history_key, $count );
		/* translators: 1: counts 2: Importer title */
		return sprintf( esc_html__( '%1$d ads migrated from %2$s', 'advanced-ads' ), $count, $this->get_title() );
	}

	/**
	 * Import all global options from Ad Inserter to Advanced Ads.
	 * Maps data across 4 different Advanced Ads option tables.
	 *
	 * @param string $history_key Session key for rollback.
	 * @return void
	 */
	private function import_options( $history_key ): void {
		$ai_options = $this->get_ai_options();
		if ( empty( $ai_options ) || ! isset( $ai_options['global'] ) ) {
			return;
		}

		$global                        = $ai_options['global'];
		$aa_settings                   = get_option( 'advanced-ads-settings', [] );
		$aa_settings['minimum_role']   = $global['MINIMUM_USER_ROLE'] ?? 'administrator';
		$aa_settings['show_admin_bar'] = (int) ( $global['ADMIN_TOOLBAR_DEBUGGING'] ?? 0 );

		$aa_settings['responsive'] = [
			'tablet_width' => (int) ( $global['VIEWPORT_WIDTH_2'] ?? 768 ),
			'mobile_width' => (int) ( $global['VIEWPORT_WIDTH_3'] ?? 480 ),
		];

		if ( isset( $global['OUTPUT_BUFFERING'] ) ) {
			$aa_settings['output_buffering'] = (int) $global['OUTPUT_BUFFERING'];
		}

		if ( isset( $global['ADB_ACTION'] ) && (int) $global['ADB_ACTION'] > 0 ) {
			$aa_settings['ad_blocker_detection'] = 1;
			// 1 = Message/Popup, 2 = Redirect
			$aa_settings['ad_blocker_detection_type'] = 2 === absint( $global['ADB_ACTION'] ) ? 'redirect' : 'popup';
		}

		update_option( 'advanced-ads-settings', $aa_settings );

		$aa_pro = get_option( 'advanced-ads-pro', [] );

		if ( isset( $global['DYNAMIC_BLOCKS'] ) && (int) $global['DYNAMIC_BLOCKS'] > 0 ) {
			$aa_pro['cache-busting']['enabled']             = '1';
			$aa_pro['cache-busting']['default_auto_method'] = 2 === absint( $global['DYNAMIC_BLOCKS'] ) ? 'passive' : 'ajax';
		}

		$aa_pro['cfp'] = [
			'click_limit'       => (string) ( $global['CLICK_FRAUD_PROTECTION_TIME'] ?? '3' ),
			'cookie_expiration' => '24',
			'ban_duration'      => '7',
		];

		if ( isset( $global['LAZY_LOADING_OFFSET'] ) ) {
			$aa_pro['lazy-load']['offset'] = (string) $global['LAZY_LOADING_OFFSET'];
		}

		if ( isset( $global['MAX_PAGE_BLOCKS'] ) ) {
			$aa_pro['advanced-visitor-conditions']['enabled'] = 1;
			$aa_pro['max-ads-per-page']                       = (int) $global['MAX_PAGE_BLOCKS'];
		}

		update_option( 'advanced-ads-pro', $aa_pro );

		if ( isset( $global['ADB_ACTION'] ) && (int) $global['ADB_ACTION'] > 0 ) {
			$aa_adb                                  = get_option( 'advanced-ads-adblocker', [] );
			$aa_adb['ads-for-adblockers']['enabled'] = $global['ADB_ACTION'];

			$aa_adb['method']  = ( 2 === (int) $global['ADB_ACTION'] ) ? 'redirect' : 'overlay';
			$aa_adb['overlay'] = [
				'content'          => $aa_adb['overlay']['content'] ?? 'Ads blocked by ad blocker',
				'time_frequency'   => ( (int) ( $global['ADB_NO_ACTION_PERIOD'] ?? 0 ) > 0 ) ? 'once' : 'everytime',
				'container_style'  => $global['ADB_MESSAGE_CSS'] ?? '',
				'background_style' => $global['ADB_OVERLAY_CSS'] ?? '',
				'hide_dismiss'     => $global['ADB_UNDISMISSIBLE_MESSAGE'] ?? 0,
				'dismiss_text'     => '',
				'dismiss_style'    => '',
			];

			if ( (int) ( $global['ADB_ACTION'] ?? 0 ) === 2 && ! empty( $global['ADB_CUSTOM_REDIRECTION_URL'] ?? '' ) ) {
				$aa_adb['redirect']['url'] = $global['ADB_CUSTOM_REDIRECTION_URL'];
			}

			update_option( 'advanced-ads-adblocker', $aa_adb );
		}

		$aa_general = get_option( 'advanced-ads', [] );

		if ( ! empty( $global['AD_LABEL'] ) ) {
			$aa_general['custom-label'] = [
				'enabled'      => empty( $global['AD_LABEL'] ) ? 0 : 1,
				'text'         => $global['AD_LABEL'],
				'html_enabled' => '1',
			];
		}

		if ( ! empty( $global['BLOCK_CLASS_NAME'] ) ) {
			$aa_general['front-prefix'] = $global['BLOCK_CLASS_NAME'] . '-';
		}

		if ( ! empty( $global['DISABLE_BLOCK_INSERTIONS'] ) ) {
			$aa_general['disabled-ads']['all'] = 1;
		}

		update_option( 'advanced-ads', $aa_general );
		$this->import_special_ai_blocks( $ai_options, $history_key );
	}

	/**
	 * Imports special AI containers [h] Header, [f] Footer, and [a] Manual as Ads.
	 *
	 * @param array  $ai_options ad inserter options.
	 * @param string $history_key Session key for rollback.
	 */
	private function import_special_ai_blocks( $ai_options, $history_key ) {
		$dummy_count = 0;
		foreach ( self::SPECIAL_PLACEMENT_MAP as $key => $meta ) {
			if ( ! empty( $ai_options[ $key ]['code'] ) ) {
				$block         = $ai_options[ $key ];
				$block['name'] = $meta['title'];

				// Reuse create_ad logic with a forced placement type.
				$this->create_ad( $block, $ai_options, $history_key, $dummy_count, $meta['type'] );
			}
		}
	}

	/**
	 * Return Ad Inserter block and option array
	 *
	 * @return array|bool
	 */
	private function get_ai_options() {
		if ( null !== $this->ai_options ) {
			return $this->ai_options;
		}

		$this->ai_options = get_option( 'ad_inserter' );
		if ( false === $this->ai_options ) {
			return $this->ai_options;
		}

		if ( is_string( $this->ai_options ) && substr( $this->ai_options, 0, 4 ) === ':AI:' ) {
			$this->ai_options = unserialize( base64_decode( substr( $this->ai_options, 4 ), true ) ); // phpcs:ignore
			$this->ai_options = array_filter( $this->ai_options );
		}

		return $this->ai_options;
	}

	/**
	 * Import ads
	 *
	 * @param string $history_key Session key for rollback.
	 *
	 * @return int
	 */
	private function process_import( $history_key ): int {
		$count  = 0;
		$blocks = $this->get_ai_options();
		if ( ! is_array( $blocks ) ) {
			return 0;
		}

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['code'] ) ) {
				continue;
			}
			$this->create_ad( $block, $blocks, $history_key, $count );
		}
		return $count;
	}


	/**
	 * Detect if the code is AdSense, a single Image, or Plain HTML.
	 *
	 * @param string $code ad type.
	 *
	 * @return string
	 */
	private function detect_ad_type( $code ): string {
		if ( stripos( $code, 'adsbygoogle' ) !== false ) {
			return 'adsense';
		}

		// Simple check for Image-only blocks (matches <img> or <a><img></a>).
		if ( preg_match( '/^<a[^>]*><img[^>]*><\/a>$|^<img[^>]*>$/i', $code ) ) {
			return 'image';
		}

		return 'plain';
	}

	/**
	 * Extracts AdSense Slot and Client ID and passes them to the AA AdSense Type.
	 *
	 * @param object $ad ad.
	 * @param string $code type ad.
	 *
	 * @return void
	 */
	private function map_adsense_data( $ad, $code ) {
		$ad->set_content( $code );

		// Extract Slot ID.
		preg_match( '/data-ad-slot=["\'](\d+)["\']/', $code, $slot_matches );
		// Extract Client ID.
		preg_match( '/data-ad-client=["\'](ca-pub-\d+)["\']/', $code, $client_matches );

		$args = [];
		if ( ! empty( $client_matches[1] ) ) {
			$args['adsense_id'] = $client_matches[1];
		}
		if ( ! empty( $slot_matches[1] ) ) {
			$args['slot_id'] = $slot_matches[1];
		}

		// Set these in the Ad options so the UI fields are populated.
		if ( ! empty( $args ) ) {
			$options = $ad->get_prop( 'options', [] );
			$ad->set_prop( 'options', array_merge( $options, $args ) );
		}
	}

	/**
	 * Attempts to find an attachment ID for an image block.
	 *
	 * @param object $ad ad.
	 * @param string $code type ad.
	 *
	 * @return void
	 */
	private function map_image_data( $ad, $code ) {
		preg_match( '/src=["\']([^"\']+)["\']/', $code, $matches );

		if ( ! empty( $matches[1] ) ) {
			$image_url = $matches[1];
			global $wpdb;
			$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_type = 'attachment'", $image_url ) );

			if ( $attachment_id ) {
				$output = $ad->get_prop( 'output', [] );
				$ad->set_image_id( $attachment_id );
				$ad->set_prop( 'output', $output );
				return;
			}
		}

		$ad->set_type( 'plain' );
		$ad->set_content( $code );
	}

	/**
	 * Set ad alignment
	 *
	 * @param object $ad ad.
	 * @param array  $block alignment settings.
	 *
	 * @return void
	 */
	private function apply_alignment( $ad, $block ) {
		$align              = (int) ( $block['alignment_type'] ?? 0 );
		$output             = $ad->get_prop( 'output', [] );
		$mapping            = self::ALIGNMENT_MAP[ $align ] ?? self::ALIGNMENT_MAP[0];
		$output['position'] = $mapping['position'];
		$output['float']    = $mapping['float'];

		$ad->set_prop( 'output', $output );
	}

	/**
	 * Create an Advanced Ads placement based on Ad Inserter's display settings.
	 *
	 * @param int    $ad_id       The ID of the migrated ad to be assigned to this placement.
	 * @param string $name        The name/title of the ad block.
	 * @param array  $block       The Ad Inserter block configuration array.
	 * @param string $history_key The unique session key for history tracking and rollbacks.
	 * @param string $forced_type Optional custom placement type (e.g., 'head', 'footer').
	 *
	 * @return \AdvancedAds\Framework\Objects\Placement|null
	 */
	private function create_placement( $ad_id, $name, $block, $history_key, $forced_type = '' ) {

		// Initialize default placement variables.
		$type  = 'manual';
		$props = [ 'item' => 'ad_' . $ad_id ];

		if ( ! empty( $forced_type ) ) {
			$type = $forced_type;
		} else {
			$ai_type = (int) $block['display_type'] ?? 0;
			if ( isset( self::PLACEMENT_TYPE_MAP[ $ai_type ] ) ) {
				$config = self::PLACEMENT_TYPE_MAP[ $ai_type ];
				$type   = $config['type'];

				if ( 'post_content' === $type ) {
					$props['index']    = (int) ( $block['paragraph_number'] ?? 1 );
					$props['position'] = $config['position'];
				}
			} else {
				$type = 'default';
			}
		}

		$placement = wp_advads_create_new_placement( $type );

		if ( $placement ) {
			$placement->set_title( '[AI] ' . $name );

			foreach ( $props as $key => $val ) {
				$placement->set_prop( $key, $val );
			}

			$display_conditions = $this->parse_display_conditions( $block );
			if ( ! empty( $display_conditions ) ) {
				$placement->set_display_conditions( $display_conditions );
			}

			$placement->save();
		}

		return $placement;
	}

	/**
	 * Parse Ad Inserter viewport detection settings and convert them into Advanced Ads visitor conditions.
	 *
	 * @param array $block configuration settings.
	 *
	 * @return array visitor condition settings
	 */
	private function parse_visitor_conditions( $block ): array {
		$devices = [];

		// Ad Inserter Viewport Mapping: 1 = Desktop, 2 = Tablet, 3 = Mobile.
		foreach ( self::VIEWPORT_MAP as $key => $device ) {
			if ( ! empty( $block[ $key ] ) ) {
				$devices[] = $device;
			}
		}

		// Only return a condition if some devices are restricted.
		// If all 3 are checked or none are checked, no specific visitor condition is needed.
		if ( ! empty( $devices ) && count( $devices ) < 3 ) {
			return [
				[
					'type'     => 'device',
					'operator' => 'is',
					'value'    => $devices,
				],
			];
		}

		return [];
	}

	/**
	 * Map Ad Inserter page visibility settings to Advanced Ads display conditions.
	 *
	 * @param  array $block block configuration.
	 * @return array 'general' display condition.
	 */
	private function parse_display_conditions( $block ): array {
		$enabled = [];
		foreach ( self::PAGE_TYPE_MAP as $ai_key => $aa_val ) {
			if ( ! empty( $block[ $ai_key ] ) ) {
				$enabled[] = $aa_val;
			}
		}

		// If specific page types are enabled, wrap them in a 'general' condition type.
		if ( ! empty( $enabled ) ) {
			return [
				[
					'type'     => 'general',
					'operator' => 'is',
					'value'    => $enabled,
				],
			];
		}

		return [];
	}

	/**
	 *  Create Ad and Placement
	 *
	 * @param array  $block          ad data.
	 * @param array  $ai_options     ad inserter options.
	 * @param string $history_key    Session key for rollback.
	 * @param int    $count          migrated ads count.
	 * @param string $forced_type    Optional custom placement type (e.g., 'head', 'footer').
	 */
	private function create_ad( $block, $ai_options, $history_key, &$count, $forced_type = '' ) {
		$ad_code = trim( $block['code'] );
		$ad_type = $this->detect_ad_type( $ad_code );
		$ad      = wp_advads_create_new_ad( $ad_type );
		$name    = ! empty( $block['name'] ) ? $block['name'] : 'Block ' . $count;
		$ad->set_title( '[AI] ' . $name );

		if ( 'adsense' === $ad_type ) {
			$this->map_adsense_data( $ad, $ad_code );
		} elseif ( 'image' === $ad_type ) {
			$this->map_image_data( $ad, $ad_code );
		} else {
			$ad->set_content( $block['code'] );
		}

		// Handle 404 condition for special blocks.
		if ( ! empty( $block['enable_404'] ) ) {
			$ad->set_display_conditions(
				[
					[
						'type'     => 'general',
						'operator' => 'is',
						'value'    => [ 'is_404' ],
					],
				]
			);
		}

		$this->apply_alignment( $ad, $block );
		$visitor_conditions = $this->parse_visitor_conditions( $block );
		if ( ! empty( $visitor_conditions ) ) {
			$ad->set_visitor_conditions( $visitor_conditions );
		}

		$ad_id = $ad->save();

		if ( $ad_id > 0 ) {
			++$count;
			$placement = null;

			// Create placement if a type is forced OR if the block has an automatic display type.
			if ( ! empty( $forced_type ) || ( ! empty( $block['display_type'] ) && 0 !== (int) $block['display_type'] ) ) {
				$placement = $this->create_placement( $ad_id, $name, $block, $history_key, $forced_type );
			}

			if ( $ad ) {
				$this->add_session_key( $ad, $placement, $history_key );
			}
		}
	}
}
