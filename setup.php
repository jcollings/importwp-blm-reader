<?php

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ConfigInterface;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\ParserInterface;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;
use ImportWPAddon\BLMReader\Importer\File\BLMFile;
use ImportWPAddon\BLMReader\Importer\Parser\BLMParser;

require_once __DIR__ . '/class/autoload.php';

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add BLM file extension into importers allowed file type list
 *
 * @param string[] $allowed
 * @return void
 */
function iwp_blmr_extend_allowed_file_types($allowed = [])
{
    $allowed[] = 'blm';
    return $allowed;
}
add_filter('iwp/importer/datasource/allowed_file_types', 'iwp_blmr_extend_allowed_file_types');

/**
 * Parse filename to determine if BLM filetype
 *
 * @param string $filetype
 * @param string $file
 * @return string
 */
function iwp_blmr_get_filetype_from_ext($filetype, $file)
{
    if (stripos($file, '.blm')) {
        $filetype = 'blm';
    }

    return $filetype;
}
add_filter('iwp/get_filetype_from_ext', 'iwp_blmr_get_filetype_from_ext', 10, 2);

/**
 * Process attached BLM file
 * 
 * @param ImporterModel $importer
 * @return void
 */
function iwp_blmr_process_file($importer)
{
    /**
     * @var ImporterManager $importer_manager
     */
    $importer_manager = Container::getInstance()->get('importer_manager');
    $config = $importer_manager->get_config($importer);
    $file = iwp_blm_get_file($importer, $config);
    $file->processing(true);
    $count = $file->getRecordCount();

    $importer->setFileSetting('count', $count);
}
add_action('iwp/file-process/blm', 'iwp_blmr_process_file', 10);

/**
 * Get BLM file.
 *
 * @param ImporterModel $importer
 * @param ConfigInterface $config
 * @return BLMFile
 */
function iwp_blm_get_file($importer, $config = null)
{
    $file = new BLMFile($importer->getFile(), $config);
    return $file;
}

/**
 * Generate BLM preview record data.
 * 
 * @param mixed $result
 * @param ImporterModel $importer_model
 * @return array
 */
function iwp_blmr_preview_file($result, $importer_model)
{
    /**
     * @var ImporterManager $importer_manager
     */
    $importer_manager = Container::getInstance()->get('importer_manager');

    $config = $importer_manager->get_config($importer_model, true);

    $file = iwp_blm_get_file($importer_model, $config);
    $file->processing(true);

    $record = $file->getRecord(0);

    return [
        'row' => explode($file->getEOF(), $record),
        'headings' => $file->getMap()
    ];
}
add_filter('iwp/file-preview/blm', 'iwp_blmr_preview_file', 10, 2);

/**
 * Get BLM file parser.
 *
 * @param ImporterModel $importer_model
 * @param ConfigInterface $config
 * @return ParserInterface
 */
function iwp_blmr_get_file_parser($importer_model, $config)
{
    $file = iwp_blm_get_file($importer_model, $config);
    return new BLMParser($file);
}

/**
 * Preview BLM record data based on input fields.
 *
 * @param mixed $result
 * @param ImporterModel $importer_model
 * @param string[] $fields
 * @return void
 */
function iwp_blmr_preview_record($result, $importer_model, $fields)
{
    /**
     * @var ImporterManager $importer_manager
     */
    $importer_manager = Container::getInstance()->get('importer_manager');

    $config = $importer_manager->get_config($importer_model, true);
    $parser = iwp_blmr_get_file_parser($importer_model, $config);

    $record = $parser->getRecord(0);

    return $record->queryGroup(['fields' => $fields]);
}
add_filter('iwp/record-preview/blm', 'iwp_blmr_preview_record', 10, 3);

/**
 * Load BLM Parser for importer
 *
 * @param ParserInterface $parser
 * @param ImporterModel $importer_model
 * @param ConfigInterface $config
 * @return ParserInterface
 */
function iwp_blmr_init_importer_parser($parser, $importer_model, $config)
{
    if ($importer_model->getParser() !== 'blm') {
        return $parser;
    }

    return iwp_blmr_get_file_parser($importer_model, $config);
}
add_filter('iwp/importer/init_parser', 'iwp_blmr_init_importer_parser', 10, 3);

/**
 * Enqueue Importer JS and CSS files
 *
 * @return void
 */
function iwp_blmr_enqueue_plugin_assets()
{
    $properties = Container::getInstance()->get('properties');
    wp_enqueue_script('iwp-blm-reader-bundle', plugin_dir_url(IWP_BLM_READER_FILE) . 'dist/js/bundle.js', [$properties->plugin_domain . '-bundle'], IWP_BLM_READER_VERSION, 'all');
    wp_enqueue_style('iwp-blm-reader-bundle-styles', plugin_dir_url(IWP_BLM_READER_FILE) . 'dist/css/style.bundle.css', [$properties->plugin_domain . '-bundle-styles'], IWP_BLM_READER_VERSION, 'all');
}
add_action('iwp/enqueue_assets', 'iwp_blmr_enqueue_plugin_assets');

/**
 * @var ImporterModel $iwp_blmr_importer_model 
 */
global $iwp_blmr_importer_model;

function iwp_blmr_get_tmp_upload_dir($importer_id)
{
    /**
     * @var Filesystem $filesystem
     */
    $filesystem = Container::getInstance()->get('filesystem');

    $upload_path = $filesystem->get_temp_directory();

    $upload_path .= DIRECTORY_SEPARATOR . 'blm';
    if (!is_dir($upload_path)) {
        mkdir($upload_path);
    }

    $upload_path .= DIRECTORY_SEPARATOR . $importer_id;
    if (!is_dir($upload_path)) {
        mkdir($upload_path);
    }

    return $upload_path;
}

function iwp_blmr_attachment($attachments = '')
{
    /**
     * @var ImporterModel $iwp_blmr_importer_model 
     */
    global $iwp_blmr_importer_model;
    if (!$iwp_blmr_importer_model) {
        return $attachments;
    }

    $parts = array_filter(array_map('trim', explode(',', $attachments)));

    if (empty($parts)) {
        return '';
    }

    $output = [];
    $zip = false;
    $zip_path = false;

    switch ($iwp_blmr_importer_model->getDatasource()) {
        case 'local':

            Logger::debug("Downloading Attachment from Local Zip");

            $upload_path = iwp_blmr_get_tmp_upload_dir($iwp_blmr_importer_model->getId());

            // if using with multiple file reader
            $multiple_file = get_post_meta($iwp_blmr_importer_model->getId(), '_import_file_mf', true);
            if (!empty($multiple_file)) {
                $file_path = $multiple_file;
            } else {
                $file_path = $iwp_blmr_importer_model->getDatasourceSetting('local_url');
            }

            $zip_path = substr_replace($file_path, 'zip', strrpos($file_path, '.') + 1);

            Logger::debug("BLM Upload Path: " . $upload_path);
            Logger::debug("BLM File Path: " . $file_path);
            Logger::debug("BLM Zip Path: " . $zip_path);

            if (!file_exists($zip_path)) {
                break;
            }

            $zip = new \ZipArchive();
            break;
        case 'remote':

            Logger::debug("Downloading Attachment from Remote Zip");

            $upload_path = iwp_blmr_get_tmp_upload_dir($iwp_blmr_importer_model->getId());
            $file_path = $iwp_blmr_importer_model->getDatasourceSetting('remote_url');
            $zip_url = substr_replace($file_path, 'zip', strrpos($file_path, '.') + 1);
            $zip_path = $upload_path . DIRECTORY_SEPARATOR . basename($zip_url);

            Logger::debug("BLM Upload Path: " . $upload_path);
            Logger::debug("BLM File Path: " . $file_path);
            Logger::debug("BLM Zip Path: " . $zip_path);

            $zip = new \ZipArchive();

            if (!file_exists($zip_path)) {

                /**
                 * @var Http $http
                 */
                $http = Container::getInstance()->get('http');
                if (!$http->download_file($zip_url, $zip_path)) {

                    if ($zip->open($zip_path, ZipArchive::CREATE)) {
                        $zip->close();
                    }
                }
            }
            break;
    }

    if ($zip) {

        Logger::debug("Opening BLM zip");

        if ($zip->open($zip_path) === true) {

            foreach ($parts as $part) {

                Logger::debug("BLM importing Media: " . $part);

                $output_data = $zip->getFromName($part);
                if ($output_data === false) {
                    Logger::debug("Media not in zip: " . $part);
                    $output[] = '';
                    continue;
                }

                $output_path = $upload_path . DIRECTORY_SEPARATOR . $part;
                if (!file_put_contents($output_path, $output_data) > 0) {
                    $output[] = '';
                }

                $output[] = $output_path;
            }

            $zip->close();
        }
    } else {

        Logger::debug("Unable to open BLM zip");
    }

    return implode(',', $output);
}


add_action('iwp/register_events', function ($event_handler) {

    /**
     * @var EventHandler $event_handler
     */
    $event_handler->listen('importer_manager.import', function ($importer_model) {

        if ($importer_model->getParser() == 'blm') {
            global $iwp_blmr_importer_model;
            $iwp_blmr_importer_model = $importer_model;
        }

        return $importer_model;
    });

    $event_handler->listen('importer_manager.import_shutdown', function ($importer_model) {

        if ($importer_model->getParser() == 'blm') {

            global $iwp_blmr_importer_model;

            $upload_path = iwp_blmr_get_tmp_upload_dir($iwp_blmr_importer_model->getId());

            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

            $filesystem = new \WP_Filesystem_Direct(false);

            $filesystem->delete($upload_path, true);

            $iwp_blmr_importer_model = null;
        }

        return $importer_model;
    });
});

function iwp_blmr_price_qualifier($input = '')
{

    switch ($input) {
        case 0:
            $input = 'Default';
            break;
        case 2:
            $input = 'Guide Price';
            break;
        case 4:
            $input = 'Offers in Excess of';
            break;
        case 5:
            $input = 'OIRO';
            break;
        case 5:
            $input = 'From';
            break;
        case 9:
        case 11:
            $input = 'Fractional Ownership';
            break;
    }

    return $input;
}

/**
 * Move zip file to processed folder after import
 * 
 * @param string $file_path
 * @param string $desination_path
 */
add_action('iwp/multiple_files/after_file_processed', function ($file_path, $destination_path) {

    /**
     * @var Filesystem $filesystem
     */
    $filesystem = Container::getInstance()->get('filesystem');

    $zip_path = substr_replace($file_path, 'zip', strrpos($file_path, '.') + 1);
    if (file_exists($zip_path) && $filesystem->copy($zip_path, $destination_path . basename($zip_path))) {
        @unlink($zip_path);
    }
}, 10, 2);
