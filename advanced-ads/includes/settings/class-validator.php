<?php
/**
 * Server-side rules validation and generic type-based sanitizing for settings fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Validator class.
 */
class Validator {

	/**
	 * Validate a value against a field's `rules`.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Submitted value.
	 *
	 * @return string|null Error message, or null if valid.
	 */
	public function validate( array $field, $value ): ?string {
		$rules = $field['rules'] ?? [];

		if ( ! empty( $rules['required'] ) && ( '' === $value || null === $value ) ) {
			return __( 'This field is required.', 'advanced-ads' );
		}

		$needs_numeric = 'number' === ( $field['type'] ?? '' ) || isset( $rules['min'] ) || isset( $rules['max'] );
		if ( $needs_numeric && '' !== $value && null !== $value && ! is_numeric( $value ) ) {
			return __( 'Must be a number.', 'advanced-ads' );
		}

		if ( isset( $rules['min'] ) && is_numeric( $value ) && (float) $value < $rules['min'] ) {
			/* translators: %s: minimum allowed value. */
			return sprintf( __( 'Must be at least %s.', 'advanced-ads' ), $rules['min'] );
		}

		if ( isset( $rules['max'] ) && is_numeric( $value ) && (float) $value > $rules['max'] ) {
			/* translators: %s: maximum allowed value. */
			return sprintf( __( 'Must be at most %s.', 'advanced-ads' ), $rules['max'] );
		}

		if ( isset( $rules['minLength'] ) && is_string( $value ) && strlen( $value ) < $rules['minLength'] ) {
			/* translators: %d: minimum number of characters. */
			return sprintf( __( 'Must be at least %d characters.', 'advanced-ads' ), $rules['minLength'] );
		}

		if ( isset( $rules['maxLength'] ) && is_string( $value ) && strlen( $value ) > $rules['maxLength'] ) {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'Must be at most %d characters.', 'advanced-ads' ), $rules['maxLength'] );
		}

		if ( isset( $rules['pattern'] ) && is_string( $value ) && '' !== $value && ! $this->value_matches_pattern( $value, (string) $rules['pattern'] ) ) {
			return __( 'Invalid format.', 'advanced-ads' );
		}

		$allowed = $this->option_values( $field );
		if ( null !== $allowed && '' !== $value && null !== $value && ! is_array( $value ) ) {
			if ( ! in_array( (string) $value, $allowed, true ) ) {
				return __( 'Invalid option.', 'advanced-ads' );
			}
		}

		return null;
	}

	/**
	 * Sanitize a value: custom `sanitize_callback` if present, else a generic type-based default.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Submitted value.
	 *
	 * @return mixed
	 */
	public function sanitize( array $field, $value ) {
		$value = $this->restrict_checkbox_grid_keys( $field, $value );

		if ( $this->is_string_field( $field ) ) {
			$value = $this->to_scalar_string( $value );
		}

		if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return ( $field['sanitize_callback'] )( $value );
		}

		switch ( $field['type'] ) {
			case 'toggle':
			case 'checkbox':
			case 'module':
				return (bool) $value;

			case 'number':
				return is_numeric( $value ) ? $value + 0 : 0;

			case 'checkbox-grid':
				return is_array( $value ) ? array_map( 'boolval', $value ) : [];

			case 'token-field':
				return is_array( $value ) ? array_values( array_map( 'sanitize_text_field', $value ) ) : [];

			case 'textarea':
				return sanitize_textarea_field( $this->to_scalar_string( $value ) );

			default:
				return sanitize_text_field( trim( $this->to_scalar_string( $value ) ) );
		}
	}

	/**
	 * Whether the field stores a scalar string.
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return bool
	 */
	private function is_string_field( array $field ): bool {
		return in_array(
			$field['type'] ?? '',
			[ 'text', 'select', 'radio-group', 'radio-inline', 'textarea' ],
			true
		);
	}

	/**
	 * Coerce a value to a string. Arrays and objects become empty — they must not be stored.
	 *
	 * @param mixed $value Submitted value.
	 *
	 * @return string
	 */
	private function to_scalar_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Allowed option values as strings, from `options` or `rules.options`.
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return array<int, string>|null Null when the field has no option list.
	 */
	private function option_values( array $field ): ?array {
		$options = $field['options'] ?? $field['rules']['options'] ?? null;
		if ( ! is_array( $options ) || [] === $options ) {
			return null;
		}

		return $this->flatten_values( $options );
	}

	/**
	 * Drop checkbox-grid keys that are not in the field's option list.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Submitted value.
	 *
	 * @return mixed
	 */
	private function restrict_checkbox_grid_keys( array $field, $value ) {
		if ( 'checkbox-grid' !== ( $field['type'] ?? '' ) || ! is_array( $value ) ) {
			return $value;
		}

		$allowed = $this->option_values( $field );
		if ( null === $allowed ) {
			return $value;
		}

		$allowed_lookup = array_fill_keys( $allowed, true );
		$filtered       = [];

		foreach ( $value as $key => $item ) {
			if ( isset( $allowed_lookup[ (string) $key ] ) ) {
				$filtered[ $key ] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Compile `rules.pattern` with a delimiter that is escaped in the pattern, then match.
	 * Invalid regexes fail closed (no warning).
	 *
	 * @param string $value   Submitted value.
	 * @param string $pattern Un-delimited regex from the field.
	 *
	 * @return bool
	 */
	private function value_matches_pattern( string $value, string $pattern ): bool {
		$compiled = '#' . str_replace( '#', '\\#', $pattern ) . '#';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid add-on patterns must not warn.
		if ( false === @preg_match( $compiled, '' ) ) {
			return false;
		}

		return 1 === preg_match( $compiled, $value );
	}

	/**
	 * Collect all leaf `value` strings from a nested options array.
	 *
	 * @param array<int, array<string, mixed>> $options Top-level field options.
	 *
	 * @return array<int, string>
	 */
	private function flatten_values( array $options ): array {
		$values = [];
		foreach ( $options as $option ) {
			if ( ! empty( $option['children'] ) && is_array( $option['children'] ) ) {
				$values = array_merge( $values, self::flatten_values( $option['children'] ) );
				continue;
			}
			if ( isset( $option['value'] ) ) {
				$values[] = (string) $option['value'];
			}
		}
		return $values;
	}
}
