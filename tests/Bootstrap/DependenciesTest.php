<?php

namespace ImportWPAddon\BLMReader\Tests\Bootstrap;

/**
 * Smoke tests that the shared iwp-dev stack loaded correctly.
 *
 * @group Bootstrap
 */
class DependenciesTest extends \WP_UnitTestCase {

	public function test_importwp_core_is_available() {
		$this->assertTrue( defined( 'IWP_VERSION' ) );
		$this->assertTrue( function_exists( 'import_wp' ) || function_exists( 'import_wp_pro' ) );
		$this->assertTrue( class_exists( '\ImportWP\Container' ) );
	}

	public function test_blm_reader_addon_is_loaded() {
		$this->assertTrue( defined( 'IWP_BLM_READER_VERSION' ) );
		$this->assertTrue( function_exists( 'iwp_blm_reader_setup' ) );
		$this->assertTrue( function_exists( 'iwp_blmr_preview_file' ) );
		$this->assertTrue( function_exists( 'iwp_blm_get_file' ) );
		$this->assertTrue( function_exists( 'iwp_blmr_attachment' ) );
		$this->assertTrue( function_exists( 'iwp_blmr_price_qualifier' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\BLMReader\Importer\File\BLMFile' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\BLMReader\Importer\Parser\BLMParser' ) );
	}
}
