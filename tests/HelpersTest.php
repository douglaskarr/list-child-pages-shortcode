<?php
/**
 * Unit tests for shortcode helper functions.
 *
 * @package ListChildPagesShortcode
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for dklcp_* helpers.
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
		$this->assertSame( '', dklcp_sanitize_class_list( '' ) );
		$this->assertSame( 'alignleft', dklcp_sanitize_class_list( 'alignleft' ) );
		$this->assertSame( 'foo bar', dklcp_sanitize_class_list( " foo   bar  foo " ) );
		$this->assertSame( 'ok scriptbad', dklcp_sanitize_class_list( 'ok <script>bad' ) );
		$this->assertSame( '', dklcp_sanitize_class_list( '!!!' ) );
	}

	/**
	 * Order must be ASC or DESC; anything else falls back to DESC.
	 */
	public function test_normalize_order() {
		$this->assertSame( 'ASC', dklcp_normalize_order( 'asc' ) );
		$this->assertSame( 'DESC', dklcp_normalize_order( 'DESC' ) );
		$this->assertSame( 'DESC', dklcp_normalize_order( 'sideways' ) );
		$this->assertSame( 'DESC', dklcp_normalize_order( '' ) );
	}

	/**
	 * Legacy publish_date maps to date; id maps to ID; unknown values fall back.
	 */
	public function test_normalize_orderby() {
		$this->assertSame( 'date', dklcp_normalize_orderby( 'publish_date' ) );
		$this->assertSame( 'date', dklcp_normalize_orderby( 'date' ) );
		$this->assertSame( 'title', dklcp_normalize_orderby( 'title' ) );
		$this->assertSame( 'menu_order', dklcp_normalize_orderby( 'menu_order' ) );
		$this->assertSame( 'ID', dklcp_normalize_orderby( 'id' ) );
		$this->assertSame( 'date', dklcp_normalize_orderby( 'not-a-field' ) );
	}

	/**
	 * Image size keys: core sizes pass; unknown keys fall back to thumbnail.
	 */
	public function test_validate_image_size() {
		$this->assertSame( 'thumbnail', dklcp_validate_image_size( '' ) );
		$this->assertSame( 'medium', dklcp_validate_image_size( 'medium' ) );
		$this->assertSame( 'large', dklcp_validate_image_size( 'LARGE' ) );
		$this->assertSame( 'full', dklcp_validate_image_size( 'full' ) );
		$this->assertSame( 'thumbnail', dklcp_validate_image_size( 'not-a-size' ) );

		$GLOBALS['dklcp_test_intermediate_sizes'] = array( 'hero' );
		$this->assertSame( 'hero', dklcp_validate_image_size( 'hero' ) );
	}

	/**
	 * Empty / current / self resolve from queried object, then current page post.
	 */
	public function test_resolve_parent_current_context() {
		$GLOBALS['dklcp_test_queried_id'] = 42;
		$this->assertSame( 42, dklcp_resolve_parent_id( '' ) );
		$this->assertSame( 42, dklcp_resolve_parent_id( 'current' ) );
		$this->assertSame( 42, dklcp_resolve_parent_id( 'self' ) );

		$GLOBALS['dklcp_test_queried_id'] = 0;
		$GLOBALS['dklcp_test_current_post'] = (object) array(
			'ID'        => 7,
			'post_type' => 'page',
		);
		$this->assertSame( 7, dklcp_resolve_parent_id( 'current' ) );

		$GLOBALS['dklcp_test_current_post'] = (object) array(
			'ID'        => 8,
			'post_type' => 'post',
		);
		$this->assertSame( 0, dklcp_resolve_parent_id( 'current' ) );
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

		$this->assertSame( 123, dklcp_resolve_parent_id( '123' ) );
		$this->assertSame( 0, dklcp_resolve_parent_id( '99' ) );
		$this->assertSame( 0, dklcp_resolve_parent_id( '404' ) );
	}

	/**
	 * Slug/path lookup uses get_page_by_path().
	 */
	public function test_resolve_parent_slug() {
		$GLOBALS['dklcp_test_paths']['about/company'] = (object) array(
			'ID'        => 55,
			'post_type' => 'page',
		);

		$this->assertSame( 55, dklcp_resolve_parent_id( 'about/company' ) );
		$this->assertSame( 0, dklcp_resolve_parent_id( 'missing' ) );
	}
}
