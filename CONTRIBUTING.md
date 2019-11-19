# Contributing

Contributions to WP Site Cloner are much appreciated. You can help out in several ways:

* [File an issue.](https://github.com/stuttter/wp-site-cloner/issues/new)
* [Open a pull-request.](https://github.com/stuttter/wp-site-cloner/compare)

## Requirements & Recommendations

When contributing code to WP Site Cloner, please keep the folowing in mind:

* Write code that is backward-compatible to PHP 5.2 and WordPress 4.6.
* Follow the [WordPress coding and documentation standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).
* If possible, provide unit tests for your changes.

WP Site Cloner provides easy-to-use workflows for running unit tests (using PHPUnit).

### PHPUnit Workflows

It is recommended to run unit tests locally before committing, to check in advance that your changes do not cause unexpected issues. Here is how you can do that:

1. create an "empty" WordPress site
2. clone the plugin and install it in that site's `wp-content/plugins` directory (it does not need to be activated...it will be activated while the tests are running).
3. set up its dependencies by running `composer install`. You only need to do this once.
4. create an empty database for the tests to use
5. copy `tests/phpunit/wp-tests-config-sample.php` to `./wp-tests-config.php` and edit the `DB_NAME`, `DB_USER`, `DB_PASSWORD` and `DB_HOST` information. 
6. From the plugin directory, run `vendor/bin/phpunit`.

For more information about the test harness used, see the [README.txt](https://github.com/stuttter/wp-site-cloner/tests/phpunit/README.txt) in the `tests/phpunit` directory.

### Writing Unit Tests

Unit tests should go into the `tests/phpunit/tests` directory. Each test class should extend the `WP_Site_Cloner_UnitTestCase` class, and file names should be prefixed with `test-`.

Convenience methods `WP_Site_Cloner_UnitTestCase::subdirectory_clone_site()` and `WP_Site_Cloner_UnitTestCase::subdomain_clone_site()` are provided to help make writing tests easier.  Their use is highly encouraged since they will make it easier for other contributors to see what it being tested (as the deetails of cloning a site are abstracted away).  
