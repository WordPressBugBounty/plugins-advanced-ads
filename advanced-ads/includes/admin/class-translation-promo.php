<?php
/**
 * Translation Promo.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.45.0
 */

namespace AdvancedAds\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * This class defines a promo box and checks your translation site's API for stats about it.
 *
 * @copyright Yoast i18n https://github.com/Yoast/i18n-module
 *
 * phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain
 */
class Translation_Promo {

	/**
	 * Your translation site's logo.
	 *
	 * @var string|null
	 */
	private $glotpress_logo;

	/**
	 * Your translation site's name.
	 *
	 * @var string
	 */
	private $glotpress_name;

	/**
	 * The URL to actually do the API request to.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Hook where you want to show the promo box.
	 *
	 * @var string
	 */
	private $hook;

	/**
	 * Will contain the site's locale.
	 *
	 * @access private
	 * @var string
	 */
	private $locale;

	/**
	 * Will contain the locale's name, obtained from your translation site.
	 *
	 * @access private
	 * @var string
	 */
	private $locale_name;

	/**
	 * Will contain the percentage translated for the plugin translation project in the locale.
	 *
	 * @access private
	 * @var int|string
	 */
	private $percent_translated;

	/**
	 * Name of your plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Project slug for the project on your translation site.
	 *
	 * @var string
	 */
	private $project_slug;

	/**
	 * URL to point to for registration links.
	 *
	 * @var string
	 */
	private $register_url;

	/**
	 * Your plugins textdomain.
	 *
	 * @var string
	 */
	private $textdomain;

	/**
	 * Indicates whether there's a translation available at all.
	 *
	 * @access private
	 * @var bool
	 */
	private $translation_exists;

	/**
	 * Indicates whether the translation's loaded.
	 *
	 * @access private
	 * @var bool
	 */
	private $translation_loaded;

	/**
	 * Constructs the i18n module for wordpress.org — required fields are the 'textdomain', 'plugin_name' and 'hook'.
	 *
	 * @param array<string, mixed> $args The settings for the i18n module.
	 * @param bool                 $show_translation_box Whether the translation box should be shown.
	 */
	public function __construct( $args, $show_translation_box = true ) {
		if ( ! is_admin() ) {
			return;
		}

		$args         = $this->set_defaults( $args );
		$this->locale = get_user_locale();
		if ( 'en_US' === $this->locale ) {
			return;
		}

		$this->init( $args );

		if ( $show_translation_box ) {
			add_action( $this->hook, [ $this, 'promo' ] );
		}

		$this->api_url = 'https://translate.wordpress.org/api/projects/wp-plugins/' . $args['textdomain'] . '/stable/';
	}

	/**
	 * This is where you decide where to display the messages and where you set the plugin specific variables.
	 *
	 * @access private
	 *
	 * @param array<string, mixed> $args Contains the settings for the class.
	 *
	 * @return void
	 */
	private function init( $args ): void {
		foreach ( $args as $key => $arg ) {
			$this->$key = $arg;
		}
	}

	/**
	 * Check whether the promo should be hidden or not.
	 *
	 * @access private
	 *
	 * @return bool
	 */
	private function hide_promo() {
		$hide_promo = get_transient( 'yoast_i18n_' . $this->project_slug . '_promo_hide' );
		if ( ! $hide_promo ) {
			if ( filter_input( INPUT_GET, 'remove_i18n_promo', FILTER_VALIDATE_INT ) === 1 ) {
				// No expiration time, so this would normally not expire, but it wouldn't be copied to other sites etc.
				set_transient( 'yoast_i18n_' . $this->project_slug . '_promo_hide', true );
				$hide_promo = true;
			}
		}
		return $hide_promo;
	}

	/**
	 * Returns the i18n_promo message from the i18n_module. Returns en empty string if the promo shouldn't be shown.
	 *
	 * @access public
	 *
	 * @return string The i18n promo message.
	 */
	public function get_promo_message() {
		if ( 'en_US' !== $this->locale && ! $this->hide_promo() ) {
			return $this->promo_message();
		}

		return '';
	}

	/**
	 * Generates a promo message.
	 *
	 * @access private
	 *
	 * @return string $message
	 */
	private function promo_message() {

		$this->translation_details();

		$message = false;

		if ( $this->translation_exists && $this->translation_loaded && $this->percent_translated < 90 ) {
			/* translators: 1: language name; 3: completion percentage; 4: link to translation platform. */
			$message = __( 'As you can see, there is a translation of this plugin in %1$s. This translation is currently %3$d%% complete. We need your help to make it complete and to fix any errors. Please register at %4$s to help complete the translation to %1$s!', $this->textdomain );
		} elseif ( ! $this->translation_loaded && $this->translation_exists ) {
			/* translators: 1: language name; 2: plugin name; 3: completion percentage; 4: link to translation platform. */
			$message = __( 'You\'re using WordPress in %1$s. While %2$s has been translated to %1$s for %3$d%%, it\'s not been shipped with the plugin yet. You can help! Register at %4$s to help complete the translation to %1$s!', $this->textdomain );
		} elseif ( ! $this->translation_exists ) {
			/* translators: 2: plugin name; 4: link to translation platform. */
			$message = __( 'You\'re using WordPress in a language we don\'t support yet. We\'d love for %2$s to be translated in that language too, but unfortunately, it isn\'t right now. You can change that! Register at %4$s to help translate it!', $this->textdomain );
		}

		$registration_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->register_url ),
			esc_html( $this->glotpress_name )
		);

		$message = sprintf(
			esc_html( $message ),
			esc_html( $this->locale_name ),
			esc_html( $this->plugin_name ),
			(int) $this->percent_translated,
			$registration_link
		);

		if ( $message ) {
			$message = '<p>' . $message . '</p><p><a href="' . esc_url( $this->register_url ) . '">' . esc_html__( 'Register now &raquo;', $this->textdomain ) . '</a></p>';
		}

		return $message;
	}

	/**
	 * Sets the default values for wordpress.org
	 *
	 * @param array<string, mixed> $args The arguments to set defaults for.
	 *
	 * @return array<string, mixed> The arguments with the arguments set.
	 */
	private function set_defaults( $args ) {

		if ( ! isset( $args['glotpress_logo'] ) ) {
			$args['glotpress_logo'] = 'https://plugins.svn.wordpress.org/' . $args['textdomain'] . '/assets/icon-128x128.png';
		}

		if ( ! isset( $args['register_url'] ) ) {
			$args['register_url'] = 'https://translate.wordpress.org/projects/wp-plugins/' . $args['textdomain'] . '/';
		}

		if ( ! isset( $args['glotpress_name'] ) ) {
			$args['glotpress_name'] = 'Translating WordPress';
		}

		if ( ! isset( $args['project_slug'] ) ) {
			$args['project_slug'] = $args['textdomain'];
		}

		return $args;
	}

	/**
	 * Outputs a promo box.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function promo() {
		$message = $this->get_promo_message();

		if ( $message ) {
			echo '<div id="i18n_promo_box" style="border:1px solid #ccc;background-color:#fff;padding:10px;max-width:650px; overflow: hidden;">';
			echo '<a href="' . esc_url( add_query_arg( [ 'remove_i18n_promo' => '1' ] ) ) . '" style="color:#333;text-decoration:none;font-weight:bold;font-size:16px;border:1px solid #ccc;padding:1px 4px;" class="alignright">X</a>';

			echo '<div>';
			/* translators: %s: plugin name. */
			echo '<h2>' . sprintf( esc_html__( 'Translation of %s', $this->textdomain ), esc_html( $this->plugin_name ) ) . '</h2>';
			if ( isset( $this->glotpress_logo ) && is_string( $this->glotpress_logo ) && '' !== $this->glotpress_logo ) {
				echo '<a href="' . esc_url( $this->register_url ) . '"><img class="alignright" style="margin:0 5px 5px 5px;max-width:200px;" src="' . esc_url( $this->glotpress_logo ) . '" alt="' . esc_attr( $this->glotpress_name ) . '"/></a>';
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- correctly escaped in promo_message() method.
			echo $message;
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Try to find the transient for the translation set or retrieve them.
	 *
	 * @access private
	 *
	 * @return object|null
	 */
	private function find_or_initialize_translation_details() {
		$set = get_transient( 'yoast_i18n_' . $this->project_slug . '_' . $this->locale );

		if ( ! $set ) {
			$set = $this->retrieve_translation_details();
			set_transient( 'yoast_i18n_' . $this->project_slug . '_' . $this->locale, $set, DAY_IN_SECONDS );
		}

		return $set;
	}

	/**
	 * Try to get translation details from cache, otherwise retrieve them, then parse them.
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function translation_details(): void {
		$set = $this->find_or_initialize_translation_details();

		$this->translation_exists = ! is_null( $set );
		$this->translation_loaded = is_textdomain_loaded( $this->textdomain );

		$this->parse_translation_set( $set );
	}

	/**
	 * Retrieve the translation details from WordPress.org Translate.
	 *
	 * @access private
	 *
	 * @return object|null
	 */
	private function retrieve_translation_details() {
		$resp = wp_remote_get( $this->api_url );
		if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $resp );
		unset( $resp );

		if ( $body ) {
			$body = json_decode( $body );
			if ( empty( $body->translation_sets ) ) {
				return null;
			}
			foreach ( $body->translation_sets as $set ) {
				if ( ! property_exists( $set, 'wp_locale' ) ) {
					continue;
				}

				// For informal and formal locales, we have to complete the locale code by concatenating the slug ('formal' or 'informal') to the xx_XX part.
				if ( 'default' !== $set->slug && strtolower( $this->locale ) === strtolower( $set->wp_locale . '_' . $set->slug ) ) {
					return $set;
				}

				if ( $this->locale === $set->wp_locale ) {
					return $set;
				}
			}
		}

		return null;
	}

	/**
	 * Set the needed private variables based on the results from Yoast Translate.
	 *
	 * @param object $set The translation set.
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function parse_translation_set( $set ): void {
		$this->locale_name        = '';
		$this->percent_translated = '';

		if ( $this->translation_exists && is_object( $set ) ) {
			$this->locale_name        = $set->name;
			$this->percent_translated = $set->percent_translated;
		}
	}
}
