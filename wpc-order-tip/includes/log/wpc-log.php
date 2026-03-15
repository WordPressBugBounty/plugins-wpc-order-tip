<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WPCOT_LITE' ) ? WPCOT_LITE : WPCOT_FILE, 'wpcot_activate' );
register_deactivation_hook( defined( 'WPCOT_LITE' ) ? WPCOT_LITE : WPCOT_FILE, 'wpcot_deactivate' );
add_action( 'admin_init', 'wpcot_check_version' );

function wpcot_check_version() {
	if ( ! empty( get_option( 'wpcot_version' ) ) && ( get_option( 'wpcot_version' ) < WPCOT_VERSION ) ) {
		wpc_log( 'wpcot', 'upgraded' );
		update_option( 'wpcot_version', WPCOT_VERSION, false );
	}
}

function wpcot_activate() {
	wpc_log( 'wpcot', 'installed' );
	update_option( 'wpcot_version', WPCOT_VERSION, false );
}

function wpcot_deactivate() {
	wpc_log( 'wpcot', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}