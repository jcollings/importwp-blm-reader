<?php

namespace ImportWPAddon\BLMReader\Tests\Importer\File;

use ImportWPAddon\BLMReader\Tests\Utils\BLMFileTestTrait;

/**
 * @group File
 */
class BLMFileTest extends \WP_UnitTestCase {

	use BLMFileTestTrait;

	public function test_parses_header_definition_and_records() {
		list( $file, $config ) = $this->create_blm_file( 'samples/properties.blm' );

		$this->assertEquals( 3, $file->getRecordCount() );
		$this->assertEquals( '^', $file->getEOF() );
		$this->assertEquals( '~', $file->getEOR() );
		$this->assertEquals( '3', $config->get( 'property_count' ) );
		$this->assertEquals(
			array( 'AGENT_REF', 'ADDRESS_1', 'TOWN', 'POSTCODE1', 'DESCRIPTION', 'PRICE' ),
			$file->getMap()
		);

		$first = $file->getRecord( 0 );
		$this->assertStringContainsString( 'REF001', $first );
		$this->assertStringContainsString( '10 High Street', $first );
		$this->assertStringContainsString( '250000', $first );

		$second = $file->getRecord( 1 );
		$this->assertStringContainsString( 'REF002', $second );
		$this->assertStringContainsString( "Line one\nLine two with a newline.", $second );

		$third = $file->getRecord( 2 );
		$this->assertStringContainsString( 'REF003', $third );
		$this->assertStringContainsString( 'Brighton', $third );
	}

	public function test_processing_mode_indexes_sample_only() {
		list( $file ) = $this->create_blm_file( 'samples/properties.blm' );
		$file->processing( true );

		$this->assertEquals( 2, $file->getRecordCount() );
		$this->assertStringContainsString( 'REF001', $file->getRecord( 0 ) );
		$this->assertStringContainsString( 'REF002', $file->getRecord( 1 ) );
	}

	public function test_pipe_eor_is_supported() {
		list( $file ) = $this->create_blm_file( 'samples/pipe-eor.blm' );

		$this->assertEquals( 2, $file->getRecordCount() );
		$this->assertEquals( '|', $file->getEOR() );
		$this->assertEquals( '^', $file->getEOF() );
		$this->assertEquals( array( 'AGENT_REF', 'ADDRESS_1', 'TOWN' ), $file->getMap() );
		$this->assertStringContainsString( 'PIPE01', $file->getRecord( 0 ) );
		$this->assertStringContainsString( 'York', $file->getRecord( 1 ) );
	}

	public function test_sections_are_located() {
		list( $file, $config ) = $this->create_blm_file( 'samples/properties.blm' );
		$file->getRecordCount();

		$sections = $config->get( 'sections' );
		$this->assertIsArray( $sections );
		foreach ( array( 'HEADER', 'DEFINITION', 'DATA', 'END' ) as $section ) {
			$this->assertArrayHasKey( $section, $sections );
			$this->assertArrayHasKey( 'tag', $sections[ $section ] );
			$this->assertArrayHasKey( 'start', $sections[ $section ] );
		}
		$this->assertGreaterThan( 0, $sections['DATA']['length'] );
	}
}
