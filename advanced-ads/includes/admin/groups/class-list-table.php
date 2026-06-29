<?php
/**
 * Admin Groups List Table.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds\Admin\Groups;

use stdClass;
use AdvancedAds\Modal;
use WP_Terms_List_Table;
use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Group;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Admin\Upgrades;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Groups List Table.
 */
class List_Table extends WP_Terms_List_Table {
	/**
	 * Missing type error.
	 *
	 * @var string
	 */
	private $type_error = '';

	/**
	 * Array with all ads.
	 *
	 * @var array<int, string>
	 */
	private $all_ads = [];

	/**
	 * Precomputed schedule details for addable ads in the group edit modal.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $ad_schedule_details = [];

	/**
	 * Hydrated groups for the current page, keyed by term ID.
	 *
	 * @var array<int, Group>
	 */
	private $groups = [];

	/**
	 * Construct the current list table.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'default_hidden_columns', [ $this, 'default_hidden_columns' ] );

		$this->prepare_items();
		$this->_actions = [];
		$this->all_ads  = wp_advads_get_ads_dropdown();
		$this->prime_addable_ad_schedules();
	}

	/**
	 * Batch-load schedule details for the add-ad dropdown once per request.
	 *
	 * @return void
	 */
	private function prime_addable_ad_schedules(): void {
		if ( empty( $this->all_ads ) ) {
			return;
		}

		foreach ( wp_advads_get_ads_by_ids( array_keys( $this->all_ads ) ) as $ad_id => $ad ) {
			$this->ad_schedule_details[ $ad_id ] = $ad->get_ad_schedule_details();
		}
	}

	/**
	 * Prepare list items from cached group summaries instead of get_terms().
	 *
	 * @return void
	 */
	public function prepare_items() {
		$taxonomy = $this->screen->taxonomy;
		$per_page = (int) $this->get_items_per_page( "edit_{$taxonomy}_per_page" );
		$search   = ! empty( $_REQUEST['s'] ) ? trim( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby  = Params::request( 'orderby', 'name' );
		$order    = Params::request( 'order', 'ASC' );
		$summaries = $this->filter_group_summaries( wp_advads_get_group_summaries(), $search );

		$summaries = $this->sort_group_summaries( $summaries, $orderby, $order );

		$total  = count( $summaries );
		$offset = ( $this->get_pagenum() - 1 ) * $per_page;
		$page   = array_slice( $summaries, $offset, $per_page, true );

		$this->items = [];
		foreach ( $page as $summary ) {
			$term            = new stdClass();
			$term->term_id   = $summary['id'];
			$term->name      = $summary['title'];
			$term->taxonomy  = Constants::TAXONOMY_GROUP;
			$this->items[]   = $term;
		}

		$this->groups = wp_advads_get_groups_by_ids( array_keys( $page ) );

		$this->callback_args = [
			'number'  => $per_page,
			'offset'  => $offset,
			'orderby' => $orderby,
		];

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
			]
		);
	}

	/**
	 * Filter group summaries by type and search query.
	 *
	 * @param array<int, array<string, mixed>> $summaries Group summaries.
	 * @param string                           $search    Search query.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_group_summaries( array $summaries, $search ): array {
		$group_type = Params::get( 'group_type' );

		if ( $group_type ) {
			$summaries = array_filter(
				$summaries,
				static function ( $summary ) use ( $group_type ) {
					return $group_type === $summary['type'];
				}
			);
		}

		if ( '' !== $search ) {
			$summaries = array_filter(
				$summaries,
				static function ( $summary ) use ( $search ) {
					return false !== stripos( $summary['title'], $search );
				}
			);
		}

		return $summaries;
	}

	/**
	 * Sort group summaries for the list table.
	 *
	 * @param array<int, array<string, mixed>> $summaries Group summaries.
	 * @param string                           $orderby   Sort column.
	 * @param string                           $order     Sort direction.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_group_summaries( array $summaries, $orderby, $order ): array {
		$desc = 'DESC' === strtoupper( $order );

		uasort(
			$summaries,
			static function ( $left, $right ) use ( $orderby, $desc ) {
				switch ( $orderby ) {
					case 'date':
						$result = strcmp( $left['modified_date'] ?? '', $right['modified_date'] ?? '' );
						break;
					case 'details':
						$result = $left['id'] <=> $right['id'];
						break;
					default:
						$result = strcasecmp( $left['title'], $right['title'] );
				}

				return $desc ? -$result : $result;
			}
		);

		return $summaries;
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 *
	 * @since 3.1.0
	 *
	 * @param string $which The location: 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			include_once ADVADS_ABSPATH . 'views/admin/groups/filters.php';
		}
	}

	/**
	 * No groups found
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No Ad Group found', 'advanced-ads' );
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return [
			'type'    => __( 'Type', 'advanced-ads' ),
			'name'    => _x( 'Name', 'term name', 'advanced-ads' ),
			'details' => __( 'Details', 'advanced-ads' ),
			'ads'     => __( 'Ads', 'advanced-ads' ),
			'date'    => __( 'Date', 'advanced-ads' ),
		];
	}

	/**
	 * Hidden columns
	 *
	 * @param string[] $hidden Column list.
	 *
	 * @return array
	 */
	public function default_hidden_columns( $hidden ): array {
		$hidden[] = 'date';
		return $hidden;
	}

	/**
	 * Sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return [
			'date'    => 'date',
			'name'    => 'name',
			'details' => 'details',
		];
	}


	/**
	 * Render single row.
	 *
	 * @param \WP_Term|object $term  Term object.
	 * @param int             $level Depth level.
	 *
	 * @return void
	 */
	public function single_row( $term, $level = 0 ): void {
		$this->type_error = '';
		$group            = $this->groups[ $term->term_id ] ?? null;

		if ( ! $group ) {
			return;
		}

		// Set the group to behave as default, if the original type is not available.
		$group_type = $group->get_type_object();
		if ( $group_type->is_premium() ) {
			$this->type_error = sprintf(
			/* translators: %s is the group type string */
				__( 'The originally selected group type “%s” is not enabled.', 'advanced-ads' ),
				$group_type->get_title()
			);
		}

		echo '<tr id="tag-' . $group->get_id() . '" class="' . $level . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->single_row_columns( $group );
		echo '</tr>';
	}

	/**
	 * Column type
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	public function column_type( $group ): void {
		include ADVADS_ABSPATH . 'views/admin/groups/columns/type.php';
	}

	/**
	 * Column name
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	public function column_name( $group ): void {
		$this->render_edit_modal( $group );
		$this->render_usage_modal( $group );
		include ADVADS_ABSPATH . 'views/admin/groups/columns/name.php';
	}

	/**
	 * Column details
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	public function column_details( $group ): void {
		include ADVADS_ABSPATH . 'views/admin/groups/columns/details.php';
	}

	/**
	 * Column ads
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	public function column_ads( $group ): void {
		$template = empty( $group->get_ad_weights() ) ? 'list-row-loop-none.php' : 'list-row-loop.php';

		include ADVADS_ABSPATH . 'views/admin/groups/columns/' . $template;
	}

	/**
	 * Column date
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	public function column_date( $group ) {
		$publish_date  = $group->get_publish_date();
		$modified_date = $group->get_modified_date();

		if ( ! $publish_date && ! $modified_date ) {
			return;
		}

		$date_time_regex = get_option( 'date_format' ) . ' \\a\\t ' . get_option( 'time_format' );
		$date_prefix     = $publish_date === $modified_date ? __( 'Published', 'advanced-ads' ) : __( 'Last Modified', 'advanced-ads' );
		$date_to_show    = get_date_from_gmt( $publish_date === $modified_date ? $publish_date : $modified_date, $date_time_regex );

		echo esc_html( $date_prefix ) . '<br>' . esc_html( $date_to_show );
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @param Group  $group Group instance.
	 * @param string $column_name Column name.
	 * @param string $primary     Primary column name.
	 *
	 * @return string
	 */
	protected function handle_row_actions( $group, $column_name, $primary ): string {
		global $tax;

		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = [];

		if ( ! $this->type_error && current_user_can( $tax->cap->edit_terms ) ) {
			$actions['edit'] = '<a href="#modal-group-edit-' . $group->get_id() . '"
								class="edits">' . esc_html__( 'Edit', 'advanced-ads' ) . '</a>';

			// duplicate group upgrade link.
			if ( ! defined( 'AAP_VERSION' ) ) {
				$actions['duplicate-group'] = ( new Upgrades() )->create_duplicate_link();
			}
		}

		$actions['usage'] = '<a href="#modal-group-usage-' . $group->get_id() . '" class="edits">' . esc_html__( 'Show Usage', 'advanced-ads' ) . '</a>';

		if ( current_user_can( $tax->cap->delete_terms ) ) {
			$actions['delete'] = sprintf(
				'<a class="delete-tag" href="%s">%s</a>',
				wp_nonce_url(
					add_query_arg(
						[
							'action'   => 'group',
							'action2'  => 'delete',
							'group_id' => $group->get_id(),
							'page'     => 'advanced-ads-groups',
						],
						admin_url( 'admin.php' )
					),
					'delete-tag_' . $group->get_id()
				),
				esc_html__( 'Delete', 'advanced-ads' )
			);
		}

		$actions = apply_filters( Constants::TAXONOMY_GROUP . '_row_actions', $actions, $group );

		return $this->row_actions( $actions );
	}

	/**
	 * Render edit form modal
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	private function render_edit_modal( $group ): void {
		ob_start();
		require ADVADS_ABSPATH . 'views/admin/groups/edit-modal/form.php';
		$modal_content = ob_get_clean();

		Modal::create(
			[
				'modal_slug'    => 'group-edit-' . $group->get_id(),
				'modal_content' => $modal_content,
				'modal_title'   => sprintf( '%s %s', __( 'Edit', 'advanced-ads' ), $group->get_name() ),
			]
		);
	}

	/**
	 * Render usage form modal
	 *
	 * @param Group $group Group instance.
	 *
	 * @return void
	 */
	private function render_usage_modal( $group ): void {
		ob_start();
		include ADVADS_ABSPATH . 'views/admin/groups/columns/usage.php';
		$modal_content = ob_get_clean();

		Modal::create(
			[
				'modal_slug'    => 'group-usage-' . $group->get_id(),
				'modal_content' => $modal_content,
				'modal_title'   => __( 'Usage', 'advanced-ads' ),
				'cancel_action' => false,
				'close_action'  => __( 'Close', 'advanced-ads' ),
			]
		);
	}
}
