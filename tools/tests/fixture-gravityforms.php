<?php
/**
 * Plugin Name: Gravity Forms (CI stand-in)
 * Description: Test fixture, CI only. Copied to wp-content/plugins/gravityforms/ on the fresh install so Scout Forms Guard's "Requires Plugins: gravityforms" is met and it can be activated. It defines rgar() and nothing else: no GFAPI class, so every plugin still takes its "Gravity Forms is not here" path.
 * Version: 0.0.0-ci
 */

if ( ! function_exists( 'rgar' ) ) {
	function rgar( $array, $prop, $default = null ) {
		return ( is_array( $array ) && isset( $array[ $prop ] ) ) ? $array[ $prop ] : $default;
	}
}
