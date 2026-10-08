<?php

/**
 * PHPUnit bootstrap for Import WP BLM Reader addon.
 *
 * Expects sibling plugins mounted by iwp-dev wp-env:
 * - importwp
 * - importwp-pro (optional)
 *
 * @package ImportWPAddon\BLMReader
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you started iwp-dev wp-env?" . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

define( 'IWP_BLM_TEST_ROOT', __DIR__ );
define( 'IWP_BLM_PLUGIN_ROOT', dirname( __DIR__ ) );
define( 'IWP_BLM_PLUGINS_DIR', dirname( IWP_BLM_PLUGIN_ROOT ) );

$autoload = IWP_BLM_PLUGIN_ROOT . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	echo "Composer dependencies missing. From iwp-dev run: npm run test:blm:install" . PHP_EOL;
	exit( 1 );
}
require_once $autoload;

if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . IWP_BLM_PLUGIN_ROOT . '/vendor/yoast/phpunit-polyfills' );
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', IWP_BLM_PLUGIN_ROOT . '/vendor/yoast/phpunit-polyfills' );
}

require_once __DIR__ . '/autoload.php';

/**
 * Resolve a sibling plugin main file under wp-content/plugins.
 *
 * @param string      $main_file Main PHP file inside the plugin directory.
 * @param string|null $preferred_directory Preferred plugin directory name.
 * @return string
 */
function iwp_blm_tests_plugin_file( $main_file, $preferred_directory = null ) {
	if ( $preferred_directory ) {
		$path = IWP_BLM_PLUGINS_DIR . '/' . $preferred_directory . '/' . $main_file;
		if ( file_exists( $path ) ) {
			return $path;
		}
	}

	$matches = glob( IWP_BLM_PLUGINS_DIR . '/*/' . $main_file );
	if ( empty( $matches ) ) {
		$hint = $preferred_directory ? IWP_BLM_PLUGINS_DIR . '/' . $preferred_directory . '/' . $main_file : $main_file;
		echo "Required plugin not found: {$hint}" . PHP_EOL;
		echo 'Start iwp-dev with: npm run test:start' . PHP_EOL;
		exit( 1 );
	}

	return $matches[0];
}

/**
 * Manually load ImportWP, optional Pro, then this addon.
 */
function _manually_load_iwp_blm_reader_plugins() {
	require iwp_blm_tests_plugin_file( 'jc-importer.php', 'importwp' );

	$pro = IWP_BLM_PLUGINS_DIR . '/importwp-pro/importwp-pro.php';
	if ( file_exists( $pro ) ) {
		require $pro;
	}

	require IWP_BLM_PLUGIN_ROOT . '/blm-reader.php';

	if ( class_exists( '\ImportWP\Common\Migration\Migrations' ) ) {
		$migration = new \ImportWP\Common\Migration\Migrations();
		$migration->migrate();
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_iwp_blm_reader_plugins' );

require "{$_tests_dir}/includes/bootstrap.php";
