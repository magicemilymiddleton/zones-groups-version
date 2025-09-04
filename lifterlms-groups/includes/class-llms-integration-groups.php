<?php
/**
 * LifterLMS Groups Integration Class.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Integration_Groups class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Reregister post types and rewrite rules before flushing permalinks when changing language settings.
 */
class LLMS_Integration_Groups extends LLMS_Abstract_Integration {

	/**
	 * Integration ID
	 *
	 * @var string
	 */
	public $id = 'groups';

	/**
	 * Integration Constructor
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Added shortcode filters.
	 *
	 * @return void
	 */
	public function configure() {

		add_action( 'init', array( $this, 'set_title_and_description' ) );

		$this->plugin_basename = plugin_basename( LLMS_GROUPS_PLUGIN_FILE );

		if ( $this->is_settings_page() ) {

			add_action( 'lifterlms_settings_integrations', array( $this, 'output_settings_scripts' ), 1000 );
			add_action( 'lifterlms_settings_save_integrations', array( $this, 'before_settings_save' ), 1 );

			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		}

		add_filter( 'llms_integration_groups_get_settings', array( $this, 'mod_default_settings' ), 1 );
		add_filter( 'llms_load_shortcodes', array( $this, 'shortcodes_load' ) );
		add_filter( 'llms_load_shortcode_path', array( $this, 'shortcodes_path' ), 10, 2 );
	}

	public function set_title_and_description() {

		$this->title       = 'LifterLMS Groups';
		$this->description = __( 'Allow group purchasing, enrollment, management, and reporting for your online courses and memberships.', 'lifterlms-groups' );
	}

	/**
	 * Modify the admin menu when post type language strings change.
	 *
	 * The post type is registered and the menu built before the option is saved resulting
	 * in the first page load (immediately following a save) to not show the updated language in
	 * the admin menu. This manually updates the language in the menu on that page load.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Fix main menu replacement strategy.
	 * @since 1.0.0 Replaced use of the deprecated `FILTER_SANITIZE_STRING` constant.
	 *
	 * @e2eCoverage AdminManageGlobalLanguageSettings
	 *
	 * @return void
	 */
	public function admin_menu() {

		$plural = llms_filter_input_sanitize_string( INPUT_POST, $this->get_option_name( 'post_name_plural' ) );
		if ( ! is_null( $plural ) && $plural !== $this->get_option( 'post_name_plural' ) ) {

			global $menu, $submenu;

			$link = 'edit.php?post_type=llms_group';

			// Find our menu item.
			foreach ( $menu as &$item ) {
				if ( ! empty( $item[2] ) && $link === $item[2] ) {
					// We found it, replace the string with the posted string.
					$item[0] = $plural;
					break;
				}
			}

			// Find our submenu & replace the string.
			if ( isset( $submenu[ $link ] ) && isset( $submenu[ $link ][5] ) ) {
				// Translators: %s = Plural user-defined group name.
				$submenu[ $link ][5][0] = sprintf( __( 'All %s', 'lifterlms-groups' ), $plural );  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Perform actions before integration settings are saved.
	 *
	 * If the post slug or plural name is to change, schedule a permalink flush on shutdown.
	 *
	 * When the Slug changes ensures that access to group pages themselves do not 404.
	 *
	 * When the Plural Name changes ensures that access to the tab on the dashboard doesn't 404.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Flush when the slug or plural post name are to be updated.
	 * @since 1.0.0 Replaced use of the deprecated `FILTER_SANITIZE_STRING` constant.
	 *
	 * @return bool `true` if actions are being run, `false` otherwise.
	 */
	public function before_settings_save() {

		$posted_slug   = llms_filter_input_sanitize_string( INPUT_POST, $this->get_option_name( 'post_slug' ) );
		$posted_plural = llms_filter_input_sanitize_string( INPUT_POST, $this->get_option_name( 'post_name_plural' ) );

		$should_flush = (
			( ! is_null( $posted_slug ) && sanitize_title( $posted_slug ) !== $this->get_option( 'post_slug' ) ) ||
			( ! is_null( $posted_plural ) && $posted_plural !== $this->get_option( 'post_name_plural' ) )
		);

		if ( $should_flush ) {

			// Re-register post type with updated slug.
			add_action( 'shutdown', array( 'LLMS_Groups_Post_Type', 'register' ), 4 );

			// Register group profile rewrites so they'll pick up the new slug from the post type.
			add_action( 'shutdown', array( 'LLMS_Groups_Profile', 'register_rewrites' ), 5 );

			// Add modified dashboard endpoints.
			add_action( 'shutdown', array( LLMS()->query, 'add_endpoints' ), 5 );

			// Flush permalinks on shutdown (after options are saved).
			return add_action( 'shutdown', 'flush_rewrite_rules' );

		}

		return false;
	}

	/**
	 * Retrieve a default image for a given image key.
	 *
	 * Used as a fallback when no default images are stored in the plugin settings.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  string $key Option key name (without the prefix).
	 * @return string
	 */
	protected function get_default_image( $key ) {

		$src = '';

		if ( 'banner_image' === $key ) {

			$src = LLMS_GROUPS_PLUGIN_ASSETS_URL . 'img/default-banner.png';

		} elseif ( 'logo_image' === $key ) {

			$theme = $this->get_theme_settings();
			$name  = md5( wp_parse_url( get_site_url(), PHP_URL_HOST ) );
			$src   = sprintf( 'https://www.gravatar.com/avatar/%1$s?&s=%2$d&d=identicon&f=y', $name, $theme['logo_dimensions'] );

		}

		/**
		 * Filter the default image source
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param string $src Image source/url.
		 * @param string $key Option key name (without the prefix).
		 */
		return apply_filters( 'llms_groups_get_default_image', $src, $key );
	}

	/**
	 * Retrieve an image for any of the global image options.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string  $key Option key name (without the prefix).
	 * @param boolean $src Whether to return the image source (default) or the attachment image id.
	 * @return string|int
	 */
	public function get_image( $key, $src = true ) {

		$option = $this->get_option( $key );
		if ( ! $option ) {
			$option = $this->get_default_image( $key );
		}

		if ( $src && is_numeric( $option ) ) {
			$option = wp_get_attachment_image_url( $option, 'full' );
		}

		return $option;
	}

	/**
	 * Get integration settings
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array[]
	 */
	public function get_integration_settings() {

		$theme = $this->get_theme_settings();

		return include LLMS_GROUPS_PLUGIN_DIR . '/includes/admin/settings-llms-groups.php';
	}

	/**
	 * Retrieve theme settings.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.15 Added `banner_auto_fit` setting, `true` by default.
	 * @since 1.0.0-beta.17 Added `logo_auto_fit` setting, `true` by default.
	 *
	 * @return array
	 */
	public function get_theme_settings() {

		/**
		 * Customize group theme settings
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param array $settings Array of theme settings.
		 */
		return apply_filters(
			'llms_groups_theme_settings',
			array(
				'banner_dimensions' => array( 1170, 280 ),
				'banner_auto_fit'   => true,
				'logo_dimensions'   => 160,
				'logo_auto_fit'     => true,
			)
		);
	}

	/**
	 * Retrieve visibility options utilized for the group settings tab on the profile.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	public function get_visibility_options() {

		$singular = strtolower( $this->get_option( 'post_name_singular' ) );

		$options = array(
			// Translators: %s = Group name (singular).
			'open'    => sprintf( __( 'Open - Anyone can see %s members and information.', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Group name (singular).
			'private' => sprintf( __( 'Private - Only logged in users can see %s members and information.', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Group name (singular).
			'closed'  => sprintf( __( 'Closed - Only %s members can see members and information.', 'lifterlms-groups' ), $singular ),
		);

		$default = $this->get_option( 'visibility' );
		if ( 'private' === $default ) {
			unset( $options['open'] );
		} elseif ( 'closed' === $default ) {
			unset( $options['open'], $options['private'] );
		}

		return $options;
	}

	/**
	 * This integration is always enabled.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return true;
	}

	/**
	 * Determines if the current page is the groups settings page.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Replaced use of the deprecated `FILTER_SANITIZE_STRING` constant.
	 *
	 * @return boolean
	 */
	private function is_settings_page() {

		return ( llms_filter_input( INPUT_GET, 'section' ) === $this->id );
	}

	/**
	 * Modify the default settings to remove the "Enabled" option.
	 *
	 * Since this integration is always enabled the option is not necessary.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  array[] $settings Default settings array.
	 * @return array[]
	 */
	public function mod_default_settings( $settings ) {

		$ids   = wp_list_pluck( $settings, 'id' );
		$index = array_search( 'llms_integration_groups_enabled', $ids, true );
		if ( false !== $index ) {
			unset( $settings[ $index ] );
		}

		return array_values( $settings );
	}

	/**
	 * Output inline javascript to improve the UX on the integration settings page.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function output_settings_scripts() {

		?>
		<script>
			( function( $ ) {

				// Toggle the "Directory Page" selector depending on the visibility option.
				$( '#<?php echo $this->get_option_name( 'visibility' ); ?>' ).on( 'change', function() {
					var $dir_page = $( '#<?php echo $this->get_option_name( 'directory_page_id' ); ?>' ).closest( 'tr' );
					if ( 'closed' === $( this ).val() ) {
						$dir_page.hide();
					} else {
						$dir_page.show();
					}
				} ).trigger( 'change' );

				// Prevent default image source paths from being stored in the database.
				$( '.llms-image-field-remove' ).each( function() {
					var $input = $( this ).next( 'input[type="hidden"]' );
					if ( isNaN( parseFloat( $input.val() ) ) ) {
						$input.val( '' );
					}
				} );

				// Update the slug preview as the input changes.
				$( '#<?php echo $this->get_option_name( 'post_slug' ); ?>' ).on( 'keyup change', function() {
					$( '#llms-groups-slug-preview' ).text( $( this ).val() );
				} ).trigger( 'change' );

			} ( jQuery ) );
		</script>
		<?php
	}

	/**
	 * Register custom shortcodes for autoloading by LifterLMS.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $shortcodes Array of shortcode class names.
	 * @return array
	 */
	public function shortcodes_load( array $shortcodes ): array {
		if ( $this->is_enabled() ) {
			$shortcodes[] = 'LLMS_Groups_Shortcode_Group_List';
		}

		return $shortcodes;
	}

	/**
	 * Set the shortcode load path for custom shortcodes.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $path  Default file to load.
	 * @param  string $class Classname.
	 * @return string
	 */
	public function shortcodes_path( string $path, string $class ): string {
		if ( 0 === strpos( $class, 'LLMS_Groups_Shortcode' ) ) {
			$class = strtolower( str_replace( array( '_' ), array( '-' ), $class ) );
			return sprintf( '%1$sincludes/shortcodes/%2$s%3$s.php', LLMS_GROUPS_PLUGIN_DIR, 'class-', $class );
		}

		return $path;
	}
}
