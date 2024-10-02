<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcot_Helper' ) ) {
	class Wpcot_Helper {
		protected static $instance = null;
		protected static $tips = [];
		protected static $settings = [];
		protected static $localization = [];

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$tips         = (array) get_option( 'wpcot_tips', [] );
			self::$settings     = (array) get_option( 'wpcot_settings', [] );
			self::$localization = (array) get_option( 'wpcot_localization', [] );
		}

		public static function get_tips( $context = 'edit' ) {
			$tips = self::$tips;

			if ( $context === 'apply' ) {
				if ( is_array( $tips ) && ! empty( $tips ) ) {
					foreach ( $tips as $key => $tip ) {
						if ( ! self::check_roles( $tip ) ) {
							unset( $tips[ $key ] );
						}
					}
				}
			}

			return apply_filters( 'wpcot_get_tips', $tips );
		}

		public static function check_roles( $tip ) {
			return true;
		}

		public static function get_settings() {
			return apply_filters( 'wpcot_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'wpcot_' . $name, $default );
			}

			return apply_filters( 'wpcot_get_setting', $setting, $name, $default );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return esc_html( apply_filters( 'wpcot_localization_' . $key, $str ) );
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'wpcot_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wpcot_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wpcot_generate_key', $key );
		}
	}

	function Wpcot_Helper() {
		return Wpcot_Helper::instance();
	}

	Wpcot_Helper();
}
