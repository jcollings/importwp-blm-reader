<?php

namespace ImportWPAddon\BLMReader\Tests\Setup;

/**
 * @group Setup
 */
class FileTypesTest extends \WP_UnitTestCase {

	public function test_blm_is_added_to_allowed_file_types() {
		$types = apply_filters( 'iwp/importer/datasource/allowed_file_types', array( 'csv', 'xml' ) );
		$this->assertContains( 'blm', $types );
	}

	public function test_filetype_detected_from_extension() {
		$this->assertEquals( 'blm', apply_filters( 'iwp/get_filetype_from_ext', 'csv', 'properties.blm' ) );
		$this->assertEquals( 'blm', apply_filters( 'iwp/get_filetype_from_ext', '', '/tmp/feed.BLM' ) );
		$this->assertEquals( 'csv', apply_filters( 'iwp/get_filetype_from_ext', 'csv', 'products.csv' ) );
	}
}
