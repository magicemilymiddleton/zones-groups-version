<?php
/**
 * LifterLMS Groups Plugin
 *
 * @package LifterLMS_Groups/Main
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.19
 *
 * Plugin Name: LifterLMS Groups
 * Plugin URI: https://lifterlms.com/product/groups/
 * Description: Allow group purchasing, enrollment, management, and reporting for your online courses and memberships.
 * Version: 1.2.2
 * Author: LifterLMS
 * Author URI: https://lifterlms.com
 * Text Domain: lifterlms-groups
 * Domain Path: /i18n
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.5
 * Tested up to: 6.3
 * Requires PHP: 7.3
 * LLMS requires at least: 7.2.0
 * LLMS tested up to: 7.4.2
 */

defined( 'ABSPATH' ) || exit;

// Define constants.
if ( ! defined( 'LLMS_GROUPS_PLUGIN_FILE' ) ) {
	define( 'LLMS_GROUPS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'LLMS_GROUPS_PLUGIN_DIR' ) ) {
	define( 'LLMS_GROUPS_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'LLMS_GROUPS_PLUGIN_URL' ) ) {
	define( 'LLMS_GROUPS_PLUGIN_URL', plugin_dir_url( LLMS_GROUPS_PLUGIN_FILE ) );
}

if ( ! defined( 'LLMS_GROUPS_PLUGIN_ASSETS_URL' ) ) {
	define( 'LLMS_GROUPS_PLUGIN_ASSETS_URL', LLMS_GROUPS_PLUGIN_URL . 'assets/' );
}

// Load the plugin.
if ( ! class_exists( 'LifterLMS_Groups' ) ) {
	require_once LLMS_GROUPS_PLUGIN_DIR . 'class-lifterlms-groups.php';
}

/**
 * Retrieve the instance of LifterLMS_Groups
 *
 * @since 1.0.0-beta.1
 *
 * @return LifterLMS_Groups
 */
function llms_groups() {
	return LifterLMS_Groups::instance();
}
return llms_groups();
