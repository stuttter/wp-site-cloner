<?php

/**
 * Test that string replacements are correct in posts.
 *
 * @since 0.2.0
 *
 * @group posts
 */
class Test_Posts extends WP_Site_Cloner_UnitTestCase {
	/**
	 * Test that string replacements are correct in guids.
	 */
	function test_subdirectory_guids() {
		// add some posts.
		self::factory()->post->create_many( rand( 1, 5 ) );

		// save the guids for later.
		$from_guids = wp_list_pluck( get_posts(), 'guid' );

		// save the from siteurl for later.
		$from_siteurl = get_option( 'siteurl' );

		$to_site_id   = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$to_siteurl = get_option( 'siteurl' );

		$expected = array_map(
			function( $guid ) use ( $from_siteurl, $to_siteurl ) {
				return str_replace( $from_siteurl, $to_siteurl, $guid );
			},
			$from_guids
		);
		$actual   = wp_list_pluck( get_posts(), 'guid' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements are correct in guids.
	 */
	function test_subdomain_guids() {
		// add some posts.
		self::factory()->post->create_many( rand( 1, 5 ) );

		// save the guids for later.
		$from_guids = wp_list_pluck( get_posts(), 'guid' );

		// save the from siteurl for later.
		$from_siteurl = get_option( 'siteurl' );

		$to_site_id   = $this->subdomain_clone_site();

		switch_to_blog( $to_site_id );

		$to_siteurl = get_option( 'siteurl' );

		$expected = array_map(
			function( $guid ) use ( $from_siteurl, $to_siteurl ) {
				return str_replace( $from_siteurl, $to_siteurl, $guid );
			},
			$from_guids
		);
		$actual   = wp_list_pluck( get_posts(), 'guid' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements are correct in a post with a gallery block with a sub-directory clone.
	 */
	function test_subdirectory_gallery_block() {
		$post_id      = self::factory()->post->create_object( array( 'post_content' => $this->gen_gallery_block() ) );

		$to_site_id   = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$expected = $this->gen_gallery_block();
		$actual   = get_post( $post_id )->post_content;

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements are correct in a post with a gallery block with a sub-domain clone.
	 */
	function test_subddomain_gallery_block() {
		$post_id      = self::factory()->post->create_object( array( 'post_content' => $this->gen_gallery_block() ) );

		$to_site_id   = $this->subdomain_clone_site();

		switch_to_blog( $to_site_id );

		$expected = $this->gen_gallery_block();
		$actual   = get_post( $post_id )->post_content;

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Generate post content that mimics a gallery block with 2 images.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	protected function gen_gallery_block() {
		$gallery_block_template = <<<EOF
<!-- wp:gallery {"ids":[957,1016]} -->
<figure class="wp-block-gallery columns-2 is-cropped">
	<ul class="blocks-gallery-grid">
		<li class="blocks-gallery-item">
			<figure>
				<img src="{{{UPLOAD_DIR_URL}}}/image1.jpg" alt="" data-id="957" data-full-url="{{{UPLOAD_DIR_URL}}}/image1.jpg" data-link="{{{SITEURL}}}/image1/" class="wp-image-957"/>
			</figure>
		</li>
		<li class="blocks-gallery-item">
			<figure>
				<img src="{{{UPLOAD_DIR_URL}}}/image2.jpg" alt="" data-id="1016" data-full-url="{{{UPLOAD_DIR_URL}}}/image2.jpg" data-link="{{{SITEURL}}}/image2/" class="wp-image-1016"/>
			</figure>
		</li>
	</ul>
</figure>
<!-- /wp:gallery -->
EOF;

		$upload_dir = $this->wp_get_upload_dir();
		$gallery_block = str_replace(
			array( '{{{UPLOAD_DIR_URL}}}', '{{{SITEURL}}}' ),
			array( $upload_dir['url'], get_option( 'siteurl') ),
			$gallery_block_template
		);

		return $gallery_block;
	}
}