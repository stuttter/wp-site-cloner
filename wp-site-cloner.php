<?php

/**
 * Plugin Name: WP Site Cloner
 * Plugin URI:  https://flox.io
 * Description: Clone a site
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby
 * Version:     0.1.0
 * License:     GPLv2 or later (license.txt)
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
			'user_id'       => get_current_user_id()
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

		// Copy Site - Data
		$this->db_copy_tables();
		$this->db_set_options();
		$this->db_update_data();

		// Return the new site ID
		return (int) $this->to_site_id;
	}

	/**
	 * Attempt to create a new site
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	private function create_site() {
		global $wpdb;

		// Try to create
		$wpdb->hide_errors();
		$site_id = wpmu_create_blog(
			$this->arguments['domain'],
			$this->arguments['path'],
			$this->arguments['title'],
			$this->arguments['user_id'],
			$this->arguments['meta'],
			$this->arguments['to_network_id']
		);
		$wpdb->show_errors();

		// Return site ID or false
		return ! is_wp_error( $site_id )
			? (int) $site_id
			: false;
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
        return array (
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

		restore_current_blog();

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

		// Setup tables & fields to loop through
		foreach ( $this->get_default_tables() as $table => $fields ) {
			$tables[ $table ] = $fields;
		}

		// Setup array of old & new strings to replace
		$string_to_replace = array(
			$from_upload_url        => $to_upload_url,
			$from_blog_url          => $to_blog_url,
			$this->from_site_prefix => $this->to_site_prefix
		);

		// Try to update data in fields
		foreach ( $tables as $table => $fields ) {
			foreach ( $string_to_replace as $from_string => $to_string ) {
				$this->update( $this->to_site_prefix . $table, $fields, $from_string, $to_string );
			}
		}

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
			$results          = $this->do_sql_query( $sql_query, 'results', false );

			// Skip if no results
			if ( empty( $results ) ) {
				continue;
			}

			// Build the update query
			$update = 'UPDATE `' . $table . '` SET `' . $field . '` = "%s" WHERE `' . $field . '` = "%s"';

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
			$pos = strpos( $val, $to_string );
			if ( $pos === false ) {
				$new = str_replace( $from_string, $to_string, $val );
			}
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

			if ( is_array( $row[ $field ] ) ) {
				$row[ $field ] = $this->replace_recursive( $row[ $field ], $from_string, $to_string );

			} else if ( is_object( $row[ $field ] ) || $row[ $field ] instanceof __PHP_Incomplete_Class ) {
				$array_object = ( array ) $row[ $field ];
				$array_object = $this->replace_recursive( $array_object, $from_string, $to_string );

				foreach ( $array_object as $key => $field ) {
					$row[ $field ]->$key = $field;
				}
			} else {
				$row[ $field ] = $this->replace( $row[ $field ], $from_string, $to_string );
			}

			$row[ $field ] = serialize( $row[ $field ] );

			// Pour des options comme wp_carousel...
			if ( $double_serialize ) {
				$row[ $field ] = serialize( $row[ $field ] );
			}
		} else {
			$row[ $field ] = $this->replace( $row[ $field ], $from_string, $to_string );
		}

		return $row[ $field ];
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
