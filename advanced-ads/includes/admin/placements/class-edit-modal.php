<?php
/**
 * Placement Edit Modal.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin\Placements;

use AdvancedAds\Abstracts\Placement;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Modal;
use AdvancedAds\Utilities\Content_Injection;
use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Placement Edit Modal.
 */
class Edit_Modal {

	/**
	 * Hold placement
	 *
	 * @var Placement
	 */
	private $placement = null;

	/**
	 * View path
	 *
	 * @var string
	 */
	private $view_path = null;

	/**
	 * The constructor
	 *
	 * @param Placement $placement Placement instance.
	 */
	public function __construct( $placement ) {
		$this->placement = $placement;
		$this->view_path = ADVADS_ABSPATH . 'views/admin/placements/edit-modal';
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
	}

	/**
	 * Load the modal for creating a new Placement.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		Modal::create(
			[
				'modal_slug'    => 'placement-edit-' . $this->placement->get_id(),
				'modal_content' => $this->render_modal_content(),
				/* translators: 1: "Options", 2: the name of a placement. */
				'modal_title'   => sprintf( '%1$s: %2$s', __( 'Options', 'advanced-ads' ), $this->placement->get_title() ),
			]
		);
	}

	/**
	 * Get form id
	 *
	 * @return string
	 */
	private function get_form_id(): string {
		return 'advanced-ads-placement-form-' . $this->placement->get_id();
	}

	/**
	 * Render the placement modal content as if it was the single edit page for a post.
	 *
	 * @return string
	 */
	private function render_modal_content(): string {
		ob_start();
		$placement = $this->placement;

		include $this->view_path . '/edit-modal-content.php';

		return ob_get_clean();
	}

	/**
	 * Render settings.
	 *
	 * @return void
	 */
	private function render_settings(): void {
		WordPress::render_option( 'placement-name', __( 'Name', 'advanced-ads' ), $this->render_field( 'name.php' ) );
		WordPress::render_option( 'placement-item', __( 'Item', 'advanced-ads' ), $this->render_field( 'item.php' ) );
		WordPress::render_option( 'placement-status', __( 'Status', 'advanced-ads' ), $this->render_field( 'status.php' ) );

		if ( $this->placement->is_type( 'post_content' ) ) {
			WordPress::render_option( 'placement-content-injection-index', __( 'position', 'advanced-ads' ), $this->render_placement_position() );
		}

		$slug = $this->placement->get_slug();

		/**
		 * Hook before advanced placement options
		 *
		 * @param string    $slug      the placement slug.
		 * @param Placement $placement the placement.
		 */
		do_action( 'advanced-ads-placement-options-before-advanced', $slug, $this->placement );

		$type_options = $this->placement->get_type_object()->get_options();

		if ( ! $this->placement->is_type( 'header' ) ) {
			if ( $type_options['placement-ad-label'] ?? true ) {
				WordPress::render_option( 'placement-ad-label', __( 'ad label', 'advanced-ads' ), $this->render_placement_ad_label() );
			}

			if ( ! empty( $type_options['show_position'] ) ) {
				WordPress::render_option( 'placement-position', __( 'Position', 'advanced-ads' ), $this->render_placement_label_position() );
			}

			WordPress::render_option( 'placement-inline-css', __( 'Inline CSS', 'advanced-ads' ), $this->render_placement_inline_css() );

			if ( ! defined( 'AAP_VERSION' ) ) {
				WordPress::render_option(
					'placement-content-minimum-length',
					__( 'Minimum Content Length', 'advanced-ads' ),
					$this->render_pro_pitch(),
					__( 'Minimum length of content before automatically injected ads are allowed in them.', 'advanced-ads' )
				);
				WordPress::render_option(
					'placement-skip-paragraph',
					__( 'Words Between Ads', 'advanced-ads' ),
					$this->render_pro_pitch(),
					__( 'A minimum amount of words between automatically injected ads.', 'advanced-ads' )
				);
			}
		}

		if ( ! defined( 'AAP_VERSION' ) ) {
			WordPress::render_option(
				'placement-display-conditions',
				__( 'Display Conditions', 'advanced-ads' ),
				$this->render_pro_pitch(),
				__( 'Use display conditions for placements.', 'advanced-ads' ) . ' ' . __( 'The free version provides conditions on the ad edit page.', 'advanced-ads' )
			);
			WordPress::render_option(
				'placement-visitor-conditions',
				__( 'Visitor Conditions', 'advanced-ads' ),
				$this->render_pro_pitch(),
				__( 'Use visitor conditions for placements.', 'advanced-ads' ) . ' ' . __( 'The free version provides conditions on the ad edit page.', 'advanced-ads' )
			);
		}

		/**
		 * Hook after advanced placement options
		 *
		 * @param string    $slug      the placement slug.
		 * @param Placement $placement the placement.
		 */
		do_action( 'advanced-ads-placement-options-after-advanced', $slug, $this->placement );
	}

	/**
	 * Render a field view that only needs `$placement`.
	 *
	 * @param string $file Field view filename under fields/.
	 *
	 * @return string
	 */
	private function render_field( string $file ): string {
		$placement = $this->placement;

		ob_start();
		include $this->view_path . '/fields/' . $file;

		return ob_get_clean();
	}

	/**
	 * Render placement ad label option.
	 *
	 * @return string
	 */
	private function render_placement_ad_label(): string {
		$label = $this->placement->get_prop( 'ad_label' ) ?? 'default';

		ob_start();
		include $this->view_path . '/fields/ad-label.php';

		return ob_get_clean();
	}

	/**
	 * Render the placement label position option.
	 *
	 * @return string
	 */
	private function render_placement_label_position(): string {
		$position = $this->placement->get_prop( 'placement_position' ) ?? 'default';
		$clearfix = ! empty( $this->placement->get_prop( 'placement_clearfix' ) );

		ob_start();
		include $this->view_path . '/fields/ad-label-position.php';

		return ob_get_clean();
	}

	/**
	 * Render the placement inline CSS option.
	 *
	 * @return string
	 */
	private function render_placement_inline_css(): string {
		$placement      = $this->placement;
		$placement_slug = $this->placement->get_slug();
		$inline_css     = $this->placement->get_prop( 'inline-css' ) ?? '';

		ob_start();
		include $this->view_path . '/fields/inline-css.php';

		return ob_get_clean();
	}

	/**
	 * Return `is_pro_pitch` for the options that are pitching the pro version — WordPress::render_option() handles this string as a special case.
	 *
	 * @return string
	 */
	private function render_pro_pitch(): string {
		return 'is_pro_pitch';
	}

	/**
	 * Render the placement position
	 *
	 * @return string
	 */
	public function render_placement_position() {
		$placement    = $this->placement;
		$index        = max( 1, (int) ( $placement->get_prop( 'index' ) ?? 1 ) );
		$tags         = Content_Injection::get_tags();
		$selected_tag = $placement->get_prop( 'tag' ) ?? 'p';

		// Automatically select the 'custom' option.
		if ( Params::cookie( 'advads_frontend_picker', '' ) === $placement->get_slug() ) {
			$selected_tag = 'custom';
		}
		$xpath             = stripslashes( $placement->get_prop( 'xpath' ) ?? '' );
		$positions         = [
			'after'  => __( 'after', 'advanced-ads' ),
			'before' => __( 'before', 'advanced-ads' ),
		];
		$selected_position = $placement->get_prop( 'position' ) ?? 'after';
		$start_from_bottom = $placement->get_prop( 'start_from_bottom' );

		ob_start();
		include $this->view_path . '/fields/content-index.php';
		if ( ! defined( 'AAP_VERSION' ) ) {
			include ADVADS_ABSPATH . 'admin/views/upgrades/repeat-the-position.php';
		}

		do_action( 'advanced-ads-placement-post-content-position', $placement->get_slug(), $placement );

		if ( ! extension_loaded( 'dom' ) ) :
			?>
			<p>
				<span class="advads-notice-inline advads-error">
					<?php esc_html_e( 'Important Notice', 'advanced-ads' ); ?>:
				</span>
				<?php
				printf(
					/* translators: %s is a name of a module. */
					esc_html__( 'Missing PHP extension could cause issues. Please ask your hosting provider to enable it: %s', 'advanced-ads' ),
					'dom (php_xml)'
				);
				?>
			</p>
			<?php
		endif;

		return ob_get_clean();
	}
}
