<?php
/**
 * User callable functions for RocketGeek WordPress Settings.
 *
 * @since 1.0.0
 */

if ( ! function_exists( 'rgs_get_setting' ) ) {
	/**
	 * Get a setting from an option group
	 *
	 * @param string $option_group
	 * @param string $section_id May also be prefixed with tab ID
	 * @param string $field_id
	 *
	 * @return mixed
	 */
	function rgs_get_setting( $option_group, $section_id, $field_id ) {
		$options = get_option( $option_group . '_settings' );
		if ( isset( $options[ $section_id . '_' . $field_id ] ) ) {
			return $options[ $section_id . '_' . $field_id ];
		}

		return false;
	}
}

if ( ! function_exists( 'rgs_delete_settings' ) ) {
	/**
	 * Delete all the saved settings from a settings file/option group
	 *
	 * @param string $option_group
	 */
	function rgs_delete_settings( $option_group ) {
		delete_option( $option_group . '_settings' );
	}
}

if ( ! function_exists( 'rgs_add_settings_page' ) ) {
	/**
	 * Add a settings page.
	 *
	 * @param object $obj
	 * @param array  $args
	 */
	function rgs_add_settings_page( &$obj, $args ) {
		$obj->add_settings_page( $args );
	}
}

if ( ! function_exists( 'rgs_get_pages' ) ) {
	function rgs_get_pages() {
		//echo '<pre>'; print_r( get_pages() ); echo '</pre>';
		
		$pages = get_pages();
		foreach ( $pages as $page ) {
			$select[ $page->ID ] = $page->post_title;
		}
		
		return $select;
	}
}

if ( ! function_exists( 'rktgk_build_html_tag' ) ) :
/**
 * Builds an HTML tag from provided attributes.
 * 
 * This function is included in rocketgeek utilities, but is included here for instances where
 * the rocketgeek utilities library is not also used in the plugin.
 * @link https://github.com/rocketgeek/rocketgeek-utilities
 * 
 * @since 1.0.2
 * 
 * @param  array  $args {
 *     An array of attributes to build the html tag.
 * 
 *     @type string  $tag              HTML tag to build.
 *     @type array   $attributes|$atts Array of attributes of the tag, keyed as the attribute name.
 *     @type string  $content          Content inside the wrapped tag (omit for self-closing tags).
 * }
 * @param boolean $echo
 */
function rktgk_build_html_tag( $args, $echo = false ) {
	
	// A list of self-closing tags (so $content is not used).
	$self_closing_tags = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	// Check for attributes and allow for shorthand "atts"
	if ( isset( $args['attributes'] ) ) {
		$attributes = $args['attributes'];
	} elseif ( isset( $args['atts'] ) ) {
		$attributes = $args['atts'];
	} else {
		$attributes = false;
	}

	// Assemble tag and attributes.
	$tag = '<' . esc_attr( $args['tag'] );
	if ( false != $attributes ) {
		foreach ( $attributes as $attribute => $value ) {
			// Sanitize classes.
			$value = ( 'class' == $attribute || 'id' == $attribute ) ? rktgk_sanitize_class( $value ) : $value;

			// Escape urls and remaining attributes.
			$esc_value = ( 'href' == $attribute ) ? esc_url( $value ) : esc_attr( $value );
			
			// Continue tag assembly.
			$tag .= ' ' . esc_attr( $attribute ) . '="' . $esc_value . '"';
		}
	}

	// If tag is self closing.
	if ( in_array( $args['tag'], $self_closing_tags ) ) {
		$tag .= ' />';
	} else {
		// If tag is a wrapped tag.
		$tag .= '>' . esc_html( $args['content'] ) . '</' . esc_attr( $args['tag'] ) . '>';
	}

	if ( $echo ) {
		echo $tag;
	} else {
		return $tag;
	}
}
endif;