<?php

/**
 * Plugin Name: WP Site Cloner
 * Plugin URI:  https://wordpress.org/plugins/wp-site-cloner/
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Create sites with content from other sites
 * Version:     0.1.0
 * Text Domain: wp-site-cloner
 * Domain Path: /assets/lang/
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Clone a site
 *
 * @since 0.1.0
 *
 * @param  array $args
 *
 * @return \WP_Site_Cloner
 */
function wp_clone_site( $args = array() ) {
	$cloner = new WP_Site_Cloner( $args );
	return $cloner->clone_site();
}

/**
 * The main site cloner class
 *
 * @since 0.1.0
 */
final class WP_Site_Cloner {

	/**
	 * @var int Source site ID
	 */
	private $from_site_id = 0;

	/**
	 * @var int Destination site ID
	 */
	private $to_site_id = 0;

	/**
	 * @var int Source site ID
	 */
	private $from_site_prefix = '';

	/**
	 * @var int Destination site ID
	 */
	private $to_site_prefix = '';

	/**
	 * @var array Arguments for the new site
	 */
	private $arguments = array();

	/**
	 * @var string URL of the new site, based on arguments
	 */
	private $home_url = '';

	/**
	 * Start to clone the site
	 *
	 * @since 0.1.0
	 *
	 * @param  array $args
	 * @return int
	 */
	public function __construct( $args = array() ) {
		$this->arguments = wp_parse_args( $args, array(
			'domain'        => '',
			'path'          => '/',
			'title'         => '',
			'meta'          => array( 'public' => 1 ),
			'from_site_id'  => 0,
			'to_network_id' => get_current_site()->id,
			'user_id'       => get_current_user_id(),
			'callback'      => 'wpmu_create_blog',
			'cleanup'       => ''
		) );
	}

	/**
	 * Main function of the plugin : duplicates a site
	 *
	 * @since 0.1.0
	 *
	 * @param  array $args parameters from form
	 */
	public function clone_site() {

		// Bail if no source siteID
		if ( empty( $this->arguments['from_site_id'] ) ) {
			return;
		}

		// Setup sites
		$this->from_site_id = (int) $this->arguments[ 'from_site_id' ];

		// Bail if from site does not exist
		if ( ! get_blog_details( $this->from_site_id ) ) {
			return;
		}

		// Attempt to create a site
		$this->to_site_id = (int) $this->create_site();

		// Bail if no site was created
		if ( empty( $this->to_site_id ) ) {
			return false;
		}

		// Setup the new URL
		$this->home_url = esc_url_raw( implode( '/', array(
			rtrim( $this->arguments['domain'], '/' ),
			ltrim( rtrim( $this->arguments['path'], '/' ), '/' )
		) ) );

		// Primary blog
		$this->maybe_set_primary_blog();

		// Temporarily bump known limitations
		@ini_set( 'memory_limit',       '1024M' );
		@ini_set( 'max_execution_time', '0'     );

		// Copy site and all of it's data
		$this->copy_uploads();
		$this->db_copy_tables();
		$this->db_set_options();
		$this->db_copy_users();
		$this->db_update_data();

		// Restore site options...because they will have been mucked with in db_update_data().
		$this->db_set_options();

		// Maybe run a clean-up routine
		$this->cleanup_site();

		// Return the new site ID
		return (int) $this->to_site_id;
	}

	/**
	 * Attempt to create a new site
	 *
	 * This method checks if your callback function exists. If it does, it calls
	 * it; if not, it calls do_action() with the name of your callback.
	 *
	 * Your custom callback function should be based on wpmu_create_blog(), and
	 * take care to mimic it's quirky requirements.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	private function create_site() {
		global $wpdb;

		// Default site ID
		$site_id = false;

		// Always hide DB errors
		$wpdb->hide_errors();

		// Try to create
		if ( function_exists( $this->arguments['callback'] ) ) {
			$site_id = call_user_func(
				$this->arguments['callback'],
				$this->arguments['domain'],
				$this->arguments['path'],
				$this->arguments['title'],
				$this->arguments['user_id'],
				$this->arguments['meta'],
				$this->arguments['to_network_id']
			);
		} else {
			$site_id = apply_filters( $this->arguments['callback'], $this->arguments );
		}

		// Restore error visibility
		$wpdb->show_errors();

		// Return site ID or false
		return ! is_wp_error( $site_id )
			? (int) $site_id
			: false;
	}

	/**
	 * Attempt to clean up a new site
	 *
	 * This method checks if your clean-up callback function exists. If it does,
	 * it calls it; if not, it calls do_action() with the name of your callback.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	private function cleanup_site() {

		// Bail if no clean-up function passed
		if ( empty( $this->arguments['cleanup'] ) ) {
			return;
		}

		// Switch to the new site
		switch_to_blog( $this->to_site_id );

		// Try to clean-up
		if ( function_exists( $this->arguments['cleanup'] ) ) {
			call_user_func( $this->arguments['cleanup'] );
		} else {
			do_action( $this->arguments['cleanup'] );
		}

		// Switch back
		restore_current_blog();
	}

	/**
	 * User rights adjustment for maybe setting the primary blog for this user
	 *
	 * @since 0.1.0
	 */
	private function maybe_set_primary_blog() {
		if ( ! is_super_admin( $this->arguments['user_id'] ) && ! get_user_meta( 'primary_blog', $this->arguments['user_id'], true ) ) {
			update_user_meta( $this->arguments['user_id'], 'primary_blog', $this->to_site_id );
		}
	}

	/**
	 * Copy uploads from one site to another.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function copy_uploads() {
		// Switch to Source site and get uploads info
		switch_to_blog( $this->from_site_id );

		$wp_upload_info = wp_upload_dir();
		$from_dir       = str_replace( ' ', "\\ ", trailingslashit( $wp_upload_info['basedir'] ) );

		// Switch to Destination site and get uploads info
		switch_to_blog( $this->to_site_id );

		$wp_upload_info = wp_upload_dir();
		$to_dir         = str_replace(' ', "\\ ", trailingslashit( $wp_upload_info['basedir'] ) );

		// Go back to Source site.
		restore_current_blog();

		// if Source site is main site, don't copy upload/sites directory.
		$exclude  = is_main_site( $this->from_site_id ) ? array( 'sites' ) : array();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		copy_dir( $from_dir, $to_dir, $exclude );

		return;
	}

	/**
	 * Copy tables from a site to another
	 *
	 * @since 0.1.0
	 */
	private function db_copy_tables() {
		global $wpdb;

		// Destination Site information
		$this->to_site_prefix   = $wpdb->get_blog_prefix( $this->to_site_id   );
		$this->from_site_prefix = $wpdb->get_blog_prefix( $this->from_site_id );

		// Setup from site query info
		$from_site_prefix_length = strlen( $this->from_site_prefix );
		$from_site_prefix_like   = $wpdb->esc_like( $this->from_site_prefix );

		// Get sources Tables
		if ( $this->from_site_id === (int) get_current_site()->blog_id ) {
			$from_site_tables = $this->get_primary_tables( $this->from_site_prefix );
		} else {
			$sql_query        = $wpdb->prepare( 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE \'%s\'', $from_site_prefix_like . '%' );
			$from_site_tables = $this->do_sql_query( $sql_query, 'col' );
		}

		// Loop through tables, and cleanup/create/populate
		foreach ( $from_site_tables as $table ) {
			$table_name = $this->to_site_prefix . substr( $table, $from_site_prefix_length );

			// Drop table if exists
			$this->do_sql_query( 'DROP TABLE IF EXISTS `' . $table_name . '`' );

			// Create new table from source table
			$this->do_sql_query( 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` LIKE `' . $table . '`' );

			// Populate database with data from source table
			$this->do_sql_query( 'INSERT `' . $table_name . '` SELECT * FROM `' . $table . '`' );
		}
	}

	/**
	 * Copy users (with their roles and user options) from one site to another.
	 *
	 * This does *not* perform string replacements in meta_values...that will happen in `WP_Site_Cloner::db_update_db()`.
	 *
	 * @since 0.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function db_copy_users() {
		global $wpdb;

		$from_site_prefix = $wpdb->get_blog_prefix( $this->from_site_id );
		$to_site_prefix   = $wpdb->get_blog_prefix( $this->to_site_id );

		// get all users on the from site, except for the user that we made the admin on the new site.
		$args = array(
			'blog_id' => $this->from_site_id,
			'exclude' => $this->arguments['user_id']
		);
		$users = get_users( $args );

		switch_to_blog( $this->to_site_id );

		foreach ( $users as $user ) {
			// get usermeta that is specific to the from site.
			$sql_query = sprintf(
				"SELECT `meta_key`, `meta_value` FROM `{$wpdb->usermeta}` WHERE `user_id` = %1\$d AND `meta_key` REGEXP '^%2\$s' AND `meta_key` NOT REGEXP '^%2\$s[[:digit:]]+_'",
				$user->ID,
				$from_site_prefix
			);
			$results = $this->do_sql_query( $sql_query, 'results' );

			// Skip if no results
			if ( empty( $results ) ) {
				// @todo this should be an error condition, because every user that is a member
				//       of a blog should have at least a `capabilities` usermeta.
				continue;
			}

			// Loop through results and add a meta_key for the to site with the from site meta_value.
			foreach ( $results as $row ) {
				$row['meta_key'] = preg_replace( "/^{$from_site_prefix}/", $to_site_prefix, $row['meta_key'] );

				$this->do_sql_query(
					$wpdb->prepare(
						"INSERT `{$wpdb->usermeta}` ( `user_id`, `meta_key`, `meta_value` ) VALUES( %s, %s, %s )",
						$user->ID,
						$row['meta_key'],
						$row['meta_value']
					)
				);
			}
		}

		restore_current_blog();

		return;
	}

	/**
	 * Options that should be preserved in the new blog.
	 *
	 * @since 0.1.0
	 */
	private function db_set_options() {

		// Update options according to new location
		$new_site_options = array(
			'siteurl'     => $this->home_url,
			'home'        => $this->home_url,
			'blogname'    => $this->arguments['title'],
			'admin_email' => get_userdata( $this->arguments['user_id'] )->user_email,
		);

		// Apply key options from new blog.
		switch_to_blog( $this->to_site_id );
		foreach ( $new_site_options as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}
		restore_current_blog();
	}

	/**
	 * Get tables to copy if duplicated site is primary site
	 *
	 * @since 0.1.0
	 *
	 * @return array of strings : the tables
	 */
	private function get_primary_tables() {

		// Tables to copy
		$default_tables = array_keys( $this->get_default_tables() );

		foreach ( $default_tables as $k => $default_table ) {
			$default_tables[ $k ] = $this->from_site_prefix . $default_table;
		}

		return $default_tables;
	}

	/**
	 * Return array of tables & values to search & replace
	 *
	 * Note that this will need to be updated if tables are added or removed, or
	 * if custom tables are desired.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	private function get_default_tables() {
        return array(
			'terms'              => array(),
			'termmeta'           => array(),
			'term_taxonomy'      => array(),
			'term_relationships' => array(),
			'commentmeta'        => array(),
			'comments'           => array(),
			'postmeta'           => array( 'meta_value'                   ),
			'posts'              => array( 'post_content', 'guid'         ),
			'links'              => array( 'link_url',     'link_image'   ),
			'options'            => array( 'option_name',  'option_value' ),
		);
	}

	/**
	 * Updated tables from a site to another
	 *
	 * @since 0.1.0
	 */
	public function db_update_data() {
		global $wpdb;

		// Looking for uploads dirs
		switch_to_blog( $this->from_site_id );

		$dir             = wp_upload_dir();
		$from_upload_url = str_replace( network_site_url(), get_bloginfo( 'url' ) . '/', $dir[ 'baseurl' ] );
		$from_blog_url   = get_blog_option( $this->from_site_id, 'siteurl' );

		switch_to_blog( $this->to_site_id );

		$dir           = wp_upload_dir();
		$to_upload_url = str_replace( network_site_url(), get_bloginfo( 'url' ) . '/', $dir[ 'baseurl' ] );
		$to_blog_url   = get_blog_option( $this->to_site_id, 'siteurl' );

		// Switch back to "from" site
		restore_current_blog();

		// Setup empty tables array
		$tables = array();

		// Bugfix : escape '_' , '%' and '/' character for mysql 'like' queries
		$to_site_prefix_like = $wpdb->esc_like( $this->to_site_prefix );
		$results             = $this->do_sql_query( 'SHOW TABLES LIKE \'' . $to_site_prefix_like . '%\'', 'col', false );

		foreach ( $results as $k => $v ) {
			$tables[ str_replace( $this->to_site_prefix, '', $v ) ] = array();
		}

		foreach ( array_keys( $tables ) as $table ) {
			$results = $this->do_sql_query( 'SHOW COLUMNS FROM `' . $this->to_site_prefix . $table . '`', 'col', false );
			$columns = array();

			foreach ( $results as $k => $v ) {
				$columns[] = $v;
			}

			$tables[ $table ] = $columns;
		}

		// Maybe don't copy _links
		$default_tables = $this->get_default_tables();
		if ( ! get_blog_option( $this->from_site_id, 'link_manager_enabled', 0 ) ) {
			unset( $default_tables['links'] );
		}

		// Setup tables & fields to loop through
		foreach ( $default_tables as $table => $fields ) {
			$tables[ $table ] = $fields;
		}

		// Setup global tables & fields to loop through.
		$tables['usermeta'] = array( 'meta_value' );

		// Setup array of old & new strings to replace
		if ( 0 === strpos( $to_upload_url, $to_blog_url ) ) {
			// if $to_blog_url is a leading substring of $to_upload_url
			// then we need to create a "temporary" string for the upload_url
			// otherwise the replaced strings won't be correct.
			// this is analogous to swaping the value of 2 variables using
			// a 3rd temporary variable.
			$tmp = str_replace( $to_blog_url, $this->placeholder_escape(), $to_upload_url );

			$string_to_replace = array(
				$from_upload_url        => $tmp,
				$from_blog_url          => $to_blog_url,
				$tmp                    => $to_upload_url,
				$this->from_site_prefix => $this->to_site_prefix
			);
		}
		else {
			$string_to_replace = array(
				$from_upload_url        => $to_upload_url,
				$from_blog_url          => $to_blog_url,
				$this->from_site_prefix => $this->to_site_prefix
			);
		}

		// collect the global tables.
		$global_tables = array_merge( $wpdb->global_tables, $wpdb->ms_global_tables );

		// Try to update data in fields
		foreach ( $tables as $table => $fields ) {
			$table = ( in_array( $table, $global_tables ) ? $wpdb->base_prefix : $this->to_site_prefix ) . $table;

			foreach ( $string_to_replace as $from_string => $to_string ) {
				$this->update( $table, $fields, $from_string, $to_string );
			}
		}

		// Restore back to original source site
		restore_current_blog();

		// Clear cache
		refresh_blog_details( $this->to_site_id );
	}

	/**
	 * Updates a table
	 *
	 * @since 0.1.0
	 *
	 * @param  string  $table        to update
	 * @param  array   $fields       of string $fields to update
	 * @param  string  $from_string  original string to replace
	 * @param  string  $to_string    new string
	 */
	public function update( $table, $fields, $from_string, $to_string ) {
		global $wpdb;

		// Bail if fields isn't an array
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return;
		}

		// Loop through fields
		foreach ( $fields as $field ) {

			// Bugfix : escape '_' , '%' and '/' character for mysql 'like' queries
			$from_string_like = $wpdb->esc_like( $from_string );
			$sql_query        = $wpdb->prepare( 'SELECT `' . $field . '` FROM `' . $table . '` WHERE `' . $field . '` LIKE "%s" ', '%' . $from_string_like . '%' );
			if ( $table === $wpdb->usermeta ) {
				$sql_query .= sprintf( " AND `meta_key` REGEXP '^%s'", $wpdb->get_blog_prefix( $this->to_site_id ) );
			}
			$results = $this->do_sql_query( $sql_query, 'results', false );

			// Skip if no results
			if ( empty( $results ) ) {
				continue;
			}

			// Build the update query
			$update = 'UPDATE `' . $table . '` SET `' . $field . '` = "%s" WHERE `' . $field . '` = "%s"';
			if ( $wpdb->usermeta === $table ) {
				$update .= sprintf( " AND `meta_key` REGEXP '^%s'", $wpdb->get_blog_prefix( $this->to_site_id ) );
			}

			// Loop through results & replace any URL & site ID related values
			foreach ( $results as $row ) {
				$old_value = $row[ $field ];
				$new_value = $this->try_replace( $row, $field, $from_string, $to_string );
				$sql_query = $wpdb->prepare( $update, $new_value, $old_value );
				$results   = $this->do_sql_query( $sql_query );
			}
		}
	}

	/**
	 * Replace $from_string with $to_string in $val. If $to_string already
	 * in $val, no replacement is made
	 *
	 * @since 0.1.0
	 *
	 * @param  string $val
	 * @param  string $from_string
	 * @param  string $to_string
	 * @return string the new string
	 */
	public function replace( $val, $from_string, $to_string ) {
		$new = $val;

		if ( is_string( $val ) ) {
			$new = str_replace( $from_string, $to_string, $val );
		}

		return $new;
	}

	/**
	 * Replace recursively $from_string with $to_string in $val
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed   (string|array) $val
	 * @param  string  $from_string
	 * @param  string  $to_string
	 *
	 * @return string  the new string
	 */
	public function replace_recursive( $val, $from_string, $to_string ) {
		$unset = array();

		if ( is_array( $val ) ) {
			foreach ( array_keys( $val ) as $k ) {
				$val[ $k ] = $this->try_replace( $val, $k, $from_string, $to_string );
			}
		} else {
			$val = $this->replace( $val, $from_string, $to_string );
		}

		foreach ( $unset as $k ) {
			unset( $val[ $k ] );
		}

		return $val;
	}

	/**
	 * Try to replace $from_string with $to_string in a row
	 *
	 * @since 0.1.0
	 *
	 * @param  array   $row the row
	 * @param  array   $field the field
	 * @param  string  $from_string
	 * @param  string  $to_string
	 *
	 * @return the data, maybe replaced
	 */
	public function try_replace( $row, $field, $from_string, $to_string ) {
		if ( is_serialized( $row[ $field ] ) ) {
			$double_serialize = false;
			$row[ $field ]    = @unserialize( $row[ $field ] );

			// FOR SERIALISED OPTIONS, like in wp_carousel plugin
			if ( is_serialized( $row[ $field ] ) ) {
				$row[ $field ]    = @unserialize( $row[ $field ] );
				$double_serialize = true;
			}

			// recurse on the unserialized value.
			$row[ $field ] = $this->try_replace( $row, $field, $from_string, $to_string );

			$row[ $field ] = serialize( $row[ $field ] );

			// Pour des options comme wp_carousel...
			if ( $double_serialize ) {
				$row[ $field ] = serialize( $row[ $field ] );
			}
		} elseif ( is_array( $row[ $field ] ) ) {
			$row[ $field ] = $this->replace_recursive( $row[ $field ], $from_string, $to_string );
		} elseif ( is_object( $row[ $field ] ) ) {
			$array_object = $this->get_object_vars( $row[ $field ] );
			$array_object = $this->replace_recursive( $array_object, $from_string, $to_string );

			$row[ $field ] = $this->set_object_vars( $row[ $field ], $array_object );
		} elseif ( is_string( $row[ $field ] ) ) {
			$row[ $field ] = $this->replace( $row[ $field ], $from_string, $to_string );
		}

		return $row[ $field ];
	}

	/**
	 * Get the non-static properties of an object as an associative array.
	 *
	 * Like PHP's builtin {@link https://www.php.net/manual/en/function.get-object-vars.php get_object_vars()}
	 * except it also gets protected and private properties, even when they are not in scope.
	 *
	 * Public properties will have keys such as `PropName` where `PropName` is the property name.
	 *
	 * Protected properties will have keys such as `'\0' . '*' . '\0' . PropName`,
	 * where `PropName` is the property name.
	 *
	 * Private properties will have keys such as `'\0' . ClassName . '\0' . PropName`,
	 * where `ClassName` is the class that declared the private property (which may be
	 * an inherited base class) and `PropName` is the property name.
	 *
	 * Works with instances of {@link https://www.php.net/manual/en/reserved.classes.php __PHP_Incomplete_Class}
	 * and {@link https://www.php.net/manual/en/reserved.classes.php stdClass}.
	 *
	 * @see WP_Site_Cloner::set_object_vars()
	 *
	 * @since 0.1.0
	 *
	 * @param object $object The object whose properties are to be gotten.
	 * @return array         Keys are property names, values are property values.
	 */
	protected function get_object_vars( $object ) {
		if ( ! is_object( $object ) ) {
			return array();
		}

		return (array) $object;
	}

	/**
	 * Set the non-static property values of an object to those in an associative array.
	 *
	 * Functionally equivalent to the following:
	 *
	 * class Foo {
	 *     public $bar = 'baz';
	 * }
	 * $foo = new Foo();
	 *
	 * $array = array( 'bar' => 'biff' );
	 * foreach ( $array as $key => $value ) {
	 *     // this will cause a PHP fatal error for protected and private properties.
	 *     $foo->{$key} = $value;
	 * }
	 *
	 * except that it allows setting values for protected and private properties.
	 *
	 * If `$array` contains keys that are not delcared properties of `$object`, they will
	 * become "dynamic" properties of the instance.
	 *
	 * Works with instances of {@link https://www.php.net/manual/en/reserved.classes.php __PHP_Incomplete_Class}
	 * and {@link https://www.php.net/manual/en/reserved.classes.php stdClass}.
	 *
	 * @see WP_Site_Cloner::get_object_vars()
	 *
	 * @since 0.2.0
	 *
	 * @param object $object The object whose properties are to be set.
	 * @param array $array   Keys are property names, values are property values.
	 * @return object        `$object` with new property values set.
	 *
	 * @todo see how the phpdoc-parser deals with the above inline code.
	 * @todo while it might be "more correct" to use reflection to set protected and
	 *       private properties, reflection doesn't work with stdClass and __PHP_Incomplete_Class,
	 *       so we'd still need something like this for those cases.  Also, reflection
	 *       doesn't completely work when an object has "dynamic" properties, and such
	 *       properties get serialized...
	 */
	protected function set_object_vars( $object, $array ) {
		if ( ! is_object( $object ) ) {
			return $object;
		}

		// convert the object into an array.
		$object_vars = $this->get_object_vars( $object );

		// merge that object array with $array.
		// if $array contains keys that are not properties of $object,
		// those keys will become "dynamic" properties of $object.
		$object_vars = array_merge( $object_vars, $array );

		// serialize the object array.
		$object_array_serialized = serialize( $object_vars );

		// replace the array serialization syntax with object serialization syntax.
		$className = get_class( $object );
		$object_array_serialized = preg_replace(
			'/^a:/',
			sprintf( 'O:%d:"%s":', strlen( $className ), $className ),
			$object_array_serialized
		);

		// reserailize the new object.
		$new_object = unserialize( $object_array_serialized );

		return $new_object;
	}

	/**
	 * Generates and returns a placeholder escape string.
	 *
	 * Uses the same algorithm as used by {@link https://developer.wordpress.org/reference/classes/wpdb/placeholder_escape/ wpdb::placeholder_escape()},
	 * except that "punctuation" surrounding the value is '[' and ']' instead of '{' and '}'.
	 * The change in punctuation is so that the value can be used in query string passed to `$wpdb->query()`
	 * without causing `$wpdb` to invoke it's processing of it's placeholder escapes.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	protected function placeholder_escape() {
		global $wpdb;

		static $placeholder;

		if ( ! $placeholder ) {
			$placeholder = $wpdb->placeholder_escape();
			$placeholder = str_replace( array( '{', '}' ), array( '[', ']' ), $placeholder );
		}

		return $placeholder;
	}

	/**
	 * Runs a WPDB query
	 *
	 * @since 0.1.0
	 *
	 * @param  string  $sql_query the query
	 * @param  string  $type type of result
	 *
	 * @return $results of the query
	 */
	public function do_sql_query( $sql_query, $type = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		switch ( $type ) {
			case 'col':
				$results = $wpdb->get_col( $sql_query );
				break;
			case 'row':
				$results = $wpdb->get_row( $sql_query );
				break;
			case 'var':
				$results = $wpdb->get_var( $sql_query );
				break;
			case 'results':
				$results = $wpdb->get_results( $sql_query, ARRAY_A );
				break;
			default:
				$results = $wpdb->query( $sql_query );
				break;
		}

		$wpdb->show_errors();

		return $results;
	}
}
