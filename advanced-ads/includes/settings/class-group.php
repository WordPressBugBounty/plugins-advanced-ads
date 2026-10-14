<?php
/**
 * A settings group: fields and nested groups.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Settings;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Settings group with per-type field shortcuts.
 *
 * @phpstan-type SettingsField array<string, mixed>
 *
 * @method self toggle(SettingsField $field) Add a toggle field.
 * @method self checkbox(SettingsField $field) Add a checkbox field.
 * @method self checkbox_grid(SettingsField $field) Add a checkbox-grid field.
 * @method self select(SettingsField $field) Add a select field.
 * @method self text(SettingsField $field) Add a text field.
 * @method self number(SettingsField $field) Add a number field.
 * @method self textarea(SettingsField $field) Add a textarea field.
 * @method self radio_group(SettingsField $field) Add a radio-group field.
 * @method self radio_inline(SettingsField $field) Add a radio-inline field.
 * @method self button(SettingsField $field) Add a button field.
 * @method self module(SettingsField $field) Add a module card field (Modules tab). Expects an `isUpgradeable` bool: false for a real, toggleable module; true for a locked/upsell card (optionally with an `upgrade_url`).
 * @method self raw(SettingsField $field) Add a raw field: no input, renders `value_callback`'s return (an HTML string) as-is. `store` must still name an already-registered store (Store::get() returns null, never calling value_callback, for an unregistered one) — the value itself is typically ignored by the callback.
 * @method self token_field(SettingsField $field) Add a token field (tag-style input): value is an array of strings; optional `suggestions` array for autocomplete.
 */
class Group {

	/**
	 * Magic method name => field `type` value — the method name can't contain a hyphen (PHP identifier rules), the type value can and, for the multi-word types, does; the frontend CONTROLS map keys on the hyphenated form.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_TYPES = [
		'toggle'        => 'toggle',
		'checkbox'      => 'checkbox',
		'checkbox_grid' => 'checkbox-grid',
		'select'        => 'select',
		'text'          => 'text',
		'number'        => 'number',
		'textarea'      => 'textarea',
		'radio_group'   => 'radio-group',
		'radio_inline'  => 'radio-inline',
		'button'        => 'button',
		'module'        => 'module',
		'raw'           => 'raw',
		'token_field'   => 'token-field',
	];

	/**
	 * Allowed HTML in field `note` (schema output only). React renders this via dangerouslySetInnerHTML.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const NOTE_ALLOWED_HTML = [
		'a'      => [
			'href'   => true,
			'target' => true,
		],
		'em'     => [],
		'strong' => [],
		'code'   => [],
		'br'     => [],
		'p'      => [],
	];

	/**
	 * Group id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Group label. Optional — a group with no label is valid (e.g. renders with no visible caption/heading); omit the 'label' key in $args.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Optional extra schema keys: description, manual_url, effect, dependencies, layout.
	 *
	 * @var array<string, mixed>
	 */
	private $args;

	/**
	 * Ordered children: leaf field arrays and/or nested Group instances.
	 *
	 * @var array<int, array<string, mixed>|Group>
	 */
	private $children = [];

	/**
	 * Registry that created this group; field() calls invalidate_cache() on it so the field list stays current.
	 *
	 * @var Registry|null
	 */
	private $registry;

	/**
	 * Group constructor.
	 *
	 * @param string               $id       Group id.
	 * @param array<string, mixed> $args     Optional: label, description, manual_url, effect, dependencies, layout.
	 * @param Registry|null        $registry Optional: the registry that created this group.
	 */
	public function __construct( string $id, array $args = [], ?Registry $registry = null ) {
		$this->id    = $id;
		$this->label = $args['label'] ?? '';
		unset( $args['label'] );
		$this->args     = $args;
		$this->registry = $registry;
	}

	/**
	 * Resolve a per-type shortcut (->toggle([...]), ->select([...]), ...) — see the @method block above and FIELD_TYPES for the full list.
	 *
	 * @param string            $name      Method name called.
	 * @param array<int, mixed> $arguments Call arguments — expects exactly one, the field array.
	 *
	 * @throws RuntimeException When $name isn't a known field type.
	 *
	 * @return self
	 */
	public function __call( string $name, array $arguments ): self {
		if ( ! isset( self::FIELD_TYPES[ $name ] ) ) {
			// A developer-facing programming error (unknown method call), never
			// shown to an end user — not translated, same as this class's other
			// RuntimeExceptions.
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException(
				sprintf(
					'Unknown field type "%s". Valid types: %s.',
					$name,
					implode( ', ', array_keys( self::FIELD_TYPES ) )
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		/** @var array<string, mixed> $field */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
		$field         = is_array( $arguments[0] ?? null ) ? $arguments[0] : [];
		$field['type'] = self::FIELD_TYPES[ $name ];

		return $this->field( $field );
	}

	/**
	 * This group's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Add a leaf field to this group.
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @throws RuntimeException When a required key is missing, `type` isn't
	 *                          a known field type, or this registry already has the id.
	 *
	 * @return self
	 */
	public function field( array $field ): self {
		$field_id = $field['id'] ?? '(no id)';

		foreach ( [ 'id', 'type', 'store' ] as $required ) {
			if ( ! isset( $field[ $required ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing, not user-facing/translated, see __call() above.
				throw new RuntimeException(
					sprintf(
						'Field "%s" is missing the required "%s" key.',
						$field_id,
						$required
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		$field['key'] = $field['key'] ?? $field['id'];

		if ( ! in_array( $field['type'], self::FIELD_TYPES, true ) ) {
			$field_type = $field['type'] ?? '(no type)';
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException(
				sprintf(
					'Field "%s" has unknown type "%s". Valid types: %s.',
					$field_id,
					$field_type,
					implode( ', ', self::FIELD_TYPES )
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( null !== $this->registry && null !== $this->registry->get_field( $field['id'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing, not user-facing/translated, see __call() above.
			throw new RuntimeException(
				sprintf(
					'Field id "%s" is already registered.',
					$field['id']
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->children[] = $field;
		if ( null !== $this->registry ) {
			$this->registry->invalidate_cache();
		}

		return $this;
	}

	/**
	 * Start (or resume) a group nested inside this one.
	 *
	 * @param string               $id   Group id.
	 * @param array<string, mixed> $args Optional: label, description, manual_url, effect, dependencies, layout.
	 *
	 * @return Group The nested group, for chaining further ->field()/->group() calls onto it.
	 */
	public function group( string $id, array $args = [] ): Group {
		foreach ( $this->children as $child ) {
			if ( $child instanceof self && $child->id === $id ) {
				return $child;
			}
		}

		$group            = new self( $id, $args, $this->registry );
		$this->children[] = $group;

		return $group;
	}

	/**
	 * Call $callback for every leaf field in this group, recursing into nested groups.
	 *
	 * @param callable $callback Called with each leaf field array.
	 *
	 * @return void
	 */
	public function each_field( callable $callback ): void {
		foreach ( $this->children as $child ) {
			if ( $child instanceof self ) {
				$child->each_field( $callback );
			} else {
				$callback( $child );
			}
		}
	}

	/**
	 * Serialize this group (and its children, recursively) to the schema shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array_merge(
			$this->args,
			[
				'type'     => 'group',
				'id'       => $this->id,
				'label'    => $this->label,
				'children' => array_map(
					static function ( $child ) {
						if ( $child instanceof self ) {
							return $child->to_array();
						}

						$field = $child;
						unset( $field['sanitize_callback'], $field['value_callback'] );

						if ( isset( $field['note'] ) && is_string( $field['note'] ) ) {
							$field['note'] = wp_kses( $field['note'], self::NOTE_ALLOWED_HTML );
						}

						return $field;
					},
					$this->children
				),
			]
		);
	}
}
