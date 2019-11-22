<?php

/**
 * WP Site Cloner test case base class.
 *
 * @since 0.2.0
 */
class WP_Site_Cloner_UnitTestCase extends WP_UnitTestCase {
	/**
	 * Ensure there is a current user...since the current user is by default used as the admin user for cloned sites.
	 *
	 * @since 0.2.0
	 */
	function setUp() {
		parent::setUp();

		wp_set_current_user( 1 );
	}

	/**
	 * Convenience method to clone a site.
	 *
	 * @since 0.2.0
	 *
	 * @param string[] $args {
	 *     @type string $domain The domain for the new site.
	 * 	   @type string $path   The path for the new site.
	 * 	   @type string $title' The blogname for the new site.
	 * 	   @type array  $meta ??
	 * 	   @type int    $from_site_id The ID of the site to clone.
	 * 	   @type int    $to_network_id The network ID for the new site.
	 * 	   @type int    $user_id The user ID for the admin for the new site.
	 * 	   @type string $callback Function/method/action to use to create the new site.
	 * 	   @type string $cleanup' FUnction/method/action to call after the new site is created.
	 * }
	 * @return int The site ID of the cloned site.
	 */
	protected function clone_site( $args ) {
		$to_site_id = wp_clone_site( $args );

		// check that the cloned site id was correctly returned.
		$this->assertNotEmpty( $to_site_id, 'to_site_id is empty' );
		$this->assertTrue( true, is_int( $to_site_id ) );

		// check that the domain and path on the cloned site are correct.
		$to_site = get_site( $to_site_id );

		$this->assertEquals( $args['domain'], $to_site->domain );
		$this->assertEquals( trailingslashit( $args['path'] ), $to_site->path );

		return $to_site_id;
	}

	/**
	 * Convenience method to clone a site as a sub-directory site.
	 *
	 * @since 0.2.0
	 *
	 * @param string[] $args {
	 *     @type string $domain The domain for the new site.
	 * 	   @type string $path   The path for the new site.
	 * 	   @type string $title' The blogname for the new site.
	 * 	   @type array  $meta ??
	 * 	   @type int    $from_site_id The ID of the site to clone.
	 * 	   @type int    $to_network_id The network ID for the new site.
	 * 	   @type int    $user_id The user ID for the admin for the new site.
	 * 	   @type string $callback Function/method/action to use to create the new site.
	 * 	   @type string $cleanup' FUnction/method/action to call after the new site is created.
	 * }
	 * @return int The cloned site ID.
	 */
	protected function subdirectory_clone_site( $args = array() ) {
		$default_args = array(
			'domain'       => '',
			'path'         => '/test',
			'title'        => 'Test',
			'from_site_id' => get_main_site_id(),
		);
		$args = wp_parse_args( $args, $default_args );

		if ( ! $args['domain'] ) {
			$from_site      = get_site( $args['from_site_id'] );
			$args['domain'] = $from_site->domain;
		}

		return $this->clone_site( $args );
	}

	/**
	 * Convenience method to clone a site as a sub-domain site.
	 *
	 * @since 0.2.0
	 *
	 * @param string[] $args {
	 *     @type string $domain The domain for the new site.
	 * 	   @type string $path   The path for the new site.
	 * 	   @type string $title' The blogname for the new site.
	 * 	   @type array  $meta ??
	 * 	   @type int    $from_site_id The ID of the site to clone.
	 * 	   @type int    $to_network_id The network ID for the new site.
	 * 	   @type int    $user_id The user ID for the admin for the new site.
	 * 	   @type string $callback Function/method/action to use to create the new site.
	 * 	   @type string $cleanup' FUnction/method/action to call after the new site is created.
	 * }
	 * @return int The cloned site ID.
	 */
	protected function subdomain_clone_site( $args = array() ) {
		$default_args = array(
			'domain'       => '',
			'path'         => '/',
			'from_site_id' => get_main_site_id(),
		);
		$args = wp_parse_args( $args, $default_args );

		if ( ! $args['domain'] ) {
			$from_site      = get_site( $args['from_site_id'] );
			$args['domain'] = "test.{$from_site->domain}";
		}

		return $this->clone_site( $args );
	}

	/**
	 * Retrieves uploads directory information, in a "light weight" as it doesn’t attempt to create the uploads directory,
	 * that returns the correct information even when {@link https://developer.wordpress.org/reference/functions/ms_is_switched ms_is_switched()}
	 * is true.
	 *
	 * @since 0.2.0
	 *
	 * @return array {
	 *     Array of information about the upload directory.
	 *
	 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
	 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
	 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
	 *     @type string       $basedir Path without subdir.
	 *     @type string       $baseurl URL path without subdir.
	 *     @type string|false $error   False or error message.
 	 * }
	 */
	static function wp_get_upload_dir() {
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		$upload_dir = wp_get_upload_dir();

		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		return $upload_dir;
	}

	/**
	 * Correct the values for the `baseurl` and `url` keys when
	 * {@link https://developer.wordpress.org/reference/functions/ms_is_switched ms_is_switched()} is true.
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
	 * @return string[] `$upload_dir` with correct `baseurl` and `url` values.
	 */
	static function upload_dir( $upload_dir ) {
		if ( ms_is_switched() ) {
			$main_siteurl          = get_blog_option( get_main_site_id(), 'siteurl' );
			$siteurl               = get_option( 'siteurl' );

			$upload_dir['baseurl'] = str_replace( $main_siteurl, $siteurl, $upload_dir['baseurl'] );
			$upload_dir['url']     = str_replace( $main_siteurl, $siteurl, $upload_dir['url'] );
		}

		return $upload_dir;
	}
}
