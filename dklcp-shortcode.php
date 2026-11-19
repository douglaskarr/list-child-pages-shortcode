<?php
/**
 * Plugin Name:       DK List Child Pages Shortcode
 * Plugin URI:        https://martech.zone/wordpress-list-child-pages-plugin/
 * Description:       List a page's child pages anywhere with the [listchildpages] shortcode, with optional featured images, excerpts, ordering, and CSS classes.
 * Version:           1.6.0
 * Requires at least: 4.6
 * Requires PHP:      7.4
 * Author:            DK New Media
 * Author URI:        https://dknewmedia.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       list-child-pages-shortcode
 *
 * Copyright 2019-2026 Douglas Karr
 *
 * @package ListChildPagesShortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DKNM_LCP_VERSION', '1.6.0' );
define( 'DKNM_LCP_SHORTCODE', 'listchildpages' );
define( 'DKNM_LCP_SHORTCODE_ALIAS', 'dknm_list_child_pages' );

/**
 * Ensure 'excerpt' support on pages (run in a safe lifecycle hook).
 */
function dknm_lcp_init_support() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'dknm_lcp_init_support' );

/**
 * Sanitize a space-delimited list of CSS classes.
 *
 * @param string $class_list Raw class list, possibly multiple classes separated by whitespace.
 * @return string A sanitized, space-delimited class list.
 */
function dknm_lcp_sanitize_class_list( $class_list ) {
	$classes   = preg_split( '/\s+/', (string) $class_list, -1, PREG_SPLIT_NO_EMPTY );
	$sanitized = array();

	foreach ( $classes as $class ) {
		$sanitized_class = sanitize_html_class( $class );
		if ( '' !== $sanitized_class ) {
			$sanitized[] = $sanitized_class;
		}
	}

	return implode( ' ', array_unique( $sanitized ) );
}

/**
 * Normalize the 'order' attribute (ASC|DESC).
 *
 * @param string $order Raw order.
 * @return string Normalized order.
 */
function dknm_lcp_normalize_order( $order ) {
	$order = strtoupper( trim( (string) $order ) );
	return in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
}

/**
 * Normalize the 'orderby' attribute with backward compatibility.
 *
 * Supports WordPress orderby values for pages. Maps legacy 'publish_date' to 'date'.
 *
 * @param string $orderby Raw orderby.
 * @return string Normalized orderby.
 */
function dknm_lcp_normalize_orderby( $orderby ) {
	$raw = strtolower( trim( (string) $orderby ) );

	$map = array(
		'publish_date' => 'date',
		'published'    => 'date',
		'date'         => 'date',
		'title'        => 'title',
		'name'         => 'name',
		'modified'     => 'modified',
		'menu_order'   => 'menu_order',
		'post__in'     => 'post__in',
		'id'           => 'ID',
		'rand'         => 'rand',
		'type'         => 'type',
		'author'       => 'author',
	);

	return isset( $map[ $raw ] ) ? $map[ $raw ] : 'date';
}

/**
 * Pick the sort direction.
 *
 * An explicit order attribute always wins. Without one, menu_order sorts
 * ascending (the order set under Page Attributes) and everything else keeps
 * the historical DESC default.
 *
 * @since 1.6.0
 *
 * @param string $order   Raw order attribute ('' when not supplied).
 * @param string $orderby Normalized orderby.
 * @return string ASC or DESC.
 */
function dknm_lcp_default_order( $order, $orderby ) {
	if ( '' === trim( (string) $order ) ) {
		return 'menu_order' === $orderby ? 'ASC' : 'DESC';
	}
	return dknm_lcp_normalize_order( $order );
}

/**
 * Build the WP_Query orderby argument.
 *
 * Pages share menu_order 0 unless an order is set, so menu_order sorts add
 * title as a stable tie-breaker.
 *
 * @since 1.6.0
 *
 * @param string $orderby Normalized orderby.
 * @param string $order   ASC or DESC.
 * @return string|array
 */
function dknm_lcp_query_orderby( $orderby, $order ) {
	if ( 'menu_order' === $orderby ) {
		return array(
			'menu_order' => $order,
			'title'      => 'ASC',
		);
	}
	return $orderby;
}

/**
 * Resolve a parent page from shortcode attribute.
 *
 * Accepts:
 * - numeric ID
 * - slug/path (string)
 * - 'current' or 'self' (string)
 * Falls back to current queried page (if any), then to current global post (if page).
 *
 * @param string $parent_attr Raw parent attribute.
 * @return int Parent page ID or 0 if none.
 */
function dknm_lcp_resolve_parent_id( $parent_attr ) {
	$parent_attr = trim( (string) $parent_attr );

	// Keywords for current context.
	if ( '' === $parent_attr || 'current' === strtolower( $parent_attr ) || 'self' === strtolower( $parent_attr ) ) {
		$parent_id = get_queried_object_id();
		if ( $parent_id ) {
			return (int) $parent_id;
		}
		$maybe_post = get_post();
		if ( $maybe_post && 'page' === $maybe_post->post_type ) {
			return (int) $maybe_post->ID;
		}
		return 0;
	}

	// Numeric ID.
	if ( ctype_digit( $parent_attr ) ) {
		$maybe = get_post( (int) $parent_attr );
		if ( $maybe && 'page' === $maybe->post_type ) {
			return (int) $maybe->ID;
		}
		return 0;
	}

	// Slug/path lookup.
	$path = sanitize_title_for_query( $parent_attr );
	$page = get_page_by_path( $path, OBJECT, 'page' );
	return $page ? (int) $page->ID : 0;
}

/**
 * Validate an image size key; fall back to 'thumbnail' if invalid.
 *
 * @param string $size Raw size.
 * @return string Valid size key.
 */
function dknm_lcp_validate_image_size( $size ) {
	$size = sanitize_key( $size );
	if ( '' === $size ) {
		return 'thumbnail';
	}

	$core_sizes = array( 'thumbnail', 'medium', 'large', 'full' );
	$all_sizes  = array_unique( array_merge( $core_sizes, get_intermediate_image_sizes() ) );

	if ( in_array( $size, $all_sizes, true ) ) {
		return $size;
	}

	return 'thumbnail';
}

/**
 * Shortcode callback: [listchildpages] (alias: [dknm_list_child_pages]).
 *
 * New attributes:
 * - parent: numeric page ID, slug/path, or 'current'/'self' to use the current page. Default: 'current'.
 * - size: image size for thumbnails (e.g., 'thumbnail', 'medium', 'large', 'full', or a registered custom size). Default: 'thumbnail'.
 *
 * Existing attributes (kept for backward compatibility):
 * - ifempty: string (HTML allowed; sanitized)
 * - order: ASC|DESC. Default DESC, or ASC when orderby is menu_order (1.6.0).
 * - orderby: maps 'publish_date' -> 'date' if provided
 * - ulclass, liclass, aclass: space-delimited class lists (sanitized)
 * - displayimage: yes/no (y/yes/t/true/1/on accepted)
 * - align: image alignment class (sanitized)
 *
 * The enclosed content (between opening/closing shortcode) is allowed safe HTML.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Enclosed shortcode content.
 * @return string
 */
function dknm_lcp_listchildpages( $atts, $content = '' ) {
	$defaults = array(
		'ifempty'      => '<p>' . __( 'No Records', 'list-child-pages-shortcode' ) . '</p>',
		'order'        => '',
		'orderby'      => 'publish_date', // normalized below.
		'ulclass'      => '',
		'liclass'      => '',
		'aclass'       => '',
		'displayimage' => 'no',
		'align'        => 'alignleft',
		'parent'       => 'current',
		'size'         => 'thumbnail',
	);

	$atts = shortcode_atts( $defaults, $atts, DKNM_LCP_SHORTCODE );

	// Normalize and sanitize attributes.
	$orderby     = dknm_lcp_normalize_orderby( $atts['orderby'] );
	$order       = dknm_lcp_default_order( $atts['order'], $orderby );
	$ulclass     = dknm_lcp_sanitize_class_list( $atts['ulclass'] );
	$liclass     = dknm_lcp_sanitize_class_list( $atts['liclass'] );
	$aclass      = dknm_lcp_sanitize_class_list( $atts['aclass'] );
	$align_class = dknm_lcp_sanitize_class_list( $atts['align'] );
	$size_key    = dknm_lcp_validate_image_size( $atts['size'] );

	$truthy     = array( 'y', 'yes', 't', 'true', '1', 'on', 1, true );
	$show_image = in_array( strtolower( (string) $atts['displayimage'] ), $truthy, true );

	// Resolve the parent ID from attribute.
	$parent_id = dknm_lcp_resolve_parent_id( $atts['parent'] );

	// If we still don't have a parent, return ifempty safely.
	if ( ! $parent_id ) {
		return wp_kses_post( $atts['ifempty'] );
	}

	// Query child IDs so the shortcode does not take over the main Loop.
	$q = new WP_Query(
		array(
			'post_type'           => 'page',
			'posts_per_page'      => -1,
			'post_parent'         => $parent_id,
			'orderby'             => dknm_lcp_query_orderby( $orderby, $order ),
			'order'               => $order,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'fields'              => 'ids',
		)
	);

	// Prepare intro content and ifempty output with safe HTML.
	$intro_html   = $content ? wp_kses_post( $content ) : '';
	$ifempty_html = wp_kses_post( $atts['ifempty'] );

	if ( ! $q->have_posts() ) {
		return $ifempty_html;
	}

	$out  = '';
	$out .= $intro_html;
	$out .= '<ul' . ( $ulclass ? ' class="' . esc_attr( $ulclass ) . '"' : '' ) . '>';

	foreach ( $q->posts as $child_id ) {
		$child_id    = (int) $child_id;
		$child_title = get_the_title( $child_id );
		$child_link  = get_permalink( $child_id );

		$out .= '<li' . ( $liclass ? ' class="' . esc_attr( $liclass ) . '"' : '' ) . '>';

		// Optional featured image.
		if ( $show_image && has_post_thumbnail( $child_id ) ) {
			$img = get_the_post_thumbnail(
				$child_id,
				$size_key,
				array(
					'class' => $align_class ? $align_class : '',
					'alt'   => esc_attr( wp_strip_all_tags( $child_title ) ),
				)
			);

			if ( $img ) {
				$out .= '<a' . ( $aclass ? ' class="' . esc_attr( $aclass ) . '"' : '' ) .
					' href="' . esc_url( $child_link ) . '"' .
					' title="' . esc_attr( wp_strip_all_tags( $child_title ) ) . '">';
				$out .= $img . '</a>';
			}
		}

		// Linked title.
		$out .= '<a' . ( $aclass ? ' class="' . esc_attr( $aclass ) . '"' : '' ) .
			' href="' . esc_url( $child_link ) . '"' .
			' title="' . esc_attr( wp_strip_all_tags( $child_title ) ) . '">';
		$out .= esc_html( $child_title ) . '</a>';

		// Optional excerpt.
		if ( has_excerpt( $child_id ) ) {
			$excerpt = get_the_excerpt( $child_id );
			if ( '' !== $excerpt ) {
				$out .= ' - ' . wp_kses_post( $excerpt );
			}
		}

		$out .= '</li>';
	}

	$out .= '</ul>';

	$filtered_atts = array(
		'order'        => $order,
		'orderby'      => $orderby,
		'ulclass'      => $ulclass,
		'liclass'      => $liclass,
		'aclass'       => $aclass,
		'displayimage' => $show_image,
		'align'        => $align_class,
		'size'         => $size_key,
	);

	/** This filter is documented in includes/legacy.php */
	$out = dknm_lcp_apply_legacy_html_filter( $out, $parent_id, $filtered_atts );

	/**
	 * Filter the final HTML output of the shortcode.
	 *
	 * @since 1.6.0
	 *
	 * @param string $out       The generated HTML.
	 * @param int    $parent_id The parent page ID.
	 * @param array  $atts      The normalized shortcode atts.
	 */
	return apply_filters( 'dknm_lcp_html', $out, $parent_id, $filtered_atts );
}

/**
 * Register shortcode on init.
 */
function dknm_lcp_register_shortcode() {
	add_shortcode( DKNM_LCP_SHORTCODE, 'dknm_lcp_listchildpages' );
	add_shortcode( DKNM_LCP_SHORTCODE_ALIAS, 'dknm_lcp_listchildpages' );
}
add_action( 'init', 'dknm_lcp_register_shortcode' );

require_once __DIR__ . '/includes/legacy.php';
