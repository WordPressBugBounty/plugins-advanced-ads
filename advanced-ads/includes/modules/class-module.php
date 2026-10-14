<?php
/**
 * A Modules-tab card: real (toggleable + conditionally-loaded) or locked/upsell.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Module class.
 */
class Module {

	/**
	 * Required keys.
	 *
	 * @var string[]
	 */
	private const REQUIRED = [ 'id', 'label', 'description', 'icon' ];

	/**
	 * Definition, as passed to the constructor.
	 *
	 * @var array<string, mixed>
	 */
	private $args;

	/**
	 * Module constructor.
	 *
	 * @param array<string, mixed> $args Module definition: id, label, description (strings, or closures returning them), icon
	 *                                   (always required); entry (required unless
	 *                                   is_upgradeable is true); is_upgradeable,
	 *                                   upgrade_url, manual_url, default_active, active_key,
	 *                                   is_hidden (optional).
	 *
	 * @throws RuntimeException When a required key is missing.
	 */
	public function __construct( array $args ) {
		$module_id = $args['id'] ?? '(no id)';

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing, not user-facing/translated, same as Settings\Group::field().
		foreach ( self::REQUIRED as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				throw new RuntimeException(
					sprintf(
						'Module "%s" is missing the required "%s" key.',
						$module_id,
						$key
					)
				);
			}
		}

		if ( empty( $args['is_upgradeable'] ) && empty( $args['entry'] ) ) {
			throw new RuntimeException(
				sprintf(
					'Module "%s" is not upgradeable, so it must declare an "entry" boot class.',
					$module_id
				)
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

		$this->args = $args;
	}

	/**
	 * Get the ID for the module.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->args['id'];
	}

	/**
	 * Get the label for the module.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->resolve( $this->args['label'] );
	}

	/**
	 * Resolve a text given as a closure — modules are registered on `plugins_loaded`, before WordPress allows translations to load, so core texts are translated only when they are read.
	 *
	 * @param string|\Closure $text Text, or a closure returning it.
	 *
	 * @return string
	 */
	private function resolve( $text ): string {
		return (string) ( $text instanceof \Closure ? $text() : $text );
	}

	/**
	 * Get the description for the module.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->resolve( $this->args['description'] );
	}

	/**
	 * Get the icon for the module.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->args['icon'];
	}

	/**
	 * Check if the module is upgradeable.
	 *
	 * @return bool
	 */
	public function is_upgradeable(): bool {
		return (bool) ( $this->args['is_upgradeable'] ?? false );
	}

	/**
	 * Get the upgrade URL for the module.
	 *
	 * @return string|null
	 */
	public function get_upgrade_url(): ?string {
		return $this->args['upgrade_url'] ?? null;
	}

	/**
	 * Manual URL shown on the Modules-tab card, if any.
	 *
	 * @return string|null
	 */
	public function get_manual_url(): ?string {
		return $this->args['manual_url'] ?? null;
	}

	/**
	 * Dot-path key within the shared 'modules' store this module's active state is read/written under. Defaults to the module id.
	 *
	 * @return string
	 */
	public function get_active_key(): string {
		return $this->args['active_key'] ?? $this->args['id'];
	}

	/**
	 * Get the default active state of the module.
	 *
	 * @return bool
	 */
	public function get_default_active(): bool {
		return (bool) ( $this->args['default_active'] ?? true );
	}

	/**
	 * A hidden module has no Modules-tab card and no activate/deactivate toggle — it always boots. For a real module with no reason to ever be user-toggleable (yet).
	 *
	 * @return bool
	 */
	public function is_hidden(): bool {
		return (bool) ( $this->args['is_hidden'] ?? false );
	}

	/**
	 * Class-string implementing Bootable_Interface, or null for a locked/upsell card with nothing to boot.
	 *
	 * @return string|null
	 */
	public function get_entry(): ?string {
		return $this->args['entry'] ?? null;
	}

	/**
	 * Convert to the array shape Settings\Group::module() expects.
	 *
	 * @return array<string, mixed>
	 */
	public function to_field(): array {
		$field = [
			'id'            => $this->get_id(),
			'label'         => $this->get_label(),
			'description'   => $this->get_description(),
			'icon'          => $this->get_icon(),
			'store'         => 'modules',
			'key'           => $this->get_active_key(),
			'isUpgradeable' => $this->is_upgradeable(),
		];

		if ( null !== $this->get_upgrade_url() ) {
			$field['upgrade_url'] = $this->get_upgrade_url();
		}

		if ( null !== $this->get_manual_url() ) {
			$field['manual_url'] = $this->get_manual_url();
		}

		return $field;
	}
}
