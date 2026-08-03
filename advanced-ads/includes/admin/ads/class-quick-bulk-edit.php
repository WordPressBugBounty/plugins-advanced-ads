<?php
/**
 * Add quick/bulk edit fields on the ad overview page
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0
 */

namespace AdvancedAds\Admin\Ads;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Options;
use AdvancedAds\Utilities\WordPress;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * WP integration
 */
class Quick_Bulk_Edit {
	/**
	 * Whether bulk edit already ran this request.
	 *
	 * Core bulk edit fires save_post once per selected post; without this guard
	 * every firing would re-save the entire selection (N²).
	 *
	 * @var bool
	 */
	private static $bulk_edit_ran = false;

	/**
	 * Hooks into WordPress
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'quick_edit_custom_box', [ $this, 'add_quick_edit_fields' ], 10, 2 );
		add_action( 'bulk_edit_custom_box', [ $this, 'add_bulk_edit_fields' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_quick_edits' ], 100 );
		add_action( 'save_post', [ $this, 'save_bulk_edit' ], 100 );
		add_action( 'advanced-ads-ad-render-column-ad_type', [ $this, 'print_ad_json' ] );
	}

	/**
	 * Print ad JSON for the quick-edit JS form filler.
	 *
	 * @param Ad $ad the ad being saved.
	 *
	 * @return void
	 */
	public function print_ad_json( $ad ): void {
		?>
		<script type="text/javascript">
			var ad_json_<?php echo esc_attr( $ad->get_id() ); ?> = <?php echo wp_json_encode( $this->get_json_data( $ad ) ); ?>;
		</script>
		<?php
	}

	/**
	 * Save changes made during bulk edit
	 *
	 * @return void
	 */
	public function save_bulk_edit(): void {
		// Not bulk edit, not ads or not enough permissions.
		if (
			! wp_verify_nonce( sanitize_key( Params::get( '_wpnonce', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ), 'bulk-posts' )
			|| Constants::POST_TYPE_AD !== sanitize_key( Params::get( 'post_type' ) )
			|| ! current_user_can( 'advanced_ads_edit_ads' )
		) {
			return;
		}

		$flags = [ 'on', 'off' ];

		$debug_mode     = Params::get( 'debug_mode' );
		$set_expiry     = Params::get( 'expiry_date' );
		$ad_label       = Params::get( 'ad_label', false );
		$ignore_privacy = Params::get( 'ignore_privacy' );

		$has_change = in_array( $debug_mode, $flags, true )
			|| in_array( $set_expiry, $flags, true )
			|| in_array( $ignore_privacy, $flags, true )
			|| false !== $ad_label;

		/**
		 * Allow add-ons to confirm early abort if no change has been made and avoid iterating through an ad stack.
		 *
		 * @param bool $has_change whether some ads have been changed.
		 */
		$has_change = apply_filters( 'advanced-ads-bulk-edit-has-change', $has_change );

		// No changes, bail out.
		if ( ! $has_change ) {
			return;
		}

		$ads = array_map(
			static function ( $ad ) {
				return wp_advads_get_ad( absint( $ad ) );
			},
			wp_unslash( Params::get( 'post', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) )
		);

		$this->persist_bulk_edit(
			$ads,
			[
				'debug_mode'     => $debug_mode,
				'set_expiry'     => $set_expiry,
				'expiry_date'    => 'on' === $set_expiry ? $this->get_expiry_timestamp( 'get' ) : 0,
				'ad_label'       => $ad_label,
				'ignore_privacy' => $ignore_privacy,
			]
		);
	}

	/**
	 * Persist bulk-edit field changes once per request.
	 *
	 * @param array<int, \AdvancedAds\Abstracts\Ad> $ads     Ad instances.
	 * @param array<string, mixed>                  $changes Parsed change payload.
	 *
	 * @return void
	 */
	private function persist_bulk_edit( array $ads, array $changes ): void {
		if ( self::$bulk_edit_ran ) {
			return;
		}
		self::$bulk_edit_ran = true;

		$flags          = [ 'on', 'off' ];
		$debug_mode     = $changes['debug_mode'] ?? null;
		$set_expiry     = $changes['set_expiry'] ?? null;
		$expiry_date    = (int) ( $changes['expiry_date'] ?? 0 );
		$ad_label       = $changes['ad_label'] ?? false;
		$ignore_privacy = $changes['ignore_privacy'] ?? null;

		foreach ( $ads as $ad ) {
			if ( ! $ad ) {
				continue;
			}

			if ( in_array( $debug_mode, $flags, true ) ) {
				$ad->set_debugmode( 'on' === $debug_mode );
			}

			if ( in_array( $set_expiry, $flags, true ) ) {
				$ad->set_prop( 'expiry_date', $expiry_date );
			}

			if ( false !== $ad_label ) {
				$ad->set_prop( 'ad_label', sanitize_text_field( wp_unslash( $ad_label ) ) );
			}

			if ( 'on' === $ignore_privacy ) {
				$ad->set_prop( 'privacy', [ 'ignore-consent' => 'on' ] );
			} elseif ( 'off' === $ignore_privacy ) {
				$ad->unset_prop( 'privacy' );
			}

			/**
			 * Allow add-on to bulk save ads.
			 *
			 * @param Ad $ad current ad being saved.
			 */
			$ad = apply_filters( 'advanced-ads-bulk-edit-save', $ad );

			$ad->save();
		}
	}

	/**
	 * Save ad edited with quick edit
	 *
	 * @param int $id the ad being saved.
	 *
	 * @return void
	 */
	public function save_quick_edits( $id ): void {
		// Not inline edit, or no permission.
		if (
			! wp_verify_nonce( sanitize_key( Params::post( '_inline_edit' ) ), 'inlineeditnonce' ) ||
			! current_user_can( 'advanced_ads_edit_ads' )
		) {
			return;
		}

		$ad = wp_advads_get_ad( $id );

		// Not an ad.
		if ( ! $ad ) {
			return;
		}

		// Re-register columns for the AJAX list-table response.
		( new List_Table() )->hooks();

		$ad->set_debugmode( Params::post( 'debugmode', false, FILTER_VALIDATE_BOOLEAN ) );
		$ad->set_prop(
			'expiry_date',
			Params::post( 'enable_expiry' ) ? $this->get_expiry_timestamp() : 0
		);

		if ( Options::instance()->get( 'privacy.enabled' ) ) {
			if ( Params::post( 'ignore_privacy' ) ) {
				$ad->set_prop( 'privacy', [ 'ignore-consent' => 'on' ] );
			} else {
				$ad->unset_prop( 'privacy' );
			}
		}

		$ad_label = Params::post( 'ad_label', false );
		if ( false !== $ad_label ) {
			$ad->set_prop( 'ad_label', sanitize_text_field( wp_unslash( $ad_label ) ) );
		}

		/**
		 * Allow add-ons to edit and ad before it is saved.
		 *
		 * @param Ad $ad the ad being saved.
		 */
		$ad = apply_filters( 'advanced-ads-quick-edit-save', $ad );

		$ad->save();
	}

	/**
	 * Get Unix timestamp from the date time inputs values
	 *
	 * @param string $method method used for the form - `post` or `get`.
	 *
	 * @return int
	 */
	private function get_expiry_timestamp( $method = 'post' ): int {
		$day     = absint( 'get' === $method ? Params::get( 'day' ) : Params::post( 'day' ) );
		$month   = absint( 'get' === $method ? Params::get( 'month' ) : Params::post( 'month' ) );
		$year    = 'get' === $method ? Params::get( 'year', 0, FILTER_VALIDATE_INT ) : Params::post( 'year', 0, FILTER_VALIDATE_INT );
		$hours   = absint( 'get' === $method ? Params::get( 'hour' ) : Params::post( 'hour' ) );
		$minutes = absint( 'get' === $method ? Params::get( 'minute' ) : Params::post( 'minute' ) );

		try {
			$local_dt = new DateTimeImmutable( 'now', WordPress::get_timezone() );
			$local_dt = $local_dt->setDate( $year, $month, $day )->setTime( $hours, $minutes );

			return $local_dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Add the bulk edit inputs
	 *
	 * @param string $column_name the current column.
	 * @param string $post_type   the current post type.
	 *
	 * @return void
	 */
	public function add_bulk_edit_fields( $column_name, $post_type ): void {
		if ( Constants::POST_TYPE_AD !== $post_type || 'ad_type' !== $column_name ) {
			return;
		}

		$is_privacy_enabled = (bool) Options::instance()->get( 'privacy.enabled' );
		include ADVADS_ABSPATH . 'views/admin/bulk-edit.php';

		/**
		 * Allow add-ons to add more fields.
		 */
		do_action( 'advanced-ads-bulk-edit-fields' );
	}

	/**
	 * Add the quick edit inputs
	 *
	 * @param string $column_name the current column.
	 * @param string $post_type   the current post type.
	 *
	 * @return void
	 */
	public function add_quick_edit_fields( $column_name, $post_type ): void {
		if ( Constants::POST_TYPE_AD !== $post_type || 'ad_date' !== $column_name ) {
			return;
		}

		$is_privacy_enabled = (bool) Options::instance()->get( 'privacy.enabled' );
		include ADVADS_ABSPATH . 'views/admin/quick-edit.php';

		/**
		 * Allow add-ons to add more fields.
		 */
		do_action( 'advanced-ads-quick-edit-fields' );
	}

	/**
	 * Print date and time inputs for the ad expiry
	 *
	 * @param int    $timestamp default expiry date.
	 * @param string $prefix    prefix for input names.
	 *
	 * @return void
	 */
	public static function print_date_time_inputs( $timestamp = 0, $prefix = '' ): void {
		global $wp_locale;

		try {
			$initial_date = $timestamp ? new DateTimeImmutable( "@$timestamp", new DateTimeZone( 'UTC' ) ) : current_datetime();
		} catch ( Exception $e ) {
			$initial_date = current_datetime();
		}

		$current_year = (int) current_datetime()->format( 'Y' );
		$expiry_year  = (int) $initial_date->format( 'Y' );
		$start_year   = min( $current_year, $expiry_year );
		$end_year     = max( $current_year + 10, $expiry_year );

		?>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Month', 'advanced-ads' ); ?></span>
			<select name="<?php echo esc_attr( $prefix ); ?>month">
				<?php for ( $mo = 1; $mo < 13; $mo++ ) : ?>
					<?php $month = zeroise( $mo, 2 ); ?>
					<option value="<?php echo esc_attr( $month ); ?>" <?php selected( $month, $initial_date->format( 'm' ) ); ?>>
						<?php echo esc_html( $month . '-' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $mo, 2 ) ) ); ?>
					</option>
				<?php endfor; ?>
			</select>
		</label>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Day', 'advanced-ads' ); ?></span>
			<input type="number" name="<?php echo esc_attr( $prefix ); ?>day" min="1" max="31" value="<?php echo esc_attr( $initial_date->format( 'd' ) ); ?>"/>
		</label>,
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Year', 'advanced-ads' ); ?></span>
			<select name="<?php echo esc_attr( $prefix ); ?>year">
				<?php for ( $y = $start_year; $y <= $end_year; $y++ ) : ?>
					<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $y, $expiry_year ); ?>><?php echo esc_html( $y ); ?></option>
				<?php endfor; ?>
			</select>
		</label>
		@
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Hour', 'advanced-ads' ); ?></span>
			<input type="number" name="<?php echo esc_attr( $prefix ); ?>hour" min="0" max="23" value="<?php echo esc_attr( $initial_date->format( 'H' ) ); ?>"/>
		</label>:
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Minute', 'advanced-ads' ); ?></span>
			<input type="number" name="<?php echo esc_attr( $prefix ); ?>minute" min="0" max="59" value="<?php echo esc_attr( $initial_date->format( 'i' ) ); ?>"/>
		</label>
		<?php
		$timezone = wp_timezone_string();
		echo esc_html( false !== strpbrk( $timezone, '+-' ) ? "UTC{$timezone}" : $timezone );
	}

	/**
	 * Get ad data for json output
	 *
	 * @param Ad $ad Ad instance.
	 *
	 * @return array<string, mixed>
	 */
	private function get_json_data( $ad ): array {
		$expiry = $ad->get_expiry_date();

		$ad_data = [
			'debug_mode' => $ad->is_debug_mode(),
			'expiry'     => $expiry
				? [
					'expires'     => true,
					'expiry_date' => array_combine(
						[ 'year', 'month', 'day', 'hour', 'minute' ],
						explode( '-', wp_date( 'Y-m-d-H-i', $expiry ) )
					),
				]
				: [
					'expires' => false,
				],
			'ad_label'   => $ad->get_prop( 'ad_label' ),
		];

		if ( Options::instance()->get( 'privacy.enabled' ) ) {
			$ad_data['ignore_privacy'] = isset( $ad->get_data()['privacy']['ignore-consent'] );
		}

		/**
		 * Allow add-ons to add more ad data fields.
		 *
		 * @param array $ad_data the fields to be sent back to the browser.
		 * @param Ad    $ad      the ad being currently edited.
		 */
		return apply_filters( 'advanced-ads-quick-edit-ad-data', $ad_data, $ad );
	}
}
