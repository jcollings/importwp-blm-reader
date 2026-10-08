<?php

namespace ImportWPAddon\BLMReader\Tests\Importer\Parser;

use ImportWPAddon\BLMReader\Importer\Parser\BLMParser;
use ImportWPAddon\BLMReader\Tests\Utils\BLMFileTestTrait;

/**
 * @group Parser
 */
class BLMParserTest extends \WP_UnitTestCase {

	use BLMFileTestTrait;

	public function test_query_by_definition_field_name() {
		list( $file ) = $this->create_blm_file( 'samples/properties.blm' );
		$parser       = new BLMParser( $file );

		$record = $parser->getRecord( 0 );
		$this->assertEquals( 'REF001', $record->query( 'AGENT_REF' ) );
		$this->assertEquals( '10 High Street', $record->query( 'ADDRESS_1' ) );
		$this->assertEquals( 'London', $record->query( 'TOWN' ) );
		$this->assertEquals( 'SW1A', $record->query( 'POSTCODE1' ) );
		$this->assertEquals( 'A short description.', $record->query( 'DESCRIPTION' ) );
		$this->assertEquals( '250000', $record->query( 'PRICE' ) );
		$this->assertEquals( '', $record->query( 'MISSING_FIELD' ) );
	}

	public function test_multiline_description_is_preserved() {
		list( $file ) = $this->create_blm_file( 'samples/properties.blm' );
		$parser       = new BLMParser( $file );

		$record = $parser->getRecord( 1 );
		$this->assertEquals( 'REF002', $record->query( 'AGENT_REF' ) );
		$this->assertEquals( "Line one\nLine two with a newline.", $record->query( 'DESCRIPTION' ) );
		$this->assertEquals( '175000', $record->query( 'PRICE' ) );
	}

	public function test_query_group_maps_fields() {
		list( $file ) = $this->create_blm_file( 'samples/properties.blm' );
		$parser       = new BLMParser( $file );

		$result = $parser->getRecord( 2 )->queryGroup(
			array(
				'fields' => array(
					'ref'  => '{AGENT_REF}',
					'town' => '{TOWN}',
					'price'=> '{PRICE}',
				),
			)
		);

		$this->assertEquals( 'REF003', $result['ref'] );
		$this->assertEquals( 'Brighton', $result['town'] );
		$this->assertEquals( '420000', $result['price'] );
	}

	public function test_selection_uses_curly_brace_heading() {
		list( $file ) = $this->create_blm_file( 'samples/properties.blm' );
		$parser       = new BLMParser( $file );

		// Docs: hovering a heading/value shows the selection as {AGENT_REF}.
		$result = $parser->getRecord( 0 )->query_string( '{AGENT_REF}' );
		$this->assertEquals( 'REF001', $result );
	}
}
