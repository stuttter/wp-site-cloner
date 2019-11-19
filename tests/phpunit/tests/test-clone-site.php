<?php

/**
 * Test that basic cloning works.
 *
 * @since 0.2.0
 *
 * @group clone-site
 */
class Test_Clone_Site extends WP_Site_Cloner_UnitTestCase {
	/**
	 * Test that a sub-directory clone is successful.
	 *
	 * Other tests in this suite are encouraged to use `WP_Site_Cloner_UnitTestCase::subdirectory_clone_()`
	 * when creating sub-directory clones. That method does what this test does...and using it will make
	 * those other tests more readable.
	 */
	function test_subdirectory_clone_site() {
		$from_site_id = get_main_site_id();
		$from_site    = get_site( $from_site_id );

		$args = array(
			'domain'       => $from_site->domain,
			'path'         => '/test',
			'title'        => 'Test',
			'from_site_id' => $from_site_id,
		);
		$to_site_id = wp_clone_site( $args );

		// check that the cloned site id was correctly returned.
		$this->assertNotEmpty( $to_site_id, 'to_site_id is empty' );
		$this->assertTrue( true, is_int( $to_site_id ) );

		// check that the domain and path on the cloned site are correct.
		$to_site = get_site( $to_site_id );

		$this->assertEquals( $args['domain'], $to_site->domain );
		$this->assertEquals( trailingslashit( $args['path'] ), $to_site->path );
	}

	/**
	 * Test that a sub-domain clone is successful.
	 *
	 * Other tests in this suite are encouraged to use `WP_Site_Cloner_UnitTestCase::subdomain_clone()`
	 * when creating sub-domain clones. That method does what this test does...and using it will make
	 * those other tests more readable.
	 */
	function test_subdomain_clone_site() {
		$from_site_id = get_main_site_id();
		$from_site    = get_site( $from_site_id );

		$args = array(
			'domain'       => "test.{$from_site->domain}",
			'path'         => '/',
			'title'        => 'Test',
			'from_site_id' => $from_site_id,
		);
		$to_site_id = wp_clone_site( $args );

		$this->assertNotEmpty( $to_site_id, 'to_site_id is empty' );
		$this->assertTrue( true, is_int( $to_site_id ) );

		$to_site = get_site( $to_site_id );

		$this->assertEquals( $args['domain'], $to_site->domain );
		$this->assertEquals( trailingslashit( $args['path'] ), $to_site->path );
	}
}
