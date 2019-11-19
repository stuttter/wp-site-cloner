<?php

/**
 * Test that uploads are copied when a site is cloned.
 *
 * @since 0.2.0
 *
 * @group uploads
 */
class Test_Uploads extends WP_Site_Cloner_UnitTestCase {
	/**
	 * Our "private" uploads directory.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const UPLOADS = DIR_TESTROOT . '/uploads';

	/**
	 * Test that uploads are copied when a site is cloned.
	 *
	 * @group failure_010
	 */
	function test_uploads_copied() {
		// "upload" random number of media files.
		foreach ( $this->get_media_to_upload() as $file ) {
			$this->media_sideload( $file );
		}

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		// check that all the media exist in $to_site_id's upload directory.
		foreach ( get_posts( array( 'post_type' => 'attachment', 'fields' => 'ids' ) ) as $attachment_id ) {
			// check the attached file.
			$attached_file = get_attached_file( $attachment_id );

			$this->assertTrue( file_exists( $attached_file ), self::$known_to_failure_010_uploadds_not_copied );

			if ( wp_attachment_is_image( $attachment_id ) ) {
				// check each intermediate size.
				$meta = wp_get_attachment_metadata( $attachment_id );

				foreach ( $meta['sizes'] as $size ) {
					$attached_file = str_replace( basename( $attached_file ), $size['file'], $attached_file );

					$this->assertTrue( file_exists( $attached_file ), self::$known_to_failure_010_uploadds_not_copied );
				}
			}
		}
	}

	/**
	 * Get a random number of media files to "upload".
	 *
	 * At least one media file will always be returned.
	 *
	 * @since 0.2.0
	 *
	 * @return string[] Array of media filenames (full paths).
	 */
	protected function get_media_to_upload( $type = 'images' ) {
		$_media = glob( DIR_TESTDATA . "/{$type}/*" );

		$media  = array();
		foreach ( (array) array_rand( $_media, rand( 1, count( $_media ) ) ) as $idx ) {
			$media[] = $_media[ $idx ];
		}

		return $media;
	}

	/**
	 * Sideload a media file.
	 *
	 * @since 0.2.0
	 *
	 * @param string $file  Full path to media file to sideload.
	 * @return int|WP_Error The post ID of the attachment on success, or a WP_Error on failure.
	 */
	protected function media_sideload( $file ) {
		// Make a copy of this file as it gets moved during the file upload
		$tmp_name = wp_tempnam( $file );

		copy( $file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => basename( $file ),
			'type'     => mime_content_type( $file ),
			'error'    => 0,
			'size'     => filesize( $file ),
		);

		$post_id = media_handle_upload(
			'upload',
			0,
			array(),
			array(
				'action'    => 'test_upload',
				'test_form' => false,
			)
		);

		unset( $_FILES['upload'] );

		return $post_id;
	}

	/**
	 * Force WordPress to use our "private" uploads dir, instead of that of the site the test suite is running in.
	 *
	 * @since 0.2.0
	 *
	 * @param string[] $upload_dir {
	 *     Array of information about the upload directory.
	 *
	 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
	 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
	 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
	 *     @type string       $basedir Path without subdir.
	 *     @type string       $baseurl URL path without subdir.
	 *     @type string|false $error   False or error message.
 	 * }
	 * @return string[] `$upload_dir` with ouor values for `basedir` and `path` values.
	 */
	static function upload_dir( $upload_dir ) {
		$search  = WP_CONTENT_DIR . '/uploads';
		$replace = str_replace( ABSPATH, '', self::UPLOADS );

		$upload_dir['basedir'] = str_replace( $search, $replace, $upload_dir['basedir'] );
		$upload_dir['path']    = str_replace( $search, $replace, $upload_dir['path'] );

		return parent::upload_dir( $upload_dir );
	}

	/**
	 * Ensure all tests in this class use our upload dir and that our upload dir exists.
	 *
	 * @since 0.2.0
	 */
	function setUp() {
		parent::setUp();

		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		// ensure our uploads dir exists, since we delete it in tearDown() and WP won't recreate it in subsequent invocations.
		$upload_dir = $this->wp_get_upload_dir();

		wp_mkdir_p( $upload_dir['path'] );
	}

	/**
	 * Delete uploaded media after a test is run.
	 *
	 * @since 0.2.0
	 */
	function tearDown() {
		global $wp_filesystem;

		// delete uploaded media.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		foreach ( glob( self::UPLOADS . '/*' ) as $dir ) {
			$wp_filesystem->delete( $dir, true );
		}

		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		parent::tearDown();
	}
}
