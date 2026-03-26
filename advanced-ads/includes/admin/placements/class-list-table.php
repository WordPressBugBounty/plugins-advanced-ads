<?php
/**
 * Placement List Table.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.49.0
 */

namespace AdvancedAds\Admin\Placements;

use WP_Query;
use AdvancedAds\Modal;
use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Admin_List_Table;
use AdvancedAds\Framework\Utilities\Params;

defined( 'ABSPATH' ) || exit;

/**
 * Placement List Table.
 */
class List_Table extends Admin_List_Table {

	/**
	 * Object being shown on the row.
	 *
	 * @var Placement|null
	 */
	protected $object = null;

	/**
	 * Object type.
	 *
	 * @var string
	 */
	protected $object_type = 'placement';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $list_table_type = Constants::POST_TYPE_PLACEMENT;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		parent::hooks();

		add_action( 'views_edit-' . $this->list_table_type, [ $this, 'display_views' ] );

		// Manage rows and columns.
		add_filter( 'list_table_primary_column', [ $this, 'set_primary_column' ], 10, 0 );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ] );

		// Filters.
		add_filter( 'disable_months_dropdown', '__return_true' );
	}

	/**
	 * Define hidden columns.
	 *
	 * @return array
	 */
	protected function define_hidden_columns(): array {
		return [ 'id', 'title' ];
	}

	/**
	 * Define which columns to show on this screen.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function define_columns( $columns ): array {
		return [
			'cb'         => $columns['cb'],
			'type'       => __( 'Type', 'advanced-ads' ),
			'title'      => __( 'Title', 'advanced-ads' ),
			'name'       => __( 'Name', 'advanced-ads' ),
			'ad_group'   => sprintf( '%1$s / %2$s', __( 'Ad', 'advanced-ads' ), __( 'Group', 'advanced-ads' ) ),
			'conditions' => __( 'Delivery', 'advanced-ads' ),
		];
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function define_sortable_columns( $columns ): array {
		$columns['type'] = 'type';
		$columns['name'] = 'title';

		return $columns;
	}

	/**
	 * Pre-fetch any data for the row each column has access to it.
	 *
	 * @param int $post_id Post ID being shown.
	 *
	 * @return void
	 */
	protected function prepare_row_data( $post_id ): void {
		if ( empty( $this->object ) || $this->object->get_id() !== $post_id ) {
			$this->object = wp_advads_get_placement( $post_id );
		}
	}

	/**
	 * Set the primary column.
	 *
	 * @return string
	 */
	public function set_primary_column(): string {
		return 'name';
	}

	/**
	 * Displays the list of views available for Placements.
	 *
	 * @param string[] $views An array of available list table views.
	 *
	 * @return array
	 */
	public function display_views( $views ): array {
		global $wp_list_table;

		$wp_list_table->screen->render_screen_reader_content( 'heading_views' );

		$is_all = count(
			array_diff_key(
				$_GET, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				[
					'post_type' => $this->list_table_type,
					'orderby'   => '',
					'order'     => '',
					'paged'     => '',
					'mode'      => '',
				]
			)
		) === 0;

		include_once ADVADS_ABSPATH . 'views/admin/table-views-list.php';

		return [];
	}

	/**
	 * Filter the row actions for placements.
	 *
	 * @param array $actions Array of actions.
	 *
	 * @return array
	 */
	public function row_actions( array $actions ): array {
		unset( $actions['inline hide-if-no-js'] );

		$actions['edit'] = '<a href="#modal-placement-edit-' . $this->object->get_id() . '" data-dialog="modal-placement-edit-' . $this->object->get_id() . '">' . esc_html__( 'Edit', 'advanced-ads' ) . '</a>';

		if ( $this->object->is_type( 'default' ) ) {
			$actions['usage'] = '<a href="#modal-placement-usage-' . $this->object->get_id() . '" class="edits">' . esc_html__( 'Show Usage', 'advanced-ads' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Order ads by title on ads list
	 *
	 * @param array $query_vars Query vars.
	 *
	 * @return array
	 */
	protected function query_filters( $query_vars ): array {
		// Early bail!!
		if ( wp_doing_ajax() ) {
			return $query_vars;
		}

		// Filter by type.
		$placement_type = sanitize_text_field( Params::get( 'placement-type', '' ) );
		if ( '' !== $placement_type ) {
			$query_vars['meta_key']   = 'type'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_vars['meta_value'] = $placement_type; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		// Sort by type.
		$order   = Params::get( 'order', 'asc' );
		$orderby = Params::get( 'orderby', 'type' );
		if ( in_array( $orderby, [ 'type' ], true ) ) {
			$query_vars['order'] = $order;

			if ( 'type' === $orderby ) {
				$query_vars['meta_key'] = 'type'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_vars['orderby']  = 'meta_value';
				add_filter( 'posts_orderby', [ $this, 'sort_by_type_order' ], 10, 2 );
			}
		}

		return $query_vars;
	}

	/**
	 * Set the ORDER BY clause of the query.
	 *
	 * @param string   $order_sql The ORDER BY clause of the query.
	 * @param WP_Query $wp_query  The current query instance.
	 *
	 * @return string
	 */
	public function sort_by_type_order( string $order_sql, WP_Query $wp_query ): string {
		global $wpdb;

		// Early bail!!
		if ( ! $wp_query->is_main_query() ) {
			return $order_sql;
		}

		$order = strtoupper( Params::get( 'order', 'asc' ) ) === 'DESC' ? 'DESC' : 'ASC';
		$types_order = wp_advads_get_placement_type_manager()->get_types();
		$types_order = array_keys( $types_order );

		$order_strings = [
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				sprintf(
					'FIELD(%s.meta_value, %s ) %s',
					$wpdb->postmeta,
					implode( ', ', array_fill( 0, count( $types_order ), '%s' ) ),
					$order // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				),
				$types_order
			),
			"{$wpdb->posts}.post_title {$order}",
		];

		return implode( ', ', $order_strings );
	}

	/**
	 * Add placement type filter to the placements list.
	 *
	 * @return void
	 */
	public function render_filters(): void {
		$current_type = Params::get( 'placement-type', '' );

		include_once ADVADS_ABSPATH . 'views/admin/placements/filters.php';
	}

	/**
	 * Render the Type column.
	 *
	 * @return void
	 */
	protected function render_type_column(): void {
		$placement = $this->object;
		( new Edit_Modal( $placement ) )->hooks();
		$this->render_usage_modal();

		require ADVADS_ABSPATH . 'views/admin/placements/columns/type.php';
	}

	/**
	 * Render the Name column.
	 *
	 * @return void
	 */
	protected function render_name_column(): void {
		$placement = $this->object;

		require ADVADS_ABSPATH . 'views/admin/placements/columns/name.php';
	}

	/**
	 * Render the Ad/Group column.
	 *
	 * @return void
	 */
	protected function render_ad_group_column(): void {
		$placement = $this->object;

		require ADVADS_ABSPATH . 'views/admin/placements/columns/ad-group.php';

		if ( $this->object->is_type( 'header' ) ) {
			require ADVADS_ABSPATH . 'views/admin/placements/columns/header-note.php';
		}
	}

	/**
	 * Render the Conditions column.
	 *
	 * @return void
	 */
	protected function render_conditions_column(): void {
		$placement = $this->object;

		require ADVADS_ABSPATH . 'views/admin/placements/columns/conditions.php';
	}

	/**
	 * Render usage form modal
	 *
	 * @return void
	 */
	private function render_usage_modal(): void {
		if ( ! $this->object->is_type( 'default' ) ) {
			return;
		}

		ob_start();
		$placement = $this->object;
		require ADVADS_ABSPATH . 'views/admin/placements/columns/usage.php';
		$modal_content = ob_get_clean();

		Modal::create(
			[
				'modal_slug'    => 'placement-usage-' . $placement->get_id(),
				'modal_content' => $modal_content,
				'modal_title'   => __( 'Usage', 'advanced-ads' ),
				'cancel_action' => false,
				'close_action'  => __( 'Close', 'advanced-ads' ),
			]
		);
	}
}
