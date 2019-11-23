<?php

/**
 * Test that string replacements are correct in the blogmeta table.
 *
 * @group blogmeta
 */
class Test_Blog_Meta extends WP_Site_Cloner_UnitTestCase {
	function test_blog_meta_string_value() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		$meta = array(
			'prefix' => $wpdb->prefix,
			'siteurl' => get_option( 'siteurl' ),
			'upload_dir_url' => $upload_dir['url'],
		);
		add_site_meta( get_current_blog_id(), 'test', $meta );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$expected = array(
			'prefix' => $wpdb->prefix,
			'siteurl' => get_option( 'siteurl' ),
			'upload_dir_url' => $upload_dir['url'],
		);
		$actual = get_site_meta( $to_site_id, 'test', true );

		$this->assertEquals( $expected, $actual );
	}
}
