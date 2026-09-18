<?php
/**
 * Plugin Name: List Child Pages Shortcode
 * Plugin URI: https://martech.zone/list-child-pages-shortcode/
 * Description: Provides a shortcode to list child pages on a parent page with an optional featured image and excerpt. Usage: [listchildpages ifempty="No child pages" orderby="publish_date" order="desc" displayimage="YES" align="alignleft" ulclass="" liclass="" aclass="" parent="current" size="thumbnail"]Here are our child pages:[/listchildpages]
 * Version: 1.4.1
 * Author: Douglas Karr
 * Author URI: https://dknewmedia.com/
 * License: GPL2
 * Text Domain: listchildpages-shortcode
 *
 * Copyright 2019-2025 Douglas Karr
 *
 * @package ListChildPagesShortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure 'excerpt' support on pages (run in a safe lifecycle hook).
 */
function dklcp_init_support() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'dklcp_init_support' );

/**
 * Sanitize a space-delimited list of CSS classes.
 *
 * @param string $class_list Raw class list, possibly multiple classes separated by whitespace.
 * @return string A sanitized, space-delimited class list.
 */
function dklcp_sanitize_class_list( $class_list ) {
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
function dklcp_normalize_order( $order ) {
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
function dklcp_normalize_orderby( $orderby ) {
	$raw = strtolower( trim( (string) $orderby ) );
	if ( 'publish_date' === $raw ) {
		return 'date';
	}

	$allowed = array(
		'date',
		'title',
		'name',
		'modified',
		'menu_order',
		'post__in',
		'ID',
		'id',
	);

	if ( 'id' === $raw ) {
		return 'ID';
	}

	return in_array( $raw, $allowed, true ) ? $raw : 'date';
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
function dklcp_resolve_parent_id( $parent_attr ) {
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
function dklcp_validate_image_size( $size ) {
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
 * Shortcode callback: [listchildpages]
 *
 * New attributes:
 * - parent: numeric page ID, slug/path, or 'current'/'self' to use the current page. Default: 'current'.
 * - size: image size for thumbnails (e.g., 'thumbnail', 'medium', 'large', 'full', or a registered custom size). Default: 'thumbnail'.
 *
 * Existing attributes (kept for backward compatibility):
 * - ifempty: string (HTML allowed; sanitized)
 * - order: ASC|DESC
 * - orderby: maps 'publish_date' -> 'date' if provided
 * - ulclass, liclass, aclass: space-delimited class lists (sanitized)
 * - displayimage: yes/no (y/yes/t/true/1 accepted)
 * - align: image alignment class (sanitized)
 *
 * The enclosed content (between opening/closing shortcode) is allowed safe HTML.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string       $content Enclosed shortcode content.
 * @return string
 */
function dklcp_listchildpages( $atts, $content = '' ) {
	$defaults = array(
		'ifempty'      => '<p>' . __( 'No Records', 'listchildpages-shortcode' ) . '</p>',
		'order'        => 'DESC',
		'orderby'      => 'publish_date', // normalized below.
		'ulclass'      => '',
		'liclass'      => '',
		'aclass'       => '',
		'displayimage' => 'no',
		'align'        => 'alignleft',
		'parent'       => 'current',
		'size'         => 'thumbnail',
	);

	$atts = shortcode_atts( $defaults, $atts, 'listchildpages' );

	// Normalize and sanitize attributes.
	$order       = dklcp_normalize_order( $atts['order'] );
	$orderby     = dklcp_normalize_orderby( $atts['orderby'] );
	$ulclass     = dklcp_sanitize_class_list( $atts['ulclass'] );
	$liclass     = dklcp_sanitize_class_list( $atts['liclass'] );
	$aclass      = dklcp_sanitize_class_list( $atts['aclass'] );
	$align_class = dklcp_sanitize_class_list( $atts['align'] );
	$size_key    = dklcp_validate_image_size( $atts['size'] );

	$truthy     = array( 'y', 'yes', 't', 'true', '1', 1, true );
	$show_image = in_array( strtolower( (string) $atts['displayimage'] ), $truthy, true );

	// Resolve the parent ID from attribute.
	$parent_id = dklcp_resolve_parent_id( $atts['parent'] );

	// If we still don't have a parent, return ifempty safely.
	if ( ! $parent_id ) {
		return wp_kses_post( $atts['ifempty'] );
	}

	// Query child pages.
	$q = new WP_Query(
		array(
			'post_type'           => 'page',
			'posts_per_page'      => -1,
			'post_parent'         => $parent_id,
			'orderby'             => $orderby,
			'order'               => $order,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	// Prepare intro content and ifempty output with safe HTML.
	$intro_html   = $content ? wp_kses_post( $content ) : '';
	$ifempty_html = wp_kses_post( $atts['ifempty'] );

	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return $ifempty_html;
	}

	$out  = '';
	$out .= $intro_html;
	$out .= '<ul' . ( $ulclass ? ' class="' . esc_attr( $ulclass ) . '"' : '' ) . '>';

	while ( $q->have_posts() ) {
		$q->the_post();

		$child_id    = get_the_ID();
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

	wp_reset_postdata();

	/**
	 * Filter the final HTML output of the listchildpages shortcode.
	 *
	 * @since 1.3.2
	 * @since 1.4.0 Added $size to the filtered args.
	 *
	 * @param string $out       The generated HTML.
	 * @param int    $parent_id The parent page ID.
	 * @param array  $atts      The normalized shortcode atts.
	 */
	$out = apply_filters(
		'dklcp/listchildpages/html', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- existing public filter.
		$out,
		$parent_id,
		array(
			'order'        => $order,
			'orderby'      => $orderby,
			'ulclass'      => $ulclass,
			'liclass'      => $liclass,
			'aclass'       => $aclass,
			'displayimage' => $show_image,
			'align'        => $align_class,
			'size'         => $size_key,
		)
	);

	return $out;
}

/**
 * Register shortcode on init.
 */
function dklcp_register_shortcode() {
	add_shortcode( 'listchildpages', 'dklcp_listchildpages' );
}
add_action( 'init', 'dklcp_register_shortcode' );
