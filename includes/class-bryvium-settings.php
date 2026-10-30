<?php
/**
 * Plugin settings and sanitization.
 *
 * @package Bryvium_WP_File_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and retrieves the plugin's settings.
 */
final class Bryvium_Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'bryvium_wfc_settings';

	/**
	 * Settings API group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'bryvium_wfc_settings_group';

	/**
	 * Register the option with the WordPress Settings API.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Get stored settings merged with all defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Return default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'widget_title'          => __( 'Chat with us', 'bryvium-wp-file-chat' ),
			'greeting'              => __( 'Hello! How can we help you today?', 'bryvium-wp-file-chat' ),
			'fallback_message'      => __( 'Sorry, I could not find an answer to that question.', 'bryvium-wp-file-chat' ),
			'placeholder'           => __( 'Type your question', 'bryvium-wp-file-chat' ),
			'primary_color'         => '#2563eb',
			'position'              => 'bottom-right',
			'auto_display'          => false,
			'confidence_threshold'  => 0.35,
			'rate_limit_per_minute' => 20,
			'save_history'          => true,
		);
	}

	/**
	 * Sanitize every setting before it is saved.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		if ( ! is_array( $input ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid',
				__( 'The submitted settings were invalid. Existing settings have been kept.', 'bryvium-wp-file-chat' ),
				'error'
			);
			return self::get();
		}

		$current  = self::get();
		$settings = array_merge( $current, $input );

		$settings['widget_title']     = self::sanitize_text_setting(
			$settings,
			'widget_title',
			$defaults['widget_title'],
			80
		);
		$settings['greeting']         = self::sanitize_text_setting(
			$settings,
			'greeting',
			$defaults['greeting'],
			300,
			true
		);
		$settings['fallback_message'] = self::sanitize_text_setting(
			$settings,
			'fallback_message',
			$defaults['fallback_message'],
			500,
			true
		);
		$settings['placeholder']      = self::sanitize_text_setting(
			$settings,
			'placeholder',
			$defaults['placeholder'],
			120
		);

		$color = isset( $settings['primary_color'] ) && is_string( $settings['primary_color'] )
			? sanitize_hex_color( $settings['primary_color'] )
			: false;
		if ( false === $color || null === $color ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid_color',
				__( 'The primary color was invalid, so the default color was used.', 'bryvium-wp-file-chat' ),
				'error'
			);
			$color = $defaults['primary_color'];
		}
		$settings['primary_color'] = $color;

		$positions = array( 'bottom-right', 'bottom-left' );
		if ( ! isset( $settings['position'] ) || ! in_array( $settings['position'], $positions, true ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid_position',
				__( 'The widget position was invalid, so the default position was used.', 'bryvium-wp-file-chat' ),
				'error'
			);
			$settings['position'] = $defaults['position'];
		}

		$settings['auto_display'] = self::sanitize_checkbox( $settings, 'auto_display' );
		$settings['save_history'] = self::sanitize_checkbox( $settings, 'save_history' );

		$threshold                        = self::sanitize_number(
			$settings,
			'confidence_threshold',
			$defaults['confidence_threshold'],
			0,
			1,
			true
		);
		$settings['confidence_threshold'] = $threshold;

		$settings['rate_limit_per_minute'] = self::sanitize_number(
			$settings,
			'rate_limit_per_minute',
			$defaults['rate_limit_per_minute'],
			1,
			120
		);

		return array_intersect_key( $settings, $defaults );
	}

	/**
	 * Sanitize a text setting, retaining its default if invalid.
	 *
	 * @param array  $settings Submitted values.
	 * @param string $key Setting key.
	 * @param string $fallback Fallback value.
	 * @param int    $max_length Maximum character length.
	 * @param bool   $textarea Whether to preserve line breaks.
	 * @return string
	 */
	private static function sanitize_text_setting( $settings, $key, $fallback, $max_length, $textarea = false ) {
		if ( ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid_' . $key,
				__( 'A text setting was invalid, so its default was used.', 'bryvium-wp-file-chat' ),
				'error'
			);
			return $fallback;
		}

		$value = $textarea
			? sanitize_textarea_field( $settings[ $key ] )
			: sanitize_text_field( $settings[ $key ] );
		if ( '' === trim( $value ) || self::string_length( $value ) > $max_length ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid_' . $key,
				__( 'A text setting was empty or too long, so its default was used.', 'bryvium-wp-file-chat' ),
				'error'
			);
			return $fallback;
		}

		return $value;
	}

	/**
	 * Sanitize a checkbox submitted as 0 or 1.
	 *
	 * @param array  $settings Submitted values.
	 * @param string $key Setting key.
	 * @return bool
	 */
	private static function sanitize_checkbox( $settings, $key ) {
		if ( ! array_key_exists( $key, $settings ) ) {
			return false;
		}

		$value = $settings[ $key ];
		if ( in_array( $value, array( true, 1, '1', 'true', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( false, 0, '0', 'false', 'off' ), true ) ) {
			return false;
		}

		add_settings_error(
			self::OPTION_NAME,
			'bryvium_wfc_settings_invalid_' . $key,
			__( 'A checkbox setting was invalid, so it was turned off.', 'bryvium-wp-file-chat' ),
			'error'
		);
		return false;
	}

	/**
	 * Validate and clamp a numeric setting.
	 *
	 * @param array     $settings Submitted values.
	 * @param string    $key Setting key.
	 * @param int|float $fallback Fallback value.
	 * @param int|float $minimum Minimum allowed value.
	 * @param int|float $maximum Maximum allowed value.
	 * @param bool      $decimal Whether to preserve decimal values.
	 * @return int|float
	 */
	private static function sanitize_number( $settings, $key, $fallback, $minimum, $maximum, $decimal = false ) {
		if ( ! isset( $settings[ $key ] ) || ! is_numeric( $settings[ $key ] ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'bryvium_wfc_settings_invalid_' . $key,
				__( 'A number setting was invalid, so its default was used.', 'bryvium-wp-file-chat' ),
				'error'
			);
			return $fallback;
		}

		$value = $decimal ? (float) $settings[ $key ] : (int) $settings[ $key ];
		$value = min( $maximum, max( $minimum, $value ) );

		return $decimal ? round( $value, 2 ) : (int) $value;
	}

	/**
	 * Count Unicode characters, with a safe fallback.
	 *
	 * @param string $text Text to count.
	 * @return int
	 */
	private static function string_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text, 'UTF-8' );
		}

		$length = preg_match_all( '/./us', $text, $matches );
		return false === $length ? strlen( $text ) : $length;
	}
}
