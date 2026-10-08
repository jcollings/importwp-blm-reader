<?php

namespace ImportWPAddon\BLMReader\Tests\Setup;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;
use ImportWPAddon\BLMReader\Tests\Utils\BLMFileTestTrait;

/**
 * @group Setup
 * @group Preview
 */
class PreviewTest extends \WP_UnitTestCase {

	use BLMFileTestTrait;

	/** @var int */
	private $importer_id;

	public function tearDown(): void {
		if ( $this->importer_id ) {
			/**
			 * @var ImporterManager $manager
			 */
			$manager = Container::getInstance()->get( 'importer_manager' );
			$manager->clear_config_files( $this->importer_id, false, true );
			$manager->clear_config_files( $this->importer_id, true, true );
			wp_delete_post( $this->importer_id, true );
			$this->importer_id = 0;
		}
		parent::tearDown();
	}

	/**
	 * @return ImporterModel
	 */
	private function create_blm_importer() {
		/**
		 * @var ImporterManager $manager
		 */
		$manager = Container::getInstance()->get( 'importer_manager' );

		$importer          = new ImporterModel(
			array(
				'name' => 'BLM Preview Test',
			)
		);
		$this->importer_id = $importer->save();

		add_filter( 'iwp/importer/local_file/allowed_directories', array( $this, 'whitelist_blm_test_dir' ) );
		$attachment_id = $manager->local_file( $importer, IWP_BLM_TEST_ROOT . '/samples/properties.blm' );
		remove_filter( 'iwp/importer/local_file/allowed_directories', array( $this, 'whitelist_blm_test_dir' ) );

		$this->assertGreaterThan( 0, $attachment_id );
		$importer = $manager->get_importer( $this->importer_id );
		$this->assertEquals( 'blm', $importer->getParser() );

		return $importer;
	}

	public function test_file_preview_returns_record_and_total() {
		$importer = $this->create_blm_importer();

		$result = apply_filters( 'iwp/file-preview/blm', null, $importer, 0 );

		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['record'] );
		$this->assertEquals( 3, $result['total'] );
		$this->assertEquals(
			array( 'AGENT_REF', 'ADDRESS_1', 'TOWN', 'POSTCODE1', 'DESCRIPTION', 'PRICE' ),
			$result['headings']
		);
		$this->assertEquals( 'REF001', $result['row'][0] );
		$this->assertEquals( '250000', $result['row'][5] );
	}

	public function test_file_preview_pages_to_requested_record() {
		$importer = $this->create_blm_importer();

		$result = apply_filters( 'iwp/file-preview/blm', null, $importer, 1 );

		$this->assertEquals( 1, $result['record'] );
		$this->assertEquals( 3, $result['total'] );
		$this->assertEquals( 'REF002', $result['row'][0] );
		$this->assertEquals( "Line one\nLine two with a newline.", $result['row'][4] );
	}

	public function test_file_process_stores_sample_count() {
		$importer = $this->create_blm_importer();

		/**
		 * @var ImporterManager $manager
		 */
		$manager = Container::getInstance()->get( 'importer_manager' );
		$manager->clear_config_files( $this->importer_id, false, true );
		$manager->clear_config_files( $this->importer_id, true, true );

		do_action( 'iwp/file-process/blm', $importer );
		$importer->save();

		$this->assertEquals( 2, intval( $importer->getFileSetting( 'count' ) ) );
	}

	public function test_file_preview_rebuilds_sample_index() {
		$importer = $this->create_blm_importer();

		/**
		 * @var ImporterManager $manager
		 */
		$manager = Container::getInstance()->get( 'importer_manager' );
		$config  = $manager->get_config( $importer, true );
		$file    = iwp_blm_get_file( $importer, $config );
		$file->processing( true );
		$this->assertEquals( 2, $file->getRecordCount() );

		$result = apply_filters( 'iwp/file-preview/blm', null, $importer, 2 );

		$this->assertEquals( 2, $result['record'] );
		$this->assertEquals( 3, $result['total'] );
		$this->assertEquals( 'REF003', $result['row'][0] );
	}

	public function test_record_preview_maps_fields() {
		$importer = $this->create_blm_importer();

		$result = apply_filters(
			'iwp/record-preview/blm',
			array(),
			$importer,
			array(
				'title' => '{AGENT_REF}',
				'price' => '{PRICE}',
			)
		);

		$this->assertEquals( 'REF001', $result['title'] );
		$this->assertEquals( '250000', $result['price'] );
	}
}
