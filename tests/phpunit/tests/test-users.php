<?php

/**
 * Test that users are copied when a site is cloned.
 *
 * @group users
 */
class Test_Users extends WP_Site_Cloner_UnitTestCase {
	/**
	 * Test that users are copied when a site is cloned.
	 *
	 * @group failure_010
	 */
	function test_users_copied() {
		// add some users to the from site.
		foreach ( array_keys( wp_roles()->get_names() ) as $role ) {
			if ( 'administrator' !== $role ) {
				self::factory()->user->create_many( 1, array( 'role' => $role ) );
			}
		}

		$from_users = $this->get_comparable_users();

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		// we must flush the cache(s) for this test to work.
		// @todo I think there is a bug in core.  When get_users() (and/or get_user_by()) is called
		//       and then switch_to_blog() is called, the subsequent calls to get_users() (and/or get_user_by()
		//       will return the same results (except for the cap_key and site_id props) as before the switch.
		self::flush_cache();

		$to_users = $this->get_comparable_users();

		$this->assertEquals( count( $from_users ), count( $to_users ), self::$known_to_failure_010_users_not_copied );
		$this->assertEquals( $from_users, $to_users, self::$known_to_failure_010_users_not_copied );
	}

	/**
	 * Get a "comparible" array of users on the current site.
	 *
	 * By "comparible" we mean that those properties that are supposed to be different between sites
	 * (i.e., `cap_key` and `site_id`) are removed, so that we can do `$this->assertEquals()`.
	 *
	 * @since 0.2.0
	 *
	 * @return array Array of users, where the users are associative arrays whose keys are WP_User properties.
	 */
	protected function get_comparable_users() {
		$users = get_users();
		// remove the cap key property, because it is supposed to be different for users in different sites.
		foreach ( $users as &$user ) {
			// we get_object_vars() because the site_id property is private and we can't unset it directly.
			$user = get_object_vars( $user );
			unset( $user['cap_key'] );
		}

		return $users;
	}
}
