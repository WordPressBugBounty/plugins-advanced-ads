<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Manages Advanced Ads plugin updating notifications on the Plugins screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Advanced_Ads_Plugins_Screen_Updates
 */
class Advanced_Ads_Plugins_Screen_Updates {

	/**
	 * The upgrade notice shown inline.
	 *
	 * @var string
	 */
	protected $upgrade_notice = '';

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $current_version = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->current_version = defined( 'ADVADS_VERSION' ) ? ADVADS_VERSION : '0.0.0';

		add_action( "in_plugin_update_message-advanced-ads/advanced-ads.php", array( $this, 'in_plugin_update_message' ), 20, 2 );
	}

	/**
	 * Show plugin changes on the plugins screen.
	 *
	 * @param array    $args     Unused parameter.
	 * @param stdClass $response Plugin update response.
	 */
	public function in_plugin_update_message( $args, $response ) {
		$new_version = $response->new_version;

		if ( version_compare( $this->current_version, $new_version, '>=' ) ) {
			return;
		}

		$this->upgrade_notice = $this->get_upgrade_notice( $new_version );

		if ( empty( $this->upgrade_notice ) ) {
			return;
		}

		echo '</p>';
		?>
		<div id="advads-update-notice-<?php echo esc_attr( $new_version ); ?>" style="display: flex; align-items: flex-start; padding: 1em 0; margin-top: .5em; border-top: 1px solid #dba617">
			<span class="dashicons dashicons-info" style="color: #ffba00; margin-right: 10px; flex-shrink: 0;"></span>
			<div>
				<strong style="display: block; margin-bottom: 4px;"><?php esc_html_e( 'Heads up!', 'advanced-ads' ); ?></strong>
				<div style="line-height: 1.5; color: #3c434a;">
					<?php echo wp_kses_post( $this->upgrade_notice ); ?>
				</div>
			</div>
		</div>
		<?php

		echo '<p class="dummy" style="display:none;">';
	}

	/**
	 * Get the upgrade notice from WordPress.org.
	 *
	 * @param string $version The version to check.
	 * @return string
	 */
	protected function get_upgrade_notice( $version ) {
		$transient_name = 'advads_upgrade_notice_' . $version;
		$upgrade_notice = get_transient( $transient_name );

		if ( false === $upgrade_notice ) {
			$response = wp_safe_remote_get( 'https://plugins.svn.wordpress.org/advanced-ads/trunk/readme.txt' );

			if ( ! is_wp_error( $response ) && ! empty( $response['body'] ) ) {
				$upgrade_notice = $this->parse_update_notice( $response['body'], $version );
				set_transient( $transient_name, $upgrade_notice, DAY_IN_SECONDS );
			}
		}

		return $upgrade_notice;
	}

	/**
	 * Parse update notice from readme file.
	 *
	 * @param string $content     Readme file content.
	 * @param string $new_version New version.
	 * @return string
	 */
	private function parse_update_notice( $content, $new_version ) {
		$version_parts     = explode( '.', $new_version );
		$check_for_notices = array(
			$version_parts[0] . '.0', // Major.
			$version_parts[0] . '.0.0', // Major.
			$version_parts[0] . '.' . $version_parts[1], // Minor.
			$version_parts[0] . '.' . $version_parts[1] . '.' . $version_parts[2], // Patch.
		);

		$notice_regexp  = '~==\s*Upgrade Notice\s*==\s*=\s*(.*)\s*=(.*)(?===|$)~Uis';
		$upgrade_notice = '';

		foreach ( $check_for_notices as $check_version ) {
			if ( version_compare( $this->current_version, $check_version, '>=' ) ) {
				continue;
			}

			$matches = null;
			if ( preg_match( $notice_regexp, $content, $matches ) ) {
				if ( version_compare( trim( $matches[1] ), $check_version, '=' ) ) {
					$notices = (array) preg_split( '~[\r\n]+~', trim( $matches[2] ) );

					foreach ( $notices as $line ) {
						$upgrade_notice .= preg_replace( '~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $line ) . ' ';
					}

					break;
				}
			}
		}

		return trim( $upgrade_notice );
	}
}
