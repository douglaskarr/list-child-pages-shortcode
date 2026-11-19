<?php
/**
 * Backward compatibility with the 1.x "dklcp_" prefix.
 *
 * Version 1.6.0 moved every function to the "dknm_lcp_" prefix. These thin
 * wrappers keep old function names, the old init callbacks, and the old
 * "dklcp/listchildpages/html" filter working for themes and snippets that
 * reference them. The [listchildpages] shortcode tag is unchanged.
 *
 * @package ListChildPagesShortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the 1.x output filter for sites that still hook it.
 *
 * @since 1.6.0
 *
 * @param string $out       Generated HTML.
 * @param int    $parent_id Parent page ID.
 * @param array  $atts      Normalized attributes.
 * @return string
 */
function dknm_lcp_apply_legacy_html_filter( $out, $parent_id, $atts ) {
	$legacy_hook = 'dklcp/listchildpages/html';
	if ( ! has_filter( $legacy_hook ) ) {
		return $out;
	}

	/**
	 * Filter the final HTML output of the listchildpages shortcode.
	 *
	 * @since 1.3.2
	 * @deprecated 1.6.0 Use dknm_lcp_html instead.
	 *
	 * @param string $out       The generated HTML.
	 * @param int    $parent_id The parent page ID.
	 * @param array  $atts      The normalized shortcode atts.
	 */
	return apply_filters_deprecated( $legacy_hook, array( $out, $parent_id, $atts ), '1.6.0', 'dknm_lcp_html' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy 1.x public filter kept for backward compatibility.
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy 1.x function names kept for backward compatibility.

if ( ! function_exists( 'dklcp_init_support' ) ) {
	/**
	 * Legacy alias of dknm_lcp_init_support().
	 *
	 * @deprecated 1.6.0
	 */
	function dklcp_init_support() {
		dknm_lcp_init_support();
	}
}

if ( ! function_exists( 'dklcp_sanitize_class_list' ) ) {
	/**
	 * Legacy alias of dknm_lcp_sanitize_class_list().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param string $class_list Class list.
	 * @return string
	 */
	function dklcp_sanitize_class_list( $class_list ) {
		return dknm_lcp_sanitize_class_list( $class_list );
	}
}

if ( ! function_exists( 'dklcp_normalize_order' ) ) {
	/**
	 * Legacy alias of dknm_lcp_normalize_order().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param string $order Order.
	 * @return string
	 */
	function dklcp_normalize_order( $order ) {
		return dknm_lcp_normalize_order( $order );
	}
}

if ( ! function_exists( 'dklcp_normalize_orderby' ) ) {
	/**
	 * Legacy alias of dknm_lcp_normalize_orderby().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param string $orderby Orderby.
	 * @return string
	 */
	function dklcp_normalize_orderby( $orderby ) {
		return dknm_lcp_normalize_orderby( $orderby );
	}
}

if ( ! function_exists( 'dklcp_resolve_parent_id' ) ) {
	/**
	 * Legacy alias of dknm_lcp_resolve_parent_id().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param string $parent_attr Parent attribute.
	 * @return int
	 */
	function dklcp_resolve_parent_id( $parent_attr ) {
		return dknm_lcp_resolve_parent_id( $parent_attr );
	}
}

if ( ! function_exists( 'dklcp_validate_image_size' ) ) {
	/**
	 * Legacy alias of dknm_lcp_validate_image_size().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param string $size Size.
	 * @return string
	 */
	function dklcp_validate_image_size( $size ) {
		return dknm_lcp_validate_image_size( $size );
	}
}

if ( ! function_exists( 'dklcp_listchildpages' ) ) {
	/**
	 * Legacy alias of dknm_lcp_listchildpages().
	 *
	 * @deprecated 1.6.0
	 *
	 * @param array|string $atts    Attributes.
	 * @param string       $content Enclosed content.
	 * @return string
	 */
	function dklcp_listchildpages( $atts, $content = '' ) {
		return dknm_lcp_listchildpages( $atts, $content );
	}
}

if ( ! function_exists( 'dklcp_register_shortcode' ) ) {
	/**
	 * Legacy alias of dknm_lcp_register_shortcode().
	 *
	 * @deprecated 1.6.0
	 */
	function dklcp_register_shortcode() {
		dknm_lcp_register_shortcode();
	}
}

// phpcs:enable
