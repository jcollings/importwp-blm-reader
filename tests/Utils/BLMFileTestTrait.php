<?php

namespace ImportWPAddon\BLMReader\Tests\Utils;

use ImportWP\Common\Importer\Config\Config;
use ImportWPAddon\BLMReader\Importer\File\BLMFile;

trait BLMFileTestTrait {

	/**
	 * @param string $relative_sample Path under tests/, e.g. samples/properties.blm
	 * @return array{0:BLMFile,1:Config,2:string} File, config, and config path.
	 */
	protected function create_blm_file( $relative_sample ) {
		$config_file = tempnam( sys_get_temp_dir(), 'blm_config_' );
		$config      = new Config( $config_file );
		$file        = new BLMFile( IWP_BLM_TEST_ROOT . '/' . $relative_sample, $config );

		return array( $file, $config, $config_file );
	}

	/**
	 * Allow the BLM sample directory for importer local_file().
	 *
	 * @param string[] $paths
	 * @return string[]
	 */
	public function whitelist_blm_test_dir( $paths ) {
		$paths[] = realpath( IWP_BLM_TEST_ROOT );
		return $paths;
	}
}
