<?php

/**
 * Plugin Name: Techr Options
 * Description: Site and theme options for the Techr project — lightweight settings UI and API for developers.
 * Version: 1.0.0
 * Author: Techr Dev Team
 * Text Domain: techr-options
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package TechrOptions
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
   exit;
}

// DEFINITIONS
if (! defined('TECHR_OPTIONS_VERSION')) {
   define('TECHR_OPTIONS_VERSION', '1.0.0');
}
if (! defined('TECHR_OPTIONS_FILE')) {
   define('TECHR_OPTIONS_FILE', __FILE__);
}
if (! defined('TECHR_OPTIONS_PATH')) {
   // Note: plugin_dir_path returns a trailing slash, so you don't need to add another "/"
   define('TECHR_OPTIONS_PATH', plugin_dir_path(__FILE__));
}
if (! defined('TECHR_OPTIONS_URL')) {
   define('TECHR_OPTIONS_URL', plugin_dir_url(__FILE__));
}

require_once TECHR_OPTIONS_PATH . "class-initialize.php";

function techr_options_run()
{
   new Initialize_Techr_Option();
}
add_action('plugins_loaded', 'techr_options_run');

function techr_options_activate()
{
   error_log("Techr Options Plugin Activated (One-time event)");
}
register_activation_hook(TECHR_OPTIONS_FILE, 'techr_options_activate');

function techr_options_deactivate()
{
   // Cleanup tasks
}
register_deactivation_hook(TECHR_OPTIONS_FILE, 'techr_options_deactivate');
