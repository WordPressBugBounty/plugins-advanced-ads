<?php
/**
 * Form radio input
 *
 * @package AdvancedAds\Framework\Form
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Framework\Form;

use AdvancedAds\Framework\Utilities\HTML;

defined( 'ABSPATH' ) || exit;

/**
 * Field radio class
 */
class Field_Radio extends Field {

	/**
	 * Render field
	 *
	 * @return void
	 */
	public function render() {
		// Early bail!!
		if ( ! $this->get( 'options' ) ) {
			return;
		}

		$counter = 1;

		$wrap_class = HTML::classnames( 'advads-radio-list', $this->get( 'class' ) );
		echo '<div class=" ' . esc_attr( $wrap_class ) . '">';
		foreach ( $this->get( 'options' ) as $data ) :
			$option_id   = $this->get( 'id' ) . '-' . ( $counter++ );
			?>
			<label for="<?php echo esc_attr( $option_id ); ?>">
				<input type="radio" id="<?php echo esc_attr( $option_id ); ?>" name="<?php echo esc_attr( $this->get( 'name' ) ); ?>" value="<?php echo esc_attr( $data['value'] ); ?>"<?php checked( $this->get( 'value' ), $data['value'] ); ?> /><?php echo esc_html( $data['label'] ); ?>
			</label>
			<?php
		endforeach;

		echo '</div>';
	}
}
