<?php
/**
 * PHPUnit bootstrap for helper-function tests (no WordPress runtime).
 *
 * @package ListChildPagesShortcode
 */

define( 'ABSPATH', sys_get_temp_dir() . '/' );

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

/**
 * No-op WordPress hook registration.
 *
 * @param string   $hook          Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arg count.
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}

/**
 * No-op shortcode registration.
 *
 * @param string   $tag      Shortcode tag.
 * @param callable $callback Callback.
 */
function add_shortcode( $tag, $callback ) {}

/**
 * Identity translation stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * Minimal sanitize_html_class() compatible with WordPress core.
 *
 * @param string $class    Class name.
 * @param string $fallback Fallback.
 * @return string
 */
function sanitize_html_class( $class, $fallback = '' ) {
	$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	if ( '' === $sanitized && '' !== $fallback ) {
		return $fallback;
	}
	return $sanitized;
}

/**
 * Minimal sanitize_key().
 *
 * @param string $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

/**
 * Minimal sanitize_title_for_query().
 *
 * @param string $title Title.
 * @return string
 */
function sanitize_title_for_query( $title ) {
	$title = strtolower( trim( (string) $title ) );
	$title = preg_replace( '/[^a-z0-9\/_\-]/', '', $title );
	return $title;
}

/**
 * Intermediate image sizes stub.
 *
 * @return array
 */
function get_intermediate_image_sizes() {
	return isset( $GLOBALS['dklcp_test_intermediate_sizes'] )
		? $GLOBALS['dklcp_test_intermediate_sizes']
		: array();
}

/**
 * Queried object ID stub.
 *
 * @return int
 */
function get_queried_object_id() {
	return isset( $GLOBALS['dklcp_test_queried_id'] ) ? (int) $GLOBALS['dklcp_test_queried_id'] : 0;
}

/**
 * get_post() stub. With no ID, returns the current test post.
 *
 * @param int|null $post Post ID.
 * @return object|null
 */
function get_post( $post = null ) {
	if ( null === $post || 0 === $post ) {
		return isset( $GLOBALS['dklcp_test_current_post'] ) ? $GLOBALS['dklcp_test_current_post'] : null;
	}
	$id = (int) $post;
	return isset( $GLOBALS['dklcp_test_posts'][ $id ] ) ? $GLOBALS['dklcp_test_posts'][ $id ] : null;
}

/**
 * get_page_by_path() stub.
 *
 * @param string $path Path.
 * @return object|null
 */
function get_page_by_path( $path ) {
	$path = (string) $path;
	return isset( $GLOBALS['dklcp_test_paths'][ $path ] ) ? $GLOBALS['dklcp_test_paths'][ $path ] : null;
}

require dirname( __DIR__ ) . '/dklcp-shortcode.php';
