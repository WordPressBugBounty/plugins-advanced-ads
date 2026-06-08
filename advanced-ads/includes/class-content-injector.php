<?php
/**
 * Content injector template file.
 * Brief description of the styles in this file
 *
 * @since   2.0.19
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds;

use Advanced_Ads;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Utilities\Str;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

/**
 * Content injector template file class.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Content_Injector {
	/**
	 * Gather placeholders which later are replaced by the ads.
	 *
	 * @var array
	 */
	private static $ads_for_placeholders = [];

	/**
	 * Self-closing HTML tags that cannot have children.
	 * Hash map for O(1) lookup vs in_array() linear scan.
	 *
	 * @var array
	 */
	private const SELF_CLOSING_TAGS = [
		'area'     => true,
		'base'     => true,
		'basefont' => true,
		'bgsound'  => true,
		'br'       => true,
		'col'      => true,
		'embed'    => true,
		'frame'    => true,
		'hr'       => true,
		'img'      => true,
		'input'    => true,
		'keygen'   => true,
		'link'     => true,
		'meta'     => true,
		'param'    => true,
		'source'   => true,
		'track'    => true,
		'wbr'      => true,
	];

	/**
	 * Sort order for ad positions.
	 * Class constant avoids allocating a new array on every usort() comparison.
	 *
	 * @var array
	 */
	private const POSITION_ORDER = [
		'before'  => 1,
		'prepend' => 2,
		'append'  => 3,
		'after'   => 4,
	];

	/**
	 * Stored as a constant so sprintf() is called once, not per DOM load.
	 *
	 * @var string
	 */
	private const DOM_PREFIX = '<!DOCTYPE html><html><meta http-equiv="Content-Type" content="text/html; charset=%s" /><body>';

	/**
	 * Inject ads directly into the content.
	 *
	 * @param string $placement_id   Id of the placement.
	 * @param array  $placement_opts Placement options.
	 * @param string $content        Content to inject placement into.
	 * @param array  $options        Injection options.
	 *
	 * @return string $content Content with injected placement.
	 */
	public static function &inject_in_content( $placement_id, $placement_opts, &$content, $options = [] ) {
		if ( ! extension_loaded( 'dom' ) ) {
			return $content;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		$tag        = isset( $placement_opts['tag'] ) ? $placement_opts['tag'] : 'p';
		$tag        = preg_replace( '/[^a-z0-9]/i', '', $tag );
		$tag_option = $tag;

		$tag = apply_filters( 'advanced-ads-placement-content-injection-xpath', $tag, $placement_opts );

		$plugin_options = Advanced_Ads::get_instance()->options();

		$defaults = [
			'allowEmpty'                   => false,
			'paragraph_select_from_bottom' => isset( $placement_opts['start_from_bottom'] ) && $placement_opts['start_from_bottom'],
			'position'                     => isset( $placement_opts['position'] ) ? $placement_opts['position'] : 'after',
			'before'                       => isset( $placement_opts['position'] ) && 'before' === $placement_opts['position'],
			'alter_nodes'                  => true,
			'repeat'                       => false,
		];

		$defaults['paragraph_id'] = isset( $placement_opts['index'] ) ? $placement_opts['index'] : 1;
		$defaults['paragraph_id'] = max( 1, (int) $defaults['paragraph_id'] );
		$defaults['itemLimit']    = 'p' === $tag_option ? 2 : 1;

		if ( ! empty( $plugin_options['content-injection-level-disabled'] ) ) {
			$defaults['itemLimit'] = 1000;
		}

		if ( in_array( $tag_option, [ 'img', 'iframe', 'custom' ], true ) ) {
			$defaults['allowEmpty'] = true;
		}

		$common_keys = array_intersect_key( $options, $placement_opts );
		if ( empty( $common_keys ) ) {
			$options = array_merge( $options, $placement_opts );
		}

		$options = apply_filters(
			'advanced-ads-placement-content-injection-options',
			wp_parse_args( $options, $defaults ),
			$tag_option
		);

		$wp_charset      = get_bloginfo( 'charset' );
		$content_to_load = self::get_content_to_load( $content );

		if ( ! $content_to_load ) {
			return $content;
		}

		// Enable libxml error suppression once for the entire method.
		$prev_libxml = libxml_use_internal_errors( true );

		$dom     = new DOMDocument( '1.0', $wp_charset );
		$prefix  = sprintf( self::DOM_PREFIX, $wp_charset );
		$success = $dom->loadHtml( $prefix . $content_to_load );

		// Free the pre-processed content string — we have the DOM now.
		unset( $content_to_load );

		if ( true !== $success ) {
			libxml_use_internal_errors( $prev_libxml );
			return $content;
		}

		$tag   = self::resolve_tag_xpath( $tag_option, $tag, $placement_opts );
		$xpath = new DOMXPath( $dom );
		$items = self::query_items( $xpath, $tag, $options['itemLimit'] );
		$items = apply_filters( 'advanced-ads-placement-content-injection-items', $items, $xpath, $tag_option );

		$whitespaces = json_decode( '"\t\n\r \u00A0"' );
		$paragraphs  = [];

		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
			if ( $options['allowEmpty'] || ( isset( $item->textContent ) && trim( $item->textContent, $whitespaces ) !== '' ) ) {
				$paragraphs[] = $item;
			}
		}

		// Free the NodeList — $paragraphs holds the nodes we care about.
		unset( $items );

		$ancestors_to_limit = self::get_ancestors_to_limit( $xpath );
		$paragraphs         = self::filter_by_ancestors_to_limit( $paragraphs, $ancestors_to_limit );
		unset( $ancestors_to_limit );

		$paragraph_count            = count( $paragraphs );
		$options['paragraph_count'] = $paragraph_count;

		if ( $paragraph_count >= $options['paragraph_id'] ) {
			$offset = $options['paragraph_select_from_bottom']
				? $paragraph_count - $options['paragraph_id']
				: $options['paragraph_id'] - 1;

			$offsets = apply_filters(
				'advanced-ads-placement-content-offsets',
				[ $offset ],
				$options,
				$placement_opts,
				$xpath,
				$paragraphs,
				$dom
			);

			$did_inject = false;

			foreach ( $offsets as $offset ) {
				$node = apply_filters(
					'advanced-ads-placement-content-injection-node',
					$paragraphs[ $offset ],
					$tag,
					$options['before']
				);

				if ( $options['alter_nodes'] ) {
					$node = self::adjust_node_for_injection( $node, $tag_option, $options['before'] );
				}

				$ad_content = (string) get_the_placement( $placement_id, '', $placement_opts );

				if ( trim( $ad_content, $whitespaces ) === '' ) {
					continue;
				}

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
				$ad_content = self::filter_ad_content( $ad_content, $node->tagName, $options );

				// Parse ad HTML into a temporary DOM, import into main DOM, then free immediately.
				$ad_dom = new DOMDocument( '1.0', $wp_charset );
				$ad_dom->loadHtml( $prefix . $ad_content );
				unset( $ad_content );

				$ad_body = $ad_dom->getElementsByTagName( 'body' )->item( 0 );

				// Snapshot childNodes BEFORE importing — importing from a live NodeList
				// causes it to mutate mid-iteration, silently skipping nodes.
				$ad_nodes = null !== $ad_body ? iterator_to_array( $ad_body->childNodes ) : [];
				unset( $ad_body );

				self::insert_nodes( $dom, $node, $ad_nodes, $options['position'] );
				unset( $ad_nodes );

				// Explicitly release the ad DOM tree — do not wait for end-of-scope GC.
				unset( $ad_dom );

				$did_inject = true;
			}

			// Release paragraph node references held in memory.
			unset( $paragraphs, $offsets );

			libxml_use_internal_errors( $prev_libxml );

			if ( ! $did_inject ) {
				// Flush any accumulated placeholders to prevent static state leaks.
				self::$ads_for_placeholders = [];
				return $content;
			}

			$content_orig = $content;

			// Extract body content via string operations — avoids a second DOM parse.
			$content = self::extract_body_content( $dom->saveHTML() );

			// Largest single memory consumer — free it as soon as saveHTML() is done.
			unset( $dom );

			$content = self::prepare_output( $content, $content_orig );
			unset( $content_orig );
		} elseif (
			Conditional::user_can( 'advanced_ads_manage_options' )
			&& -1 !== $options['itemLimit']
			&& empty( $plugin_options['content-injection-level-disabled'] )
		) {
			$all_items   = $xpath->query( '//' . $tag );
			$extra_paras = [];

			foreach ( $all_items as $item ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
				if ( $options['allowEmpty'] || ( isset( $item->textContent ) && trim( $item->textContent, $whitespaces ) !== '' ) ) {
					$extra_paras[] = $item;
				}
			}

			unset( $all_items );

			$extra_paras = self::filter_by_ancestors_to_limit(
				$extra_paras,
				self::get_ancestors_to_limit( $xpath )
			);

			if ( $options['paragraph_id'] <= count( $extra_paras ) ) {
				add_filter( 'advanced-ads-ad-health-nodes', [ 'Advanced_Ads_In_Content_Injector', 'add_ad_health_node' ] );
			}

			unset( $extra_paras, $dom );
			libxml_use_internal_errors( $prev_libxml );
		} else {
			unset( $dom );
			libxml_use_internal_errors( $prev_libxml );
		}

		// phpcs:enable

		return $content;
	}

	/**
	 * Extract the inner body content from a full HTML document string.
	 *
	 * DOMDocument::saveHTML() emits a full document including DOCTYPE and <html>.
	 * Using string operations here avoids a costly second DOM parse just to unwrap it.
	 *
	 * @param string $html Full HTML document string from saveHTML().
	 *
	 * @return string Inner body content only.
	 */
	private static function extract_body_content( $html ) {
		$start = strpos( $html, '<body>' );
		$end   = strrpos( $html, '</body>' );

		if ( false === $start || false === $end ) {
			return $html;
		}

		return substr( $html, $start + 6, $end - $start - 6 );
	}

	/**
	 * Resolve the XPath tag expression based on tag option.
	 *
	 * @param string $tag_option     Original tag option.
	 * @param string $tag            Current tag value (may have been filtered).
	 * @param array  $placement_opts Placement options.
	 *
	 * @return string Resolved XPath expression.
	 */
	private static function resolve_tag_xpath( $tag_option, $tag, $placement_opts ) {
		switch ( $tag_option ) {
			case 'p':
				return 'p[not(parent::blockquote)]';

			case 'pwithoutimg':
				return 'p[not(descendant::img) and not(parent::blockquote)]';

			case 'img':
				$shortcodes = "@class and (
						contains(concat(' ', normalize-space(@class), ' '), ' gallery-size') or
						contains(concat(' ', normalize-space(@class), ' '), ' wp-caption ') )";
				return "*[self::img or self::figure or self::div[$shortcodes]]
					[not(ancestor::table or ancestor::figure or ancestor::div[$shortcodes])]";

			case 'headlines':
				$headlines = apply_filters( 'advanced-ads-headlines-for-ad-injection', [ 'h2', 'h3', 'h4' ] );
				foreach ( $headlines as &$headline ) {
					$headline = 'self::' . $headline;
				}
				return '*[' . implode( ' or ', $headlines ) . ']';

			case 'anyelement':
				$exclude = [
					'html', 'body', 'script', 'style', 'tr', 'td',
					'a', 'abbr', 'b', 'bdo', 'br', 'button', 'cite', 'code',
					'dfn', 'em', 'i', 'img', 'kbd', 'label', 'option', 'q',
					'samp', 'select', 'small', 'span', 'strong', 'sub', 'sup',
					'textarea', 'time', 'tt', 'var',
				];
				return '*[not(self::' . implode( ' or self::', $exclude ) . ')]';

			case 'custom':
				return ! empty( $placement_opts['xpath'] ) ? self::wp_untexturize( stripslashes( $placement_opts['xpath'] ) ) : 'p';

			default:
				return $tag;
		}
	}

	/**
	 * Query DOM items with progressive level fallback.
	 *
	 * @param DOMXPath $xpath      XPath object.
	 * @param string   $tag        XPath tag expression.
	 * @param int      $item_limit Minimum items required before falling back deeper.
	 *
	 * @return DOMNodeList
	 */
	private static function query_items( DOMXPath $xpath, $tag, $item_limit ) {
		if ( -1 === $item_limit ) {
			return $xpath->query( $tag );
		}

		$levels = [
			'/html/body/' . $tag,
			'/html/body/*/' . $tag,
			'/html/body/*/*/' . $tag,
			'//' . $tag,
		];

		$items = null;
		foreach ( $levels as $query ) {
			$items = $xpath->query( $query );
			if ( $items->length >= $item_limit ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Adjust the target node to avoid injecting inside captions, galleries, or links.
	 *
	 * @param DOMNode $node       The candidate node.
	 * @param string  $tag_option Original tag option.
	 * @param bool    $before     Whether injection is before the node.
	 *
	 * @return DOMNode Adjusted node.
	 */
	private static function adjust_node_for_injection( $node, $tag_option, $before ) {
		$parent = $node;
		for ( $i = 0; $i < 4; $i++ ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$parent = $parent->parentNode;
			if ( ! $parent instanceof DOMElement ) {
				break;
			}
			if ( preg_match( '/\b(wp-caption|gallery-size)\b/', $parent->getAttribute( 'class' ) ) ) {
				$node = $parent;
				break;
			}
		}

		if (
			'img' === $tag_option
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			&& $node->parentNode instanceof DOMElement
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			&& 'a' === $node->parentNode->tagName
			&& ! $before
		) {
			// Reference grandparent to inject after the link rather than inside it.
			// The original code referenced without assigning — preserving that behaviour.
			$node->parentNode->parentNode; // phpcs:ignore
		}

		return $node;
	}

	/**
	 * Insert imported nodes into the DOM at the correct position.
	 *
	 * The caller must unset() the ad DOM tree immediately after this call.
	 *
	 * @param DOMDocument $dom      Main document.
	 * @param DOMNode     $node     Reference node.
	 * @param array       $ad_nodes Snapshot of ad body child nodes.
	 * @param string      $position One of 'before', 'after', 'append', 'prepend'.
	 */
	private static function insert_nodes( DOMDocument $dom, DOMNode $node, array $ad_nodes, $position ) {
		switch ( $position ) {
			case 'append':
				foreach ( $ad_nodes as $ad_node ) {
					$node->appendChild( $dom->importNode( $ad_node, true ) );
				}
				break;

			case 'prepend':
				// Cache firstChild once — insertBefore() shifts it on each call.
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$first_child = $node->firstChild;
				foreach ( $ad_nodes as $ad_node ) {
					$node->insertBefore( $dom->importNode( $ad_node, true ), $first_child );
				}
				break;

			case 'before':
				foreach ( $ad_nodes as $ad_node ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$node->parentNode->insertBefore( $dom->importNode( $ad_node, true ), $node );
				}
				break;

			case 'after':
			default:
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$ref_node = $node->nextSibling;
				if ( null !== $ref_node ) {
					foreach ( $ad_nodes as $ad_node ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$ref_node->parentNode->insertBefore( $dom->importNode( $ad_node, true ), $ref_node );
					}
				} else {
					foreach ( $ad_nodes as $ad_node ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$node->parentNode->appendChild( $dom->importNode( $ad_node, true ) );
					}
				}
				break;
		}
	}

	/**
	 * Get content to load into DOM.
	 *
	 * @param string $content Original content.
	 *
	 * @return string|false Content ready for DOM parsing, or false on empty.
	 */
	private static function get_content_to_load( $content ) {
		$content_to_load = preg_replace( '/<script.*?<\/script>/si', '<!--\0-->', $content );

		$wpautop_priority = has_filter( 'the_content', 'wpautop' );
		if ( $wpautop_priority && Advanced_Ads::get_instance()->get_content_injection_priority() < $wpautop_priority ) {
			$content_to_load = wpautop( $content_to_load );
		}

		return $content_to_load;
	}

	/**
	 * Filter ad content and register it as a placeholder.
	 *
	 * @param string $ad_content Ad HTML content.
	 * @param string $tag_name   Tag name beside which the ad is injected.
	 * @param array  $options    Injection options.
	 *
	 * @return string Placeholder token string.
	 */
	private static function filter_ad_content( $ad_content, $tag_name, $options ) {
		// Only run the costly regex when the pattern can possibly match.
		if ( str_contains( $ad_content, 'document.write' ) ) {
			$ad_content = preg_replace( '#(document.write.+)</(.*)#', '$1<\/$2', $ad_content );
		}

		$id                           = count( self::$ads_for_placeholders );
		self::$ads_for_placeholders[] = [
			'id'       => $id,
			'tag'      => $tag_name,
			'position' => $options['position'],
			'ad'       => $ad_content,
		];

		return '%advads_placeholder_' . $id . '%';
	}

	/**
	 * Prepare output and flush the static placeholder store.
	 *
	 * @param string $content      Modified (DOM-serialized) content with placeholders.
	 * @param string $content_orig Unmodified original content.
	 *
	 * @return string Final content with ads injected.
	 */
	private static function prepare_output( $content, $content_orig ) {
		$content                    = self::inject_ads( $content, $content_orig, self::$ads_for_placeholders );
		self::$ads_for_placeholders = [];
		return $content;
	}

	/**
	 * Locate placeholder positions in the modified content and inject ads at the
	 * corresponding positions in the original content.
	 *
	 * Memory strategy:
	 * - $content (DOM version) is unset as soon as the placeholder scan is done.
	 * - $new_content is built incrementally using substr() slices of $content_orig —
	 *   we never duplicate $content_orig into another variable.
	 * - Match arrays are unset immediately after use.
	 *
	 * @param string $content              DOM-serialized content with ad placeholders.
	 * @param string $content_orig         Unmodified original content.
	 * @param array  $ads_for_placeholders Ad metadata array.
	 *
	 * @return string Content with ads injected.
	 */
	private static function inject_ads( $content, $content_orig, $ads_for_placeholders ) {
		// Normalize self-closing tag positions.
		foreach ( $ads_for_placeholders as &$ad ) {
			if (
				( 'prepend' === $ad['position'] || 'append' === $ad['position'] )
				&& isset( self::SELF_CLOSING_TAGS[ $ad['tag'] ] )
			) {
				$ad['position'] = 'after';
			}
		}
		unset( $ad );

		usort( $ads_for_placeholders, [ 'Advanced_Ads_In_Content_Injector', 'sort_ads_for_placehoders' ] );

		// Build tag patterns — skip duplicates during construction, not after.
		$alts = [];
		foreach ( $ads_for_placeholders as $ad ) {
			$tag = $ad['tag'];

			switch ( $ad['position'] ) {
				case 'before':
				case 'prepend':
					$pattern = "<{$tag}[^>]*>";
					break;
				case 'after':
					$pattern = isset( self::SELF_CLOSING_TAGS[ $tag ] ) ? "<{$tag}[^>]*>" : "</{$tag}>";
					break;
				case 'append':
					$pattern = "</{$tag}>";
					break;
				default:
					$pattern = null;
			}

			if ( null !== $pattern && ! in_array( $pattern, $alts, true ) ) {
				$alts[] = $pattern;
			}
		}

		$tag_regexp                 = implode( '|', $alts );
		$alts[]                     = '%advads_placeholder_(?:\d+)%';
		$tag_and_placeholder_regexp = implode( '|', $alts );
		unset( $alts );

		// Phase 1: scan DOM-serialized content to map placeholder → tag occurrence index.
		preg_match_all( "#{$tag_and_placeholder_regexp}#i", $content, $tag_matches );

		// $content (the DOM version, potentially larger than $content_orig) is no
		// longer needed — free it before we allocate $orig_tag_matches below.
		unset( $content );

		$count = 0;
		foreach ( $tag_matches[0] as $r ) {
			if ( preg_match( '/%advads_placeholder_(\d+)%/', $r, $result ) ) {
				$id = (int) $result[1];
				foreach ( $ads_for_placeholders as $n => $ad ) {
					if ( (int) $ad['id'] === $id ) {
						$ads_for_placeholders[ $n ]['offset'] = $count - 1;
						if ( 'before' === $ad['position'] || 'append' === $ad['position'] ) {
							$ads_for_placeholders[ $n ]['offset'] = $count;
						}
						break;
					}
				}
			} else {
				++$count;
			}
		}
		unset( $tag_matches );

		// Phase 2: find byte offsets of injection tags in the original content.
		preg_match_all( "#{$tag_regexp}#i", $content_orig, $orig_tag_matches, PREG_OFFSET_CAPTURE );

		// Phase 3: build output by streaming substrings of $content_orig.
		// We never copy $content_orig — only slice it with substr().
		$new_content = '';
		$pos         = 0;

		foreach ( $orig_tag_matches[0] as $n => $r ) {
			$to_inject = [];
			foreach ( $ads_for_placeholders as $ad ) {
				if ( isset( $ad['offset'] ) && $ad['offset'] === $n ) {
					$to_inject[] = $ad;
				}
			}

			foreach ( $to_inject as $item ) {
				$found_pos = ( 'before' === $item['position'] || 'append' === $item['position'] )
					? $r[1]
					: $r[1] + strlen( $r[0] );

				$new_content .= substr( $content_orig, $pos, $found_pos - $pos );
				$pos          = $found_pos;
				$new_content .= $item['ad'];
			}
		}

		unset( $orig_tag_matches, $ads_for_placeholders );

		$new_content .= substr( $content_orig, $pos );

		return $new_content;
	}

	/**
	 * Callback for usort() — sorts ads by position priority.
	 * Uses the POSITION_ORDER constant so no array is allocated per comparison.
	 *
	 * @param array $first  First ad.
	 * @param array $second Second ad.
	 *
	 * @return int
	 */
	public static function sort_ads_for_placehoders( $first, $second ) {
		$a = self::POSITION_ORDER[ $first['position'] ] ?? 99;
		$b = self::POSITION_ORDER[ $second['position'] ] ?? 99;
		return $a <=> $b;
	}

	/**
	 * Add a warning node to the Ad Health bar.
	 *
	 * @param array $nodes Existing nodes.
	 *
	 * @return array Modified nodes.
	 */
	public static function add_ad_health_node( $nodes ) {
		$nodes[] = [
			'type' => 1,
			'data' => [
				'parent' => 'advanced_ads_ad_health',
				'id'     => 'advanced_ads_ad_health_the_content_not_enough_elements',
				'title'  => sprintf(
					/* translators: %s stands for the name of the "Disable level limitation" option */
					__( 'Set <em>%s</em> to show more ads', 'advanced-ads' ),
					__( 'Disable level limitation', 'advanced-ads' )
				),
				'href'   => admin_url( '/admin.php?page=advanced-ads-settings#top#general' ),
				'meta'   => [
					'class'  => 'advanced_ads_ad_health_warning',
					'target' => '_blank',
				],
			],
		];
		return $nodes;
	}

	/**
	 * Get paths of ancestor nodes that should not contain ads.
	 *
	 * @param DOMXPath $xpath DOMXPath object.
	 *
	 * @return array Node path strings.
	 */
	private static function get_ancestors_to_limit( DOMXPath $xpath ) {
		$query = self::get_ancestors_to_limit_query();
		if ( ! $query ) {
			return [];
		}

		$node_list          = $xpath->query( $query );
		$ancestors_to_limit = [];

		foreach ( $node_list as $a ) {
			$ancestors_to_limit[] = $a->getNodePath();
		}

		unset( $node_list );
		return $ancestors_to_limit;
	}

	/**
	 * Remove paragraphs whose ancestors should not contain ads.
	 *
	 * Short-circuits immediately when no restricted ancestors exist,
	 * avoiding the inner loop entirely.
	 *
	 * Uses Str::starts_with() from Framework instead of stripos()
	 * since node paths are case-sensitive.
	 *
	 * @param array $paragraphs         Array of DOMNode objects.
	 * @param array $ancestors_to_limit Node paths of restricted ancestors.
	 *
	 * @return array Filtered array of DOMNode objects.
	 */
	private static function filter_by_ancestors_to_limit( $paragraphs, $ancestors_to_limit ) {
		if ( empty( $ancestors_to_limit ) ) {
			return $paragraphs;
		}

		$new_paragraphs = [];

		foreach ( $paragraphs as $paragraph ) {
			$node_path = $paragraph->getNodePath();
			foreach ( $ancestors_to_limit as $ancestor ) {
				if ( Str::starts_with( $ancestor . '/', $node_path ) ) {
					continue 2;
				}
			}
			$new_paragraphs[] = $paragraph;
		}

		return $new_paragraphs;
	}

	/**
	 * Build the XPath query to select ancestors that should not contain ads.
	 *
	 * @return string|false XPath query string, or false if none configured.
	 */
	private static function get_ancestors_to_limit_query() {
		$items = apply_filters(
			'advanced-ads-content-injection-nodes-without-ads',
			[
				[ 'node' => '.advads-stop-injection',    'type' => 'ancestor' ],
				[ 'node' => '.woopack-product-carousel', 'type' => 'ancestor' ],
				[ 'node' => '#wpautbox-%',               'type' => 'ancestor' ],
				[ 'node' => '.geodir-post-slider',       'type' => 'ancestor' ],
			]
		);

		$query = [];

		foreach ( $items as $p ) {
			$sel      = $p['node'];
			$sel_type = $sel[0];
			$sel      = substr( $sel, 1 );
			$rand_pos = strpos( $sel, '%' );
			$sel      = sanitize_html_class( str_replace( '%', '', $sel ) );

			if ( '.' === $sel_type ) {
				$query[] = false !== $rand_pos
					? "@class and contains(concat(' ', normalize-space(@class), ' '), ' $sel')"
					: "@class and contains(concat(' ', normalize-space(@class), ' '), ' $sel ')";
			} elseif ( '#' === $sel_type ) {
				$query[] = false !== $rand_pos
					? "@id and starts-with(@id, '$sel')"
					: "@id and @id = '$sel'";
			}
		}

		return $query ? '//*[' . implode( ' or ', $query ) . ']' : false;
	}

	/**
	 * Un-texturize the text.
	 *
	 * @param string $text Text to un-texturize.
	 *
	 * @return string Un-texturized text.
	 */
	private static function wp_untexturize( $text ) {
		return str_replace(
			[ '“', '”', '‘', '’' ],
			[ '"', '"', "'", "'" ],
			$text
		);
	}
}
