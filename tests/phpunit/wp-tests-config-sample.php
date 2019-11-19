<?php

/* Path to the plugin codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** MySQL settings ** //

// This configuration file will be used by the copy of WordPress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME', 'youremptytestdbnamehere' );
define( 'DB_USER', 'yourusernamehere' );
define( 'DB_PASSWORD', 'yourpasswordhere' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define('AUTH_KEY',         'T+huc:qMQ+m#^=1VA* _}oQ$gJ.[_*<%NEYmg?[wJ~3xewCEX&{>> <c>gt+2I47');
define('SECURE_AUTH_KEY',  'vQFDSB4fW;n4yL&w{UpcxKK@w+`o$)jtmlNCQ24ewV4M7[Q(LbhC9,#FYq[j|Cm ');
define('LOGGED_IN_KEY',    'cw0:b`_I;H.0CwfG^Y`xK@q=>6fv||~_J5c)1/+pJF4VHR,tl%;H!$45j*-!zV8D');
define('NONCE_KEY',        '-&|#^,[my<^RmpVo*t5vko{a &NTP-81O*Dhxj~:g|JaQWV+4g:zVK#A_1^-&]Yj');
define('AUTH_SALT',        'o3gO~8yK+fvQKd*Vm1c[cilpeB}U5Bmf<nmRG,uUyk2s3}p+<sgH,!RuaKuXY[V|');
define('SECURE_AUTH_SALT', 'Svm4iI/l-/zgQ42D^H`=!1F>qUd$?#[D 1pM2A#zHDIkfO|]}AH>Z2j -(~W#[[+');
define('LOGGED_IN_SALT',   'eq--|#{|Uh5@z#2utryydF+#t^&/7FCcWX`gJ^~[gCDH_uBj}tGQJ.-ItB|<t-,;');
define('NONCE_SALT',       '0Zt%cAZq*-/p}81@u^IIT2>?jq<l#hC-OLRMm)F$*5Xpwe|sXN=PaW^-08WISik,');

$table_prefix = 'wptests_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

// activate the following plugins during tests.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'wp-site-cloner/wp-site-cloner.php' ),
);
