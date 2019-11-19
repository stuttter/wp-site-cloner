The short version:

1. Create a clean MySQL database to use for the tests.  DO NOT USE AN EXISTING DATABASE or you will lose data, guaranteed.

2. Copy wp-tests-config-sample.php to wp-tests-config.php in this plugin's directory, edit it and
include your database name/user/password.

3. install a local copy of phpunit.   From this plugin directory run:
   $ composer install

4. Run the tests from the directory of the plugin being tested:
   To execute all tests:
      $ vendor/bin/phpunit
   To execute all tests in a group:
      $ vendor/bin/phpunit --group {group name}
   To see a list of all groups:
      $ vendor/bin/phpunit --list-groups
   To execute a particular test:
      $ vendor/bin/phpunit tests/phpunit/tests/test_case.php

Notes:

This test harness (the files in the includes directory) is a copy of Core's harness from 5.3.  The test suite,
of course, is for this plugin.

Test cases live in the 'tests' subdirectory.  All files in that directory will be included by default.
Extend the WP_Site_Cloner_UnitTestCase class to ensure your test is run.

phpunit will initialize and install a (more or less) complete running copy of WordPress each time it is run.
This makes it possible to run functional interface and module tests against a fully working database and
codebase, as opposed to pure unit tests with mock objects and stubs.  Pure unit tests may be used also, of course.

Changes to the test database will be rolled back as tests are finished, to ensure a clean start next time the
tests are run.

phpunit is intended to run at the command line, not via a web server.
