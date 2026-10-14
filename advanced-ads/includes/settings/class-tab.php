<?php
/**
 * A settings tab: an ordered collection of top-level Groups, identified by a unique id — owns its own groups directly, the same way Group owns its children, so a caller holds onto the Tab instance returned by Registry::tab() and calls ->group() directly on it to keep adding to the same tab across multiple statements (e.g. an add-on plugin resuming a tab shell another file already registered, via the advanced-ads-register-settings action). Groups are stored keyed by their own id, so resuming/looking one up is a direct array access rather than a scan.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tab class.
 */
class Tab {

	/**
	 * Tab id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Tab label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Tab icon (lucide icon name).
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * Sort order among tabs.
	 *
	 * @var int
	 */
	private $order;

	/**
	 * Top-level Group instances, keyed by group id, in registration order.
	 *
	 * @var array<string, Group>
	 */
	private $groups = [];

	/**
	 * The registry this tab belongs to, if any — passed through to every Group this tab creates. See Group's own $registry for what it's for.
	 *
	 * @var Registry|null
	 */
	private $registry;

	/**
	 * Tab constructor.
	 *
	 * @param string               $id       Tab id.
	 * @param string               $label    Tab label.
	 * @param array<string, mixed> $args     Optional: icon, order.
	 * @param Registry|null        $registry Optional: the registry that created this tab (see Group's $registry).
	 */
	public function __construct( string $id, string $label, array $args = [], ?Registry $registry = null ) {
		$this->id       = $id;
		$this->label    = $label;
		$this->icon     = $args['icon'] ?? '';
		$this->order    = $args['order'] ?? 10;
		$this->registry = $registry;
	}

	/**
	 * This tab's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Start (or resume) a top-level group within this tab — renders as a titled card in the UI. Hold onto the returned Group and call ->field()/->group() directly on it to keep adding to the same group across multiple statements (e.g. inside a conditional block).
	 *
	 * @param string               $id   Group id.
	 * @param array<string, mixed> $args Optional: label, description, manual_url, effect, dependencies, layout.
	 *
	 * @return Group
	 */
	public function group( string $id, array $args = [] ): Group {
		if ( isset( $this->groups[ $id ] ) ) {
			return $this->groups[ $id ];
		}

		$group               = new Group( $id, $args, $this->registry );
		$this->groups[ $id ] = $group;

		return $group;
	}

	/**
	 * Look up a top-level group of this tab by id — unlike group(), doesn't create one if it isn't registered yet.
	 *
	 * @param string $id Group id.
	 *
	 * @return Group|null
	 */
	public function get_group( string $id ): ?Group {
		return $this->groups[ $id ] ?? null;
	}

	/**
	 * Call $callback for every leaf field in this tab's groups, recursing into nested groups.
	 *
	 * @param callable $callback Called with each leaf field array.
	 *
	 * @return void
	 */
	public function each_field( callable $callback ): void {
		foreach ( $this->groups as $group ) {
			$group->each_field( $callback );
		}
	}

	/**
	 * Serialize this tab (and its groups, recursively) to the schema shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'key'      => $this->id,
			'label'    => $this->label,
			'icon'     => $this->icon,
			'order'    => $this->order,
			'children' => array_map(
				static fn( Group $group ) => $group->to_array(),
				array_values( $this->groups )
			),
		];
	}
}
