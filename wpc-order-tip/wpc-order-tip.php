<?php
/*
Plugin Name: WPC Order Tip for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Order Tip is a plugin that enables customers to add extra amounts to their order as a tip or donation to the seller or specified recipients.
Version: 3.1.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-order-tip
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.3
*/

! defined( 'WPCOT_VERSION' ) && define( 'WPCOT_VERSION', '3.1.1' );
! defined( 'WPCOT_LITE' ) && define( 'WPCOT_LITE', __FILE__ );
! defined( 'WPCOT_FILE' ) && define( 'WPCOT_FILE', __FILE__ );
! defined( 'WPCOT_DIR' ) && define( 'WPCOT_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCOT_URI' ) && define( 'WPCOT_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCOT_SUPPORT' ) && define( 'WPCOT_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcot&utm_campaign=wporg' );
! defined( 'WPCOT_REVIEWS' ) && define( 'WPCOT_REVIEWS', 'https://wordpress.org/support/plugin/wpc-order-tip/reviews/?filter=5' );
! defined( 'WPCOT_CHANGELOG' ) && define( 'WPCOT_CHANGELOG', 'https://wordpress.org/plugins/wpc-order-tip/#developers' );
! defined( 'WPCOT_DISCUSSION' ) && define( 'WPCOT_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-order-tip' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCOT_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcot_init' ) ) {
	add_action( 'plugins_loaded', 'wpcot_init', 11 );

	function wpcot_init() {
		load_plugin_textdomain( 'wpc-order-tip', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcot_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcot' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcot {
				public function __construct() {
					require_once trailingslashit( WPCOT_DIR ) . 'includes/class-helper.php';
					require_once trailingslashit( WPCOT_DIR ) . 'includes/class-backend.php';
					require_once trailingslashit( WPCOT_DIR ) . 'includes/class-reports.php';
					require_once trailingslashit( WPCOT_DIR ) . 'includes/class-frontend.php';
				}
			}

			new WPCleverWpcot();
		}
	}
}

if ( ! function_exists( 'wpcot_notice_wc' ) ) {
	function wpcot_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Order Tip</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
