<?php
/**
 * Unit tests for shortcode helper functions.
 *
 * @package ListChildPagesShortcode
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for dknm_lcp_* helpers.
 */
class HelpersTest extends TestCase {

	/**
	 * Reset request-scoped stubs between tests.
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['dklcp_test_queried_id'],
			$GLOBALS['dklcp_test_current_post'],
			$GLOBALS['dklcp_test_posts'],
			$GLOBALS['dklcp_test_paths'],
			$GLOBALS['dklcp_test_intermediate_sizes']
		);
		parent::tearDown();
	}

	/**
	 * Sanitize class lists: drop invalid tokens, keep unique classes.
	 */
	public function test_sanitize_class_list() {
		$this->assertSame( '', dknm_lcp_sanitize_class_list( '' ) );
		$this->assertSame( 'alignleft', dknm_lcp_sanitize_class_list( 'alignleft' ) );
		$this->assertSame( 'foo bar', dknm_lcp_sanitize_class_list( " foo   bar  foo " ) );
		$this->assertSame( 'ok scriptbad', dknm_lcp_sanitize_class_list( 'ok <script>bad' ) );
		$this->assertSame( '', dknm_lcp_sanitize_class_list( '!!!' ) );
	}

	/**
	 * Order must be ASC or DESC; anything else falls back to DESC.
	 */
	public function test_normalize_order() {
		$this->assertSame( 'ASC', dknm_lcp_normalize_order( 'asc' ) );
		$this->assertSame( 'DESC', dknm_lcp_normalize_order( 'DESC' ) );
		$this->assertSame( 'DESC', dknm_lcp_normalize_order( 'sideways' ) );
		$this->assertSame( 'DESC', dknm_lcp_normalize_order( '' ) );
	}

	/**
	 * Legacy publish_date maps to date; id maps to ID; unknown values fall back.
	 */
	public function test_normalize_orderby() {
		$this->assertSame( 'date', dknm_lcp_normalize_orderby( 'publish_date' ) );
		$this->assertSame( 'date', dknm_lcp_normalize_orderby( 'date' ) );
		$this->assertSame( 'title', dknm_lcp_normalize_orderby( 'title' ) );
		$this->assertSame( 'menu_order', dknm_lcp_normalize_orderby( 'menu_order' ) );
		$this->assertSame( 'ID', dknm_lcp_normalize_orderby( 'id' ) );
		$this->assertSame( 'date', dknm_lcp_normalize_orderby( 'published' ) );
		$this->assertSame( 'rand', dknm_lcp_normalize_orderby( 'rand' ) );
		$this->assertSame( 'author', dknm_lcp_normalize_orderby( 'author' ) );
		$this->assertSame( 'type', dknm_lcp_normalize_orderby( 'type' ) );
		$this->assertSame( 'date', dknm_lcp_normalize_orderby( 'not-a-field' ) );
	}

	/**
	 * Image size keys: core sizes pass; unknown keys fall back to thumbnail.
	 */
	public function test_validate_image_size() {
		$this->assertSame( 'thumbnail', dknm_lcp_validate_image_size( '' ) );
		$this->assertSame( 'medium', dknm_lcp_validate_image_size( 'medium' ) );
		$this->assertSame( 'large', dknm_lcp_validate_image_size( 'LARGE' ) );
		$this->assertSame( 'full', dknm_lcp_validate_image_size( 'full' ) );
		$this->assertSame( 'thumbnail', dknm_lcp_validate_image_size( 'not-a-size' ) );

		$GLOBALS['dklcp_test_intermediate_sizes'] = array( 'hero' );
		$this->assertSame( 'hero', dknm_lcp_validate_image_size( 'hero' ) );
	}

	/**
	 * Empty / current / self resolve from queried object, then current page post.
	 */
	public function test_resolve_parent_current_context() {
		$GLOBALS['dklcp_test_queried_id'] = 42;
		$this->assertSame( 42, dknm_lcp_resolve_parent_id( '' ) );
		$this->assertSame( 42, dknm_lcp_resolve_parent_id( 'current' ) );
		$this->assertSame( 42, dknm_lcp_resolve_parent_id( 'self' ) );

		$GLOBALS['dklcp_test_queried_id'] = 0;
		$GLOBALS['dklcp_test_current_post'] = (object) array(
			'ID'        => 7,
			'post_type' => 'page',
		);
		$this->assertSame( 7, dknm_lcp_resolve_parent_id( 'current' ) );

		$GLOBALS['dklcp_test_current_post'] = (object) array(
			'ID'        => 8,
			'post_type' => 'post',
		);
		$this->assertSame( 0, dknm_lcp_resolve_parent_id( 'current' ) );
	}

	/**
	 * Numeric parent IDs only resolve when the post exists and is a page.
	 */
	public function test_resolve_parent_numeric_id() {
		$GLOBALS['dklcp_test_posts'][123] = (object) array(
			'ID'        => 123,
			'post_type' => 'page',
		);
		$GLOBALS['dklcp_test_posts'][99]  = (object) array(
			'ID'        => 99,
			'post_type' => 'post',
		);

		$this->assertSame( 123, dknm_lcp_resolve_parent_id( '123' ) );
		$this->assertSame( 0, dknm_lcp_resolve_parent_id( '99' ) );
		$this->assertSame( 0, dknm_lcp_resolve_parent_id( '404' ) );
	}

	/**
	 * Slug/path lookup uses get_page_by_path().
	 */
	public function test_resolve_parent_slug() {
		$GLOBALS['dklcp_test_paths']['about/company'] = (object) array(
			'ID'        => 55,
			'post_type' => 'page',
		);

		$this->assertSame( 55, dknm_lcp_resolve_parent_id( 'about/company' ) );
		$this->assertSame( 0, dknm_lcp_resolve_parent_id( 'missing' ) );
	}

	/**
	 * Without an explicit order, menu_order sorts ascending; other fields keep DESC.
	 */
	public function test_default_order() {
		$this->assertSame( 'ASC', dknm_lcp_default_order( '', 'menu_order' ) );
		$this->assertSame( 'DESC', dknm_lcp_default_order( '', 'date' ) );
		$this->assertSame( 'DESC', dknm_lcp_default_order( 'desc', 'menu_order' ) );
		$this->assertSame( 'ASC', dknm_lcp_default_order( 'asc', 'title' ) );
	}

	/**
	 * menu_order gets a title tie-breaker; other fields pass through.
	 */
	public function test_query_orderby() {
		$this->assertSame(
			array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			dknm_lcp_query_orderby( 'menu_order', 'ASC' )
		);
		$this->assertSame( 'date', dknm_lcp_query_orderby( 'date', 'DESC' ) );
	}

	/**
	 * 1.x function names still work.
	 */
	public function test_legacy_aliases() {
		$this->assertSame( 'ASC', dklcp_normalize_order( 'asc' ) );
		$this->assertSame( 'date', dklcp_normalize_orderby( 'publish_date' ) );
		$this->assertSame( 'foo', dklcp_sanitize_class_list( 'foo' ) );
		$this->assertTrue( function_exists( 'dklcp_listchildpages' ) );
		$this->assertTrue( function_exists( 'dklcp_register_shortcode' ) );
	}
}
