<?php
/**
 * LifterLMS Social Learning Directory Shortcode
 *
 * [llms_group_list]
 *
 * @package LifterLMS_Social_Learning/Shortcodes/Classes
 *
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Shortcode_Group_List class.
 *
 * @since 1.0.0
 */
class LLMS_Groups_Shortcode_Group_List extends LLMS_Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	const TAG = 'llms_group_list';

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	public $tag = self::TAG;

	/**
	 * Retrieves an array of default attributes.
	 *
	 * They are automatically merged with the user submitted attributes
	 * and passed to $this->get_output().
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function get_default_attributes(): array {
		return array(
			'include' => array(),
			'exclude' => array(),
			'count'   => 10,
			'columns' => 3,
		);
	}

	/**
	 * Retrieve the actual content of the shortcode.
	 *
	 * $atts & $content are both filtered before being passed to get_output()
	 * output is filtered so the return of get_output() doesn't need its own filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_output(): string {

		$attributes = $this->get_attributes();
		$args       = array();

		if ( $attributes['include'] ?? array() ) {
			$args['post__in'] = is_array( $attributes['include'] ) ?
				array_column( $attributes['include'], 'value' ) : array_map( 'trim', explode( ',', $attributes['include'] ) );
		}

		if ( $attributes['exclude'] ?? array() ) {
			$args['post__not_in'] = is_array( $attributes['exclude'] ) ?
				array_column( $attributes['exclude'], 'value' ) : array_map( 'trim', explode( ',', $attributes['exclude'] ) );
		}

		if ( $attributes['count'] ?? 0 ) {
			$args['posts_per_page'] = (int) $attributes['count'];
		}

		$directory = LLMS_Groups_Directory::get_groups_list(
			$args
		);

		$columns = (int) ( $attributes['columns'] ?? 0 );

		$style = '';

		if ( $columns ) {
			$style = sprintf( ' style="--wp--custom--group-list--columns:%s"', $columns );
		}

		return sprintf(
			'<div class="wp-block-llms-group-list"%s>%s</div>',
			$style,
			$directory
		);
	}
}

return LLMS_Groups_Shortcode_Group_List::instance();
