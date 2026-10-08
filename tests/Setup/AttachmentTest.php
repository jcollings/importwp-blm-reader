<?php

namespace ImportWPAddon\BLMReader\Tests\Setup;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;
use ImportWPAddon\BLMReader\Importer\Parser\BLMParser;
use ImportWPAddon\BLMReader\Tests\Utils\BLMFileTestTrait;

/**
 * Docs: https://www.importwp.com/docs/import-blm-files-to-wordpress/
 *
 * Attachments are extracted with [iwp:iwp_blmr_attachment(...)] from a companion zip.
 *
 * @group Setup
 * @group Attachment
 */
class AttachmentTest extends \WP_UnitTestCase {

	use BLMFileTestTrait;

	/** @var int */
	private $importer_id;

	/** @var string */
	private $work_dir;

	public function setUp(): void {
		parent::setUp();

		$this->work_dir = sys_get_temp_dir() . '/iwp-blm-attach-' . uniqid( '', true );
		mkdir( $this->work_dir );

		copy( IWP_BLM_TEST_ROOT . '/samples/with-media.blm', $this->work_dir . '/with-media.blm' );

		$zip = new \ZipArchive();
		$this->assertTrue( true === $zip->open( $this->work_dir . '/with-media.zip', \ZipArchive::CREATE ) );
		$zip->addFromString( 'image-a.jpg', 'fake-jpeg-a' );
		$zip->addFromString( 'image-b.jpg', 'fake-jpeg-b' );
		$zip->close();
	}

	public function tearDown(): void {
		global $iwp_blmr_importer_model;
		$iwp_blmr_importer_model = null;

		if ( $this->importer_id ) {
			/**
			 * @var ImporterManager $manager
			 */
			$manager = Container::getInstance()->get( 'importer_manager' );
			$manager->clear_config_files( $this->importer_id, false, true );
			$manager->clear_config_files( $this->importer_id, true, true );

			$upload_path = iwp_blmr_get_tmp_upload_dir( $this->importer_id );
			if ( is_dir( $upload_path ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
				( new \WP_Filesystem_Direct( false ) )->delete( $upload_path, true );
			}

			wp_delete_post( $this->importer_id, true );
			$this->importer_id = 0;
		}

		if ( $this->work_dir && is_dir( $this->work_dir ) ) {
			foreach ( glob( $this->work_dir . '/*' ) as $file ) {
				@unlink( $file );
			}
			@rmdir( $this->work_dir );
		}

		parent::tearDown();
	}

	/**
	 * @return ImporterModel
	 */
	private function create_local_blm_importer() {
		$importer          = new ImporterModel(
			array(
				'name' => 'BLM Attachment Test',
			)
		);
		$this->importer_id = $importer->save();

		$importer->setParser( 'blm' );
		$importer->setDatasource( 'local' );
		$importer->setDatasourceSetting( 'local_url', $this->work_dir . '/with-media.blm' );
		$importer->save();

		return $importer;
	}

	public function test_attachment_helper_is_callable() {
		$this->assertTrue( function_exists( 'iwp_blmr_attachment' ) );
		$this->assertTrue( is_callable( 'iwp_blmr_attachment' ) );
	}

	public function test_attachment_without_import_context_returns_input() {
		$this->assertEquals( 'image-a.jpg', iwp_blmr_attachment( 'image-a.jpg' ) );
		$this->assertEquals( '', iwp_blmr_attachment( '' ) );
	}

	public function test_extracts_attachments_from_local_companion_zip() {
		$importer = $this->create_local_blm_importer();

		global $iwp_blmr_importer_model;
		$iwp_blmr_importer_model = $importer;

		$result = iwp_blmr_attachment( 'image-a.jpg,image-b.jpg' );
		$paths  = explode( ',', $result );

		$this->assertCount( 2, $paths );
		$this->assertFileExists( $paths[0] );
		$this->assertFileExists( $paths[1] );
		$this->assertStringEndsWith( 'image-a.jpg', $paths[0] );
		$this->assertStringEndsWith( 'image-b.jpg', $paths[1] );
		$this->assertEquals( 'fake-jpeg-a', file_get_contents( $paths[0] ) );
		$this->assertEquals( 'fake-jpeg-b', file_get_contents( $paths[1] ) );
	}

	public function test_missing_zip_entry_returns_empty_slot() {
		$importer = $this->create_local_blm_importer();

		global $iwp_blmr_importer_model;
		$iwp_blmr_importer_model = $importer;

		$result = iwp_blmr_attachment( 'image-a.jpg,missing.jpg' );
		$paths  = explode( ',', $result );

		$this->assertCount( 2, $paths );
		$this->assertFileExists( $paths[0] );
		$this->assertSame( '', $paths[1] );
	}

	public function test_iwp_prefixed_custom_method_extracts_via_parser() {
		$importer = $this->create_local_blm_importer();

		global $iwp_blmr_importer_model;
		$iwp_blmr_importer_model = $importer;

		list( $file ) = $this->create_blm_file( 'samples/with-media.blm' );
		$parser       = new BLMParser( $file );

		$result = $parser->getRecord( 0 )->queryGroup(
			array(
				'fields' => array(
					'ref'   => '{AGENT_REF}',
					'media' => '[iwp:iwp_blmr_attachment("{MEDIA_IMAGE_00},{MEDIA_IMAGE_01}")]',
				),
			)
		);

		$this->assertEquals( 'REF100', $result['ref'] );

		$paths = explode( ',', $result['media'] );
		$this->assertCount( 2, $paths );
		$this->assertFileExists( $paths[0] );
		$this->assertFileExists( $paths[1] );
		$this->assertStringEndsWith( 'image-a.jpg', $paths[0] );
		$this->assertStringEndsWith( 'image-b.jpg', $paths[1] );
	}

	public function test_unprefixed_custom_method_is_not_executed() {
		$importer = $this->create_local_blm_importer();

		global $iwp_blmr_importer_model;
		$iwp_blmr_importer_model = $importer;

		list( $file ) = $this->create_blm_file( 'samples/with-media.blm' );
		$parser       = new BLMParser( $file );

		$result = $parser->getRecord( 0 )->query_string( '[iwp_blmr_attachment("{MEDIA_IMAGE_00}")]' );

		// Without the iwp: prefix the helper is not invoked; braces still interpolate.
		$this->assertEquals( '[iwp_blmr_attachment("image-a.jpg")]', $result );
	}

	public function test_price_qualifier_via_iwp_prefix() {
		list( $file ) = $this->create_blm_file( 'samples/with-media.blm' );
		$parser       = new BLMParser( $file );

		$result = $parser->getRecord( 0 )->queryGroup(
			array(
				'fields' => array(
					'qualifier' => '[iwp:iwp_blmr_price_qualifier("{PRICE_QUALIFIER}")]',
				),
			)
		);

		$this->assertEquals( 'Guide Price', $result['qualifier'] );

		$result = $parser->getRecord( 1 )->queryGroup(
			array(
				'fields' => array(
					'qualifier' => '[iwp:iwp_blmr_price_qualifier("{PRICE_QUALIFIER}")]',
				),
			)
		);

		$this->assertEquals( 'Offers in Excess of', $result['qualifier'] );
	}

	public function test_import_event_sets_attachment_context() {
		$importer = $this->create_local_blm_importer();

		/**
		 * @var ImporterManager $manager
		 */
		$manager       = Container::getInstance()->get( 'importer_manager' );
		$reflection    = new \ReflectionClass( $manager );
		$property      = $reflection->getProperty( 'event_handler' );
		$property->setAccessible( true );
		$event_handler = $property->getValue( $manager );

		$event_handler->run( 'importer_manager.import', array( $importer ) );

		global $iwp_blmr_importer_model;
		$this->assertSame( $importer, $iwp_blmr_importer_model );

		$event_handler->run( 'importer_manager.import_shutdown', array( $importer ) );
		$this->assertNull( $iwp_blmr_importer_model );
	}
}
