<?php

/**
 * Plugin Name: Import WP - BLM File Reader Addon
 * Plugin URI: https://www.importwp.com
 * Description: Allow Import WP to import BLM Files.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 0.0.1 
 * Author URI: https://www.importwp.com
 * Network: True
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('IWP_BLM_READER_FILE', __FILE__);
define('IWP_BLM_READER_VERSION', '0.0.1');

add_action('admin_init', 'iwp_blm_reader_check');

function iwp_blm_reader_requirements_met()
{
    return false === (is_admin() && current_user_can('activate_plugins') &&  (!function_exists('import_wp') || version_compare(IWP_VERSION, '2.6.2', '<')));
}

function iwp_blm_reader_check()
{
    if (!iwp_blm_reader_requirements_met()) {

        add_action('admin_notices', 'iwp_blm_reader_notice');

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function iwp_blm_reader_setup()
{
    if (!iwp_blm_reader_requirements_met()) {
        return;
    }

    $base_path = dirname(__FILE__);

    require_once $base_path . '/setup.php';

    // Install updater
    if (file_exists($base_path . '/updater.php') && !class_exists('IWP_Updater')) {
        require_once $base_path . '/updater.php';
    }

    if (class_exists('IWP_Updater')) {
        $updater = new IWP_Updater(__FILE__, 'importwp-blm-reader');
        $updater->initialize();
    }
}
add_action('plugins_loaded', 'iwp_blm_reader_setup', 9);

function iwp_blm_reader_notice()
{
    echo '<div class="error">';
    echo '<p><strong>Import WP - BLM File Reader Addon</strong> requires that you have <strong>Import WP v2.6.2 or newer</strong> installed.</p>';
    echo '</div>';
}
