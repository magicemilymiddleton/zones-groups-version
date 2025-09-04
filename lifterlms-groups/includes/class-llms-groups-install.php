<?php
/**
 * LifterLMS Groups Plugin installation
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Install class
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_Install {

	/**
	 * Key name for the option where the current DB version number is stored.
	 *
	 * @var string
	 */
	const VERSION_KEY = 'llms_groups_db_version';

	/**
	 * Initialize the install class
	 * Hooks all actions
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );

		add_filter( 'llms_install_get_schema', array( __CLASS__, 'get_schema' ), 20, 2 );
	}

	/**
	 * Checks the current LLMS version and runs installer if required
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function check_version() {

		if ( ! defined( 'IFRAME_REQUEST' ) && self::get_version() !== llms_groups()->version ) {
			self::install();
			do_action( 'llms_groups_updated' );
		}
	}

	/**
	 * Get a string of table data that can be passed to dbDelta() to install custom db tables
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $schema  Existing DB schema.
	 * @param string $collate DB table collation string.
	 * @return string
	 */
	public static function get_schema( $schema, $collate ) {

		global $wpdb;

		$schema .= "
CREATE TABLE `{$wpdb->prefix}lifterlms_group_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `invite_key` char(32) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `role` varchar(10) DEFAULT 'member',
  PRIMARY KEY (`id`),
  KEY `invite_key` (`invite_key`)
) $collate;
";

		return $schema;
	}

	/**
	 * Core install function
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function install() {

		if ( ! is_blog_installed() ) {
			return;
		}

		do_action( 'llms_groups_before_install' );

		self::set_default_options();
		LLMS_Groups_Post_Type::register();
		LLMS_Roles::install();
		LLMS_Install::create_tables();

		flush_rewrite_rules();

		self::set_version();

		do_action( 'llms_groups_after_install' );
	}

	/**
	 * Retrieve the current database version
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return string
	 */
	public static function get_version() {
		return get_option( self::VERSION_KEY );
	}

	/**
	 * Set the default values of all integration settings that have default values.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	protected static function set_default_options() {

		foreach ( llms_groups()->get_integration()->get_integration_settings() as $field ) {
			if ( ! empty( $field['id'] ) && ! empty( $field['default'] ) ) {
				add_option( $field['id'], $field['default'] );
			}
		}
	}

	/**
	 * Update the LifterLMS version record to the latest version
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $version Version number.
	 * @return void
	 */
	public static function set_version( $version = null ) {
		delete_option( self::VERSION_KEY );
		add_option( self::VERSION_KEY, is_null( $version ) ? llms_groups()->version : $version );
	}
}

LLMS_Groups_Install::init();
