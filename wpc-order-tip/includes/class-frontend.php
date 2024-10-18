<?php
defined( 'ABSPATH' ) || exit;

class Wpcot_Frontend {
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// ajax
		add_action( 'wc_ajax_wpcot_apply_tip', [ $this, 'ajax_apply_tip' ] );
		add_action( 'wc_ajax_wpcot_remove_tip', [ $this, 'ajax_remove_tip' ] );

		// apply tips
		add_action( 'init', [ $this, 'init_session' ] );
		add_action( 'woocommerce_new_order', [ $this, 'destroy_session' ] );
		add_action( 'wp', [ $this, 'apply_tips' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_tips' ] );

		// cart
		switch ( Wpcot_Helper()->get_setting( 'position_cart', 'before_totals' ) ) {
			case 'before_cart':
				add_action( 'woocommerce_before_cart', [ $this, 'show_tips' ] );
				break;
			case 'after_coupon':
				add_action( 'woocommerce_cart_coupon', [ $this, 'show_tips' ] );
				break;
			case 'before_totals':
				add_action( 'woocommerce_before_cart_totals', [ $this, 'show_tips' ] );
				break;
			case 'after_cart':
				add_action( 'woocommerce_after_cart', [ $this, 'show_tips' ] );
				break;
		}

		// checkout
		switch ( Wpcot_Helper()->get_setting( 'position_checkout', 'before_order_review' ) ) {
			case 'before_checkout_form':
				add_action( 'woocommerce_before_checkout_form', [ $this, 'show_tips' ] );
				break;
			case 'before_order_notes':
				add_action( 'woocommerce_before_order_notes', [ $this, 'show_tips' ] );
				break;
			case 'after_customer_details':
				add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'show_tips' ] );
				break;
			case 'before_order_review':
				add_action( 'woocommerce_checkout_before_order_review_heading', [ $this, 'show_tips' ] );
				break;
			case 'after_checkout_form':
				add_action( 'woocommerce_after_checkout_form', [ $this, 'show_tips' ] );
				break;
		}

		// fragments
		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'order_review_fragments' ] );

		add_action( 'woocommerce_checkout_order_created', [ $this, 'clear_cookie' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'clear_cookie' ] );

		// shortcode
		add_shortcode( 'wpcot', [ $this, 'shortcode' ] );
	}

	public function enqueue_scripts() {
		$color_default = apply_filters( 'wpcot_active_color_default', '#cc99c2' );
		$color         = Wpcot_Helper()->get_setting( 'active_color', $color_default );
		$inline_css    = '.wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value:hover > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value.active > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom:hover > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom.active > span, .wpcot-tips .wpcot-tip .wpcot-tip-custom .wpcot-tip-custom-form input[type=button]:hover {background-color: ' . $color . ';} .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value:hover, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value.active, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom:hover, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom.active {border-color: ' . $color . ';}';

		wp_enqueue_style( 'wpcot-frontend', WPCOT_URI . 'assets/css/frontend.css' );
		wp_add_inline_style( 'wpcot-frontend', $inline_css );

		wp_enqueue_script( 'wpcot-frontend', WPCOT_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCOT_VERSION, true );
		wp_localize_script( 'wpcot-frontend', 'wpcot_vars', [
			'wc_ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'a_nonce'     => wp_create_nonce( 'wpcot_apply_tip' ),
			'r_nonce'     => wp_create_nonce( 'wpcot_remove_tip' ),
			'user_id'     => get_current_user_id(),
			'click_again' => Wpcot_Helper()->get_setting( 'click_again', 'yes' )
		] );
	}

	public function shortcode() {
		return self::get_tips();
	}

	public function show_tips() {
		echo self::get_tips();
	}

	function order_review_fragments( $fragments ) {
		$fragments['.wpcot-tips'] = self::get_tips();

		return $fragments;
	}

	public function get_tips() {
		ob_start();
		$wc_session  = WC()->session;
		$active_tips = $wc_session->get( 'wpcot_tips' );
		$tips        = Wpcot_Helper()->get_tips( 'apply' );
		$btn_style   = Wpcot_Helper()->get_setting( 'btn_style', 'square' );
		$tips_class  = 'wpcot-tips wpcot-btn-' . $btn_style;

		if ( ! empty( $tips ) ) {
			echo '<div class="' . esc_attr( $tips_class ) . '">';

			foreach ( $tips as $key => $tip ) {
				$default      = ! empty( $tip['default'] ) ? $tip['default'] : '';
				$custom       = ! empty( $tip['custom'] ) && ( $tip['custom'] === 'yes' );
				$custom_min   = ! empty( $tip['custom_min'] ) ? abs( (float) $tip['custom_min'] ) : '0';
				$custom_max   = ! empty( $tip['custom_max'] ) ? abs( (float) $tip['custom_max'] ) : '';
				$custom_step  = ! empty( $tip['custom_step'] ) ? abs( (float) $tip['custom_step'] ) : 'any';
				$custom_value = ! empty( $tip['custom_value'] ) ? abs( (float) $tip['custom_value'] ) : $custom_min;
				$active_value = ! empty( $active_tips[ $key ]['value'] ) ? $active_tips[ $key ]['value'] : 0;
				$has_custom   = true;

				$no_btn_html     = '<div class="wpcot-tip-value wpcot-tip-value-none ' . ( ! isset( $active_tips[ $key ] ) ? 'active' : '' ) . '"><span>' . Wpcot_Helper()->localization( 'no', esc_html__( 'No, Thanks!', 'wpc-order-tip' ) ) . '</span></div>';
				$no_btn_position = Wpcot_Helper()->get_setting( 'no_btn_position', 'first' );

				echo '<div class="wpcot-tip" data-key="' . esc_attr( $key ) . '" data-name="' . esc_attr( $tip['name'] ) . '">';
				echo '<div class="wpcot-tip-name">' . esc_html( $tip['name'] ) . '</div>';
				echo '<div class="wpcot-tip-desc">' . esc_html( $tip['desc'] ) . '</div>';
				echo '<div class="wpcot-tip-values">';

				if ( $no_btn_position === 'first' ) {
					echo $no_btn_html;
				}

				if ( ! empty( $tip['values'] ) ) {
					foreach ( $tip['values'] as $value ) {
						if ( $active_value == $value['value'] ) {
							$has_custom = false;
						}

						$tip_class = 'wpcot-tip-value';

						if ( $active_value == $value['value'] ) {
							$tip_class .= ' active';
						}

						if ( $default == $value['value'] ) {
							$tip_class .= ' active-default';
						}

						echo '<div class="' . esc_attr( $tip_class ) . '" data-label="' . esc_attr( $value['label'] ) . '" data-value="' . esc_attr( $value['value'] ) . '"><span>' . esc_html( $value['label'] ) . '</span></div>';
					}
				}

				if ( $has_custom && $active_value ) {
					echo '<div class="wpcot-tip-value active"><span>' . wc_price( abs( $active_value ) ) . '</span></div>';
				}

				if ( $no_btn_position === 'before_other' ) {
					echo $no_btn_html;
				}

				if ( $custom ) {
					echo '<div class="wpcot-tip-value-custom"><span>' . Wpcot_Helper()->localization( 'other', esc_html__( 'Other', 'wpc-order-tip' ) ) . '</span></div>';
				}

				if ( $no_btn_position === 'last' ) {
					echo $no_btn_html;
				}

				echo '</div><!-- /wpcot-tip-values -->';

				if ( $custom ) {
					echo '<div class="wpcot-tip-custom">';
					echo '<div class="wpcot-tip-custom-form"><div class="wpcot-tip-custom-form-inner">';
					echo '<input type="number" class="wpcot-tip-custom-value" value="' . esc_attr( $custom_value ) . '" min="' . esc_attr( $custom_min ) . '" max="' . esc_attr( $custom_max ) . '" step="' . esc_attr( $custom_step ) . '"/><input type="button" class="wpcot-tip-custom-add" value="' . esc_attr( Wpcot_Helper()->localization( 'add', esc_attr__( 'Add', 'wpc-order-tip' ) ) ) . '"/>';
					echo '</div></div><!-- /wpcot-tip-custom-form -->';
					echo '</div><!-- /wpcot-tip-custom -->';
				}

				echo '</div><!-- /wpcot-tip -->';
			}

			echo '</div>';
		}

		return apply_filters( 'wpcot_get_tips', ob_get_clean(), $tips, $active_tips );
	}

	function ajax_apply_tip() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( $_POST['security'] ), 'wpcot_apply_tip' ) ) {
			die( 'Permissions check failed!' );
		}

		$wc_session = WC()->session;
		$ct_session = $wc_session->get( 'customer' );
		$key        = sanitize_text_field( $_POST['key'] );
		$all_tips   = Wpcot_Helper()->get_tips( 'apply' );
		$tips       = [];

		if ( $ct_session ) {
			$tips = $wc_session->get( 'wpcot_tips' );
		}

		if ( isset( $all_tips[ $key ] ) ) {
			$tips[ $key ]['value']  = sanitize_text_field( $_POST['value'] );
			$_SESSION['wpcot_tips'] = serialize( $tips );

			if ( $ct_session ) {
				$wc_session->set( 'wpcot_tips', $tips );
			}
		}

		wp_send_json( $tips );
	}

	function ajax_remove_tip() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( $_POST['security'] ), 'wpcot_remove_tip' ) ) {
			die( 'Permissions check failed!' );
		}

		$wc_session = WC()->session;
		$ct_session = $wc_session->get( 'customer' );
		$key        = sanitize_text_field( $_POST['key'] );
		$tips       = [];

		if ( $ct_session ) {
			$tips = $wc_session->get( 'wpcot_tips' );
		}

		unset( $tips[ $key ] );

		$_SESSION['wpcot_tips'] = serialize( $tips );

		if ( $ct_session ) {
			$wc_session->set( 'wpcot_tips', $tips );
		}

		wp_send_json( $tips );
	}

	function init_session() {
		if ( ! session_id() && WC()->session ) {
			session_start();
		}
	}

	function destroy_session() {
		if ( ! is_admin() ) {
			$wc_session = WC()->session;

			if ( $wc_session && $wc_session->get( 'wpcot_tips' ) ) {
				$wc_session->__unset( 'wpcot_tips' );
			}

			if ( isset( $_SESSION['wpcot_tips'] ) && $_SESSION['wpcot_tips'] ) {
				unset( $_SESSION['wpcot_tips'] );
			}
		}
	}

	function apply_tips() {
		if ( is_admin() ) {
			return;
		}

		$wc_session = WC()->session;
		$tips       = $wc_session ? $wc_session->get( 'wpcot_tips' ) : [];

		if ( empty( $tips ) ) {
			if ( isset( $_SESSION['wpcot_tips'] ) && $_SESSION['wpcot_tips'] ) {
				$tips = unserialize( $_SESSION['wpcot_tips'] );
			}
		}

		// remove all tips first
		$fees     = WC()->cart->get_fees();
		$all_tips = Wpcot_Helper()->get_tips( 'apply' );

		foreach ( $fees as $key => $fee ) {
			if ( in_array( $key, array_map( 'sanitize_title', array_column( $all_tips, 'name' ) ) ) || isset( $all_tips[ $key ] ) ) {
				unset( $fees[ $key ] );
			}
		}

		if ( ! empty( $fees ) ) {
			WC()->cart->fees_api()->set_fees( $fees );
		}

		if ( $tips ) {
			foreach ( $tips as $k => $tip ) {
				// check if the tip is active
				if ( isset( $all_tips[ $k ] ) ) {
					$tip   = array_merge( $tip, [ 'key' => $k ], $all_tips[ $k ] );
					$value = self::clean_value( $tip['value'] );

					if ( ! empty( $value ) ) {
						if ( str_contains( $value, '%' ) ) {
							$subtotal = apply_filters( 'wpcot_cart_subtotal', WC()->cart->get_subtotal() );
							$amount   = ( (float) $value / 100 ) * $subtotal;
						} else {
							$amount = (float) $value;
						}

						WC()->cart->fees_api()->add_fee( apply_filters( 'wpcot_tip', [
							'id'     => $k,
							'name'   => apply_filters( 'wpcot_tip_name', $tip['name'], $tip ),
							'amount' => abs( (float) apply_filters( 'wpcot_tip_amount', $amount, $tip ) )
						], $tip ) );
					}
				}
			}
		}
	}

	function clear_cookie() {
		if ( ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $k => $v ) {
				if ( str_starts_with( $k, 'wpcot_tip_' ) ) {
					unset( $_COOKIE[ $k ] );
					setcookie( $k, '', time() - 3600, '/' );
				}
			}
		}
	}

	function clean_value( $value ) {
		return preg_replace( '/[^-.,%0-9]/', '', $value );
	}
}

return Wpcot_Frontend::instance();
