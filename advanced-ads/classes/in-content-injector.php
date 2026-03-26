<?php // phpcs:ignoreFileName

use AdvancedAds\Utilities\Conditional;

/**
 * Injects ads in the content using high-performance string parsing.
 */
class Advanced_Ads_In_Content_Injector {

	/**
	 * Gather placeholders which later are replaced by the ads
	 *
	 * @var array $ads_for_placeholders
	 */
	private static $ads_for_placeholders = [];

	/**
	 * Inject ads directly into the contents
	 *
	 * @param string $placement_id Id of the placement.
	 * @param array  $placement_opts Placement options.
	 * @param string $content Content to inject placement into.
	 * @param array  $options {
	 *     Injection options.
	 *
	 *     @type bool   $allowEmpty                   Whether the tag can be empty to be counted.
	 *     @type bool   $paragraph_select_from_bottom Whether to select ads from buttom.
	 *     @type string $position                     Position. Can be one of 'before', 'after', 'append', 'prepend'
	 *     @type number $alter_nodes                  Whether to alter nodes, for example to prevent injecting ads into `a` tags.
	 *     @type bool   $repeat                       Whether to repeat the position.
	 *     @type number $paragraph_id                 Paragraph Id.
	 *     @type number $itemLimit                    If there are too few items at this level test nesting. Set to '-1` to prevent testing.
	 * }
	 *
	 * @return string $content Content with injected placement.
	 */
	public static function &inject_in_content( $placement_id, $placement_opts, &$content, $options = [] ) {
		$tag = isset( $placement_opts['tag'] ) ? $placement_opts['tag'] : 'p';

		// Normalize Pro position keys to internal injector keys
		$raw_pos = isset( $placement_opts['pro_custom_position'] ) ? $placement_opts['pro_custom_position'] : '';
		if ( empty( $raw_pos ) && isset( $options['pro_custom_position'] ) ) {
			$raw_pos = $options['pro_custom_position'];
		}

		if ( ! empty( $raw_pos ) ) {
			$pos_map = [
				'insertBefore' => 'before',
				'insertAfter'  => 'after',
				'prependTo'    => 'prepend',
				'appendTo'     => 'append',
			];
			if ( isset( $pos_map[ $raw_pos ] ) ) {
				$placement_opts['position'] = $pos_map[ $raw_pos ];
				$options['position']        = $pos_map[ $raw_pos ];
			}
		}

		if ( $tag === 'custom' ) {
			// Do NOT strip special characters for custom selectors like p[2]
			$tag_option = ! empty( $placement_opts['xpath'] ) ? stripslashes( $placement_opts['xpath'] ) : 'p';
		} else {
			$tag_option = preg_replace( '/[^a-z0-9]/i', '', $tag );
		}

		// get plugin options.
		$plugin_options = Advanced_Ads::get_instance()->options();

		$defaults = [
			'allowEmpty'                   => false,
			'paragraph_select_from_bottom' => isset( $placement_opts['start_from_bottom'] ) && $placement_opts['start_from_bottom'],
			'position'                     => isset( $placement_opts['position'] ) ? $placement_opts['position'] : 'after',
			// only has before and after.
			'before'                       => isset( $placement_opts['position'] ) && 'before' === $placement_opts['position'],
			// Whether to alter nodes, for example to prevent injecting ads into `a` tags.
			'alter_nodes'                  => true,
			'repeat'                       => false,
		];

		$defaults['paragraph_id'] = isset( $placement_opts['index'] ) ? $placement_opts['index'] : 1;
		$defaults['paragraph_id'] = max( 1, (int) $defaults['paragraph_id'] );

		// if there are too few items at this level test nesting.
		$defaults['itemLimit'] = ( strpos( $tag_option, 'p' ) === 0 ) ? 2 : 1;

		// trigger such a high item limit that all elements will be considered.
		if ( ! empty( $plugin_options['content-injection-level-disabled'] ) ) {
			$defaults['itemLimit'] = 1000;
		}

		// Handle tags that are empty by definition or could be empty ("custom" option).
		if ( in_array( $tag_option, [ 'img', 'iframe', 'custom' ], true ) || strpos( $tag_option, '@class' ) !== false || strpos( $tag_option, '@id' ) !== false ) {
			$defaults['allowEmpty'] = true;
		}

		// Merge the options if possible. If there are common keys, we don't merge them to prevent overriding and unexpected behavior.
		$common_keys = array_intersect_key( $options, $placement_opts );
		if ( empty( $common_keys ) ) {
			$options = array_merge( $options, $placement_opts );
		}

		// allow hooks to change some options.
		$options = apply_filters(
			'advanced-ads-placement-content-injection-options',
			wp_parse_args( $options, $defaults ),
			$tag_option
		);
		$content_to_load = self::get_content_to_load( $content );
		if ( ! $content_to_load ) {
			return $content;
		}

		// Build a Map of all HTML tags to understand hierarchy (Replaces DOMDocument)
		$tag_map = self::get_tag_map( $content_to_load );

		// Filter candidates based on complex rules (Replaces XPath /p[not(parent::blockquote)])
		$candidates = self::get_candidates_by_logic( $tag_map, $tag_option, $options );

		// Handle Nesting Levels (Replaces XPath /html/body/p vs //p)
		$paragraphs = self::apply_level_limitation( $candidates, $options['itemLimit'] );

		// Exclude forbidden areas (Replaces get_ancestors_to_limit)
		$forbidden_ranges = self::get_forbidden_ranges( $content_to_load );
		$filtered_paragraphs = [];
		foreach ( $paragraphs as $p ) {
			if ( ! self::is_offset_forbidden( $p['start'], $forbidden_ranges ) ) {
				$filtered_paragraphs[] = $p;
			}
		}
		$paragraphs = $filtered_paragraphs;

		$options['paragraph_count'] = count( $paragraphs );

		if ( $options['paragraph_count'] >= $options['paragraph_id'] ) {
			$target_idx = $options['paragraph_select_from_bottom'] ? $options['paragraph_count'] - $options['paragraph_id'] : $options['paragraph_id'] - 1;
			$offsets = apply_filters( 'advanced-ads-placement-content-offsets', [ $target_idx ], $options, $placement_opts, null, $paragraphs, null );
			$did_inject = false;
			$content_working_copy = $content_to_load;

			// Sort in descending order to prevent offset shifting during string manipulation
			rsort( $offsets );

			foreach ( $offsets as $offset_index ) {
				if ( ! isset( $paragraphs[ $offset_index ] ) ) {
					continue;
				}

				$node = $paragraphs[ $offset_index ];

				// Handle alter_nodes logic (Links/Captions)
				$insertion_point = self::calculate_insertion_point( $content_working_copy, $node, $options, $tag_option );

				$ad_content = (string) get_the_placement( $placement_id, '', $placement_opts );
				if ( trim( $ad_content ) === '' ) {
					continue;
				}

				$ad_placeholder = self::filter_ad_content( $ad_content, $node['tag'], $options );

				// Perform string injection
				$content_working_copy = substr_replace( $content_working_copy, $ad_placeholder, $insertion_point, 0 );
				$did_inject = true;
			}

			if ( $did_inject ) {
				$content = self::prepare_output( $content_working_copy, $content );
				return $content;
			}
		} elseif ( Conditional::user_can( 'advanced_ads_manage_options' ) && empty( $plugin_options['content-injection-level-disabled'] ) ) {
			// Ad Health Warning Logic
			if ( $options['paragraph_id'] <= count( $candidates ) ) {
				add_filter( 'advanced-ads-ad-health-nodes', [ __CLASS__, 'add_ad_health_node' ] );
			}
		}

		return $content;
	}

	/**
	 * Creates a virtual DOM map using Regex and Stacks.
	 * Identifies: tag name, start/end byte offsets, depth (1-3+), parent tag name, and full HTML content.
	 */
	private static function get_tag_map( $html ) {
		$map   = [];
		$stack = [];
		// Match opening, closing, and self-closing tags
		preg_match_all( '/<(\/?[a-z0-9]+)(?:\s+[^>]*?)?(\/?)>/i', $html, $matches, PREG_OFFSET_CAPTURE );

		foreach ( $matches[0] as $i => $match ) {
			$full_tag   = $match[0];
			$offset     = $match[1];
			$tag_name   = strtolower( $matches[1][$i][0] );
			$is_closing = $tag_name[0] === '/';

			if ( $is_closing ) {
				$tag_name = substr( $tag_name, 1 );
				// Walk backwards in stack to find matching opening tag
				for ( $j = count( $stack ) - 1; $j >= 0; $j-- ) {
					if ( $stack[ $j ]['tag'] === $tag_name && ! isset( $stack[ $j ]['end_offset'] ) ) {
						$stack[ $j ]['end_offset'] = $offset + strlen( $full_tag );
						$stack[ $j ]['full_html']  = substr( $html, $stack[ $j ]['start'], $stack[ $j ]['end_offset'] - $stack[ $j ]['start'] );
						$map[]                     = $stack[ $j ];
						array_splice( $stack, $j, 1 );
						break;
					}
				}
			} else {
				$is_self_closing = $matches[2][ $i ][0] === '/' || in_array( $tag_name, [ 'img', 'br', 'hr', 'input', 'meta', 'link', 'embed', 'source' ], true );

				// Extract classes from the opening tag
				$node_classes = [];
				if ( preg_match( '/class=["\']([^"\']+)["\']/i', $full_tag, $class_match ) ) {
					$node_classes = array_map( 'trim', explode( ' ', $class_match[1] ) );
				}

				// Extract ID from the opening tag
				$node_id = '';
				if ( preg_match( '/id=["\']([^"\']+)["\']/i', $full_tag, $id_match ) ) {
					$node_id = trim( $id_match[1] );
				}

				$parent_node = count( $stack ) > 0 ? $stack[ count( $stack ) - 1 ] : null;

				$node            = [
					'tag'       => $tag_name,
					'start'     => $offset,
					'depth'     => count( $stack ) + 1,
					'parent'    => $parent_node ? $parent_node['tag'] : 'body',
					'parent_id' => $parent_node && isset($parent_node['id']) ? $parent_node['id'] : '',
					'parent_classes' => $parent_node && isset($parent_node['classes']) ? $parent_node['classes'] : [],
					'html_open' => $full_tag,
					'classes'   => $node_classes,
					'id'        => $node_id,
				];
				if ( $is_self_closing ) {
					$node['end_offset'] = $offset + strlen( $full_tag );
					$node['full_html']  = $full_tag;
					$map[]              = $node;
				} else {
					$stack[] = $node;
				}
			}
		}
		usort( $map, function( $a, $b ) {
			return $a['start'] - $b['start'];
		} );
		return $map;
	}

	/**
	 * Implements the filtering logic. Supports custom p[2] selectors and hierarchical paths.
	 */
	private static function get_candidates_by_logic( $map, $tag_option, $options ) {
		$results     = [];
		$headlines   = apply_filters( 'advanced-ads-headlines-for-ad-injection', [ 'h2', 'h3', 'h4' ] );
		$whitespaces = json_decode( '"\t\n\r \u00A0"' );

		// FIX START: Detect index syntax p[10] from the whole string
		$req_idx = null;
		if ( preg_match( '/\[(\d+)\]$/', $tag_option, $match ) ) {
			$req_idx = (int) $match[1];
			// Strip the index for matching logic (p[10] becomes p)
			$tag_option = preg_replace( '/\[\d+\]$/', '', $tag_option );
		}
		$occurence_counter = 0;

		// Handle hierarchical selectors (div > header > div)
		$parts = array_filter(explode('/', $tag_option));
		$target_part = end($parts);
		$parent_part = (count($parts) > 1) ? prev($parts) : null;

		// Extract ALL classes for target
		$target_classes = [];
		if ( preg_match_all( "/'\s+([^\s\']+)\s+\'/", $target_part, $class_matches ) ) {
			$target_classes = $class_matches[1];
		}

		// Extract ID for target
		$target_id = null;
		if ( preg_match( "/@id\s*=\s*'([^']+)'/", $target_part, $id_match ) ) {
			$target_id = $id_match[1];
		}

		// Extract Tag for target
		$target_tag = null;
		if ( preg_match( '/^([a-z0-9]+)/i', $target_part, $tag_match ) ) {
			$target_tag = strtolower($tag_match[1]);
			if (in_array($target_tag, ['descendant', 'self'], true)) $target_tag = null;
		}

		// Pre-parse parent requirements
		$parent_classes = [];
		$parent_tag = null;
		if ($parent_part) {
			if ( preg_match_all( "/'\s+([^\s\']+)\s+\'/", $parent_part, $p_class_matches ) ) {
				$parent_classes = $p_class_matches[1];
			}
			if ( preg_match( '/^([a-z0-9]+)/i', $parent_part, $p_tag_match ) ) {
				$parent_tag = strtolower($p_tag_match[1]);
			}
		}

		foreach ( $map as $node ) {
			$is_match = false;

			// Check ID match
			if ( $target_id !== null ) {
				$is_match = ( $node['id'] === $target_id );
			}
			// Check Tag and Class match
			else {
				$tag_match = ($target_tag === null || $node['tag'] === $target_tag);
				$class_match = true;
				foreach ($target_classes as $tc) {
					if (!in_array($tc, $node['classes'], true)) {
						$class_match = false;
						break;
					}
				}
				$is_match = ($tag_match && $class_match);
			}

			// Verify Hierarchy (Parent)
			if ($is_match && $parent_part) {
				if ($parent_tag && $node['parent'] !== $parent_tag) {
					$is_match = false;
				}
				foreach ($parent_classes as $pc) {
					if (!in_array($pc, $node['parent_classes'], true)) {
						$is_match = false;
						break;
					}
				}
			}

			// Fallback for standard built-ins (p, headlines, etc)
			if (!$is_match && $target_id === null && empty($target_classes)) {
				switch ( $tag_option ) {
					case 'p':
						$is_match = ( $node['tag'] === 'p' && $node['parent'] !== 'blockquote' );
						break;
					case 'pwithoutimg':
						$is_match = ( $node['tag'] === 'p' && $node['parent'] !== 'blockquote' && stripos( $node['full_html'], '<img' ) === false );
						break;
					case 'headlines':
						$is_match = in_array( $node['tag'], $headlines, true );
						break;
					case 'img':
						$is_match = ( $node['tag'] === 'img' || ( $node['tag'] === 'div' && preg_match( '/wp-caption|gallery/', $node['html_open'] ) ) );
						break;
					case 'anyelement':
						$exclude  = [ 'html', 'body', 'script', 'style', 'tr', 'td', 'a', 'abbr', 'b', 'br', 'em', 'i', 'img', 'span', 'strong', 'small' ];
						$is_match = ! in_array( $node['tag'], $exclude, true );
						break;
					default:
						$is_match = ( $node['tag'] === $target_tag );
				}
			}

			if ( $is_match ) {
				if ( ! $options['allowEmpty'] && trim( strip_tags( $node['full_html'] ), $whitespaces ) === '' ) {
					continue;
				}

				// FIX: Handle the Index counting here
				if ($req_idx !== null) {
					$occurence_counter++;
					if ($occurence_counter === $req_idx) {
						return [$node]; // Return only the Nth match
					}
				} else {
					$results[] = $node;
				}
			}
		}
		return $results;
	}

	/**
	 * Replicates hierarchy depth logic.
	 */
	private static function apply_level_limitation( $candidates, $limit ) {
		if ( -1 === $limit || empty( $candidates ) ) {
			return $candidates;
		}

		foreach ( [ 1, 2, 3 ] as $depth ) {
			$lvl = array_values(
				array_filter(
					$candidates,
					function( $n ) use ( $depth ) {
						return $n['depth'] === $depth;
					}
				)
			);
			if ( count( $lvl ) >= $limit ) {
				return $lvl;
			}
		}
		return $candidates;
	}

	/**
	 * Logic to move insertion point outside of links or captions
	 */
	private static function calculate_insertion_point( $html, $node, $options, $tag_option ) {
		// Prevent injection into image links (a > img)
		if ( $options['alter_nodes'] && strpos( $tag_option, 'img' ) !== false && $node['parent'] === 'a' ) {
			if ( preg_match( '/<a\b[^>]*>.*?<\/a>/is', $html, $m, PREG_OFFSET_CAPTURE, max( 0, $node['start'] - 500 ) ) ) {
				foreach ( $m as $match ) {
					if ( $node['start'] >= $match[1] && $node['start'] <= $match[1] + strlen( $match[0] ) ) {
						return ( $options['position'] === 'before' ) ? $match[1] : $match[1] + strlen( $match[0] );
					}
				}
			}
		}

		switch ( $options['position'] ) {
			case 'before':
				return $node['start'];
			case 'prepend':
				return $node['start'] + strlen( $node['html_open'] );
			case 'append':
				return $node['end_offset'] - ( isset( $node['tag'] ) ? strlen( "</{$node['tag']}>" ) : 0 );
			case 'after':
			default:
				return $node['end_offset'];
		}
	}

	/**
	 * Find ranges where ad injection is strictly forbidden.
	 */
	private static function get_forbidden_ranges( $html ) {
		$ranges  = [];
		$classes = [ 'advads-stop-injection', 'woopack-product-carousel', 'geodir-post-slider', 'wp-caption', 'gallery-size' ];
		$pattern = '/<(\w+)\b[^>]*class="[^"]*(' . implode( '|', $classes ) . ')[^"]*"[^>]*>.*?<\/\\1>/is';

		if ( preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$ranges[] = [ $match[1], $match[1] + strlen( $match[0] ) ];
			}
		}
		return $ranges;
	}

	private static function is_offset_forbidden( $offset, $ranges ) {
		foreach ( $ranges as $range ) {
			if ( $offset >= $range[0] && $offset <= $range[1] ) {
				return true;
			}
		}
		return false;
	}

	private static function get_content_to_load( $content ) {
		$content_to_load = preg_replace( '/<script.*?<\/script>/si', '<!--\0-->', $content );
		$wpautop_priority = has_filter( 'the_content', 'wpautop' );
		if ( $wpautop_priority && Advanced_Ads::get_instance()->get_content_injection_priority() < $wpautop_priority ) {
			$content_to_load = wpautop( $content_to_load );
		}
		return $content_to_load;
	}

	private static function filter_ad_content( $ad_content, $tag_name, $options ) {
		$ad_content = preg_replace( '#(document.write.+)</(.*)#', '$1<\/$2', $ad_content );
		$id         = count( self::$ads_for_placeholders );
		self::$ads_for_placeholders[] = [
			'id'       => $id,
			'tag'      => $tag_name,
			'position' => $options['position'],
			'ad'       => $ad_content,
		];
		return '%advads_placeholder_' . $id . '%';
	}

	private static function prepare_output( $content, $content_orig ) {
		$content                    = self::inject_ads( $content, $content_orig, self::$ads_for_placeholders );
		self::$ads_for_placeholders = [];
		return $content;
	}

	private static function inject_ads( $content, $content_orig, $ads_for_placeholders ) {
		$self_closing_tags = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source' ];

		foreach ( $ads_for_placeholders as &$ad_content ) {
			if ( ( 'prepend' === $ad_content['position'] || 'append' === $ad_content['position'] ) && in_array( $ad_content['tag'], $self_closing_tags, true ) ) {
				$ad_content['position'] = 'after';
			}
		}
		unset( $ad_content );
		usort( $ads_for_placeholders, [ __CLASS__, 'sort_ads_for_placehoders' ] );

		$alts = [];
		foreach ( $ads_for_placeholders as $ad_content ) {
			$tag = $ad_content['tag'];
			if ( 'before' === $ad_content['position'] || 'prepend' === $ad_content['position'] || in_array( $tag, $self_closing_tags, true ) ) {
				$alts[] = "<{$tag}[^>]*>";
			} else {
				$alts[] = "</{$tag}>";
			}
		}
		$alts       = array_unique( $alts );
		$tag_regexp = implode( '|', $alts );
		$alts[]     = '%advads_placeholder_(?:\d+)%';
		$full_regex = implode( '|', $alts );

		preg_match_all( "#{$full_regex}#i", $content, $tag_matches );
		$count = 0;

		foreach ( $tag_matches[0] as $r ) {
			if ( preg_match( '/%advads_placeholder_(\d+)%/', $r, $result ) ) {
				$id = $result[1];
				foreach ( $ads_for_placeholders as $n => $ad ) {
					if ( (int) $ad['id'] === (int) $id ) {
						$ads_for_placeholders[ $n ]['offset'] = ( 'before' === $ad['position'] || 'append' === $ad['position'] ) ? $count : $count - 1;
						break;
					}
				}
			} else {
				++$count;
			}
		}

		preg_match_all( "#{$tag_regexp}#i", $content_orig, $orig_tag_matches, PREG_OFFSET_CAPTURE );
		$new_content = '';
		$pos         = 0;

		foreach ( $orig_tag_matches[0] as $n => $r ) {
			foreach ( $ads_for_placeholders as $item ) {
				if ( isset( $item['offset'] ) && $item['offset'] === $n ) {
					$found_pos    = ( 'before' === $item['position'] || 'append' === $item['position'] ) ? $r[1] : $r[1] + strlen( $r[0] );
					$new_content .= substr( $content_orig, $pos, $found_pos - $pos ) . $item['ad'];
					$pos          = $found_pos;
				}
			}
		}
		return $new_content . substr( $content_orig, $pos );
	}

	public static function sort_ads_for_placehoders( $first, $second ) {
		if ( $first['position'] === $second['position'] ) {
			return 0;
		}
		$num = [ 'before' => 1, 'prepend' => 2, 'append' => 3, 'after' => 4 ];
		return $num[ $first['position'] ] > $num[ $second['position'] ] ? 1 : -1;
	}

	public static function add_ad_health_node( $nodes ) {
		$nodes[] = [
			'type' => 1,
			'data' => [
				'parent' => 'advanced_ads_ad_health',
				'id'     => 'advanced_ads_ad_health_the_content_not_enough_elements',
				'title'  => sprintf( __( 'Set <em>%s</em> to show more ads', 'advanced-ads' ), __( 'Disable level limitation', 'advanced-ads' ) ),
				'href'   => admin_url( '/admin.php?page=advanced-ads-settings#top#general' ),
				'meta'   => [ 'class' => 'advanced_ads_ad_health_warning', 'target' => '_blank' ],
			],
		];
		return $nodes;
	}
}
