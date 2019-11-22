<?php

/**
 * Test that string replacements in option names and values are correct.
 *
 * @since 0.2.0
 *
 * @group options
 */
class Test_Options extends WP_Site_Cloner_UnitTestCase {
	/**
	 * Test that option names have string replacements correctly performed on them.
	 */
	function test_option_name() {
		global $wpdb;

		$upload_dir            = $this->wp_get_upload_dir();
		$prefix_option         = $wpdb->prefix;
		$siteurl_option        = get_option( 'siteurl');
		$upload_dir_url_option = $upload_dir['url'];

		add_option( $prefix_option, 'test' );
		add_option( $siteurl_option, 'test' );
		add_option( $upload_dir_url_option, 'test' );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		// the option names added in the from site should not exist in the to site.
		$this->assertFalse( get_option( $prefix_option ) );
		$this->assertFalse( get_option( $siteurl_option ) );
		$this->assertFalse( get_option( $upload_dir_url_option ) );

		// but their "replacements" should and should have the values added in the from site.
		$upload_dir            = $this->wp_get_upload_dir();
		$prefix_option         = $wpdb->prefix;
		$siteurl_option        = get_option( 'siteurl');
		$upload_dir_url_option = $upload_dir['url'];

		$this->assertEquals( 'test', get_option( $prefix_option ) );
		$this->assertEquals( 'test', get_option( $siteurl_option ) );
		$this->assertEquals( 'test', get_option( $upload_dir_url_option ) );
	}

	/**
	 * Test that option values that are strings have string replacements correctly performed on them.
	 */
	function test_option_value_string() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		add_option( 'test_prefix', $wpdb->prefix );
		add_option( 'test_siteurl', get_option( 'siteurl' ) );
		add_option( 'test_upload_url', $upload_dir['url'] );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$this->assertEquals( $wpdb->prefix, get_option( 'test_prefix' ) );
		$this->assertEquals( get_option( 'siteurl' ), get_option( 'test_siteurl' ) );
		$this->assertEquals( get_option( 'test_upload_url' ), $upload_dir['url'] );
	}

	/**
	 * Test that string replacements happen correctly in array values.
	 */
	function test_option_value_array() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		$option = array(
			$wpdb->prefix,
			get_option( 'siteurl' ),
			$upload_dir['url']
		);
		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$expected   = array(
			$wpdb->prefix,
			get_option( 'siteurl' ),
			$upload_dir['url']
		);
		$actual = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements happen correctly in array values.
	 */
	function test_option_value_array_key() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		$option = array(
			$wpdb->prefix           => 'test',
			// ensure the next key/value pair doesn't get overwritten when the first key gets replaced
			"{$wpdb->prefix}2_"     => 'another test',
			get_option( 'siteurl' ) => 'test',
			$upload_dir['url']      => 'test',
		);
		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$expected   = array(
			$wpdb->prefix           => 'test',
			"{$wpdb->prefix}2_"     => 'another test',
			get_option( 'siteurl' ) => 'test',
			$upload_dir['url']      => 'test',
		);
		$actual = get_option( 'test' );

		// note: we use === comparison because the order the keys is significant
		//       and assertEquals() doesn't check the order of the keys.
		$this->assertTrue( $expected === $actual );
	}

	/**
	 * Test that string replacements recusively happen in array values.
	 */
	function test_option_value_array_recursive() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();

		$value = array(
			array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
		);
		add_option( 'test', $value );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();

		$expected = array(
			array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
		);
		$actual = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements recusively happen in array keys.
	 */
	function test_option_value_array_key_recursive() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();

		$value = array(
			$wpdb->prefix => array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
			"{$wpdb->prefix}2" => array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
		);
		add_option( 'test', $value );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();

		$expected = array(
			$wpdb->prefix => array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
			"{$wpdb->prefix}2" => array(
				$wpdb->prefix,
				get_option( 'siteurl' ),
				$upload_dir['url'],
			),
		);
		$actual = get_option( 'test' );

		// note: we use === comparison because the order the keys is significant
		//       and assertEquals() doesn't check the order of the keys.
		$this->assertTrue( $expected === $actual );
	}

	/**
	 * Test that string replacements happen in object property values.
	 */
	function test_option_value_object() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		$option = (object) array(
			'prefix'         => $wpdb->prefix,
			'siteurl'        => get_option( 'siteurl' ),
			'upload_dir_url' => $upload_dir['url']
		);
		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$expected = (object) array(
			'prefix'         => $wpdb->prefix,
			'siteurl'        => get_option( 'siteurl' ),
			'upload_dir_url' => $upload_dir['url']
		);
		$actual = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that objects with property names that match replacement strings are not replaced.
	 */
	function test_option_value_object_property_name_not_replaced() {
		global $wpdb;

		$option = (object) array(
			$wpdb->prefix => 'test',
		);
		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$expected = $option;
		$actual   = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements happen correctly in objects with protected and private properties.
	 */
	function test_option_value_object_with_protected_private_props() {
		$option = new Protected_Private_Properties();

		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$expected = new Protected_Private_Properties();
		$actual   = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test that string replacements recusively happen in object properties.
	 */
	function test_option_value_object_with_recusive_props() {
		global $wpdb;

		$upload_dir = $this->wp_get_upload_dir();
		$option = (object) array(
			'array' => array(
				'prefix'         => $wpdb->prefix,
				'siteurl'        => get_option( 'siteurl' ),
				'upload_dir_url' => $upload_dir['url']
			),
			'object' => (object) array(
				'prefix'         => $wpdb->prefix,
				'siteurl'        => get_option( 'siteurl' ),
				'upload_dir_url' => $upload_dir['url']
			),
		);
		add_option( 'test', $option );

		$to_site_id = $this->subdirectory_clone_site();

		switch_to_blog( $to_site_id );

		$upload_dir = $this->wp_get_upload_dir();
		$expected = (object) array(
			'array' => array(
				'prefix'         => $wpdb->prefix,
				'siteurl'        => get_option( 'siteurl' ),
				'upload_dir_url' => $upload_dir['url']
			),
			'object' => (object) array(
				'prefix'         => $wpdb->prefix,
				'siteurl'        => get_option( 'siteurl' ),
				'upload_dir_url' => $upload_dir['url']
			),
		);
		$actual   = get_option( 'test' );

		$this->assertEquals( $expected, $actual );
	}
}

/**
 * Class that has protected and private properties.
 *
 * When an instance of this class is stored as an option value (serialized), v0.1.0 will have a fatal
 * error when trying to perform string replacements on the protected and private properties.
 *
 * @since 0.2.0
 */
class Protected_Private_Properties {
	public    $prefix;
	protected $siteurl;
	private   $upload_dir_url;

	/**
	 * Constructor.
	 *
	 * Will set the properties to the values of replacement strings on the site we're
	 * instantiated in.
	 *
	 * @since 0.2.0
	 *
	 */
	function __construct() {
		global $wpdb;

		$upload_dir = WP_Site_Cloner_UnitTestCase::wp_get_upload_dir();

		$this->prefix         = $wpdb->prefix;
		$this->siteurl        = get_option( 'siteurl' );
		$this->upload_dir_url = $upload_dir['url'];
	}
}
