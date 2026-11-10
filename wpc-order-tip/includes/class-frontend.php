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
		add_action( 'woocommerce_new_order', [ $this, 'destroy_session' ] );
		add_action( 'wp', [ $this, 'apply_tips' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_tips' ] );

		// round tip total (amount + tax) by adjusting pre-tax amount
		add_filter( 'wpcot_tip_amount', [ $this, 'round_tip_amount' ], 10, 2 );

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

		// WooCommerce Cart/Checkout Blocks integration (Slot/Fill + Store API)
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_block_scripts' ] );

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', [ $this, 'register_store_api' ] );
		}
	}

	/**
	 * Enqueue the script that renders the tips inside the Cart/Checkout blocks.
	 */
	public function enqueue_block_scripts() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		// Skip classic (shortcode) pages, the script is only useful for the Cart/Checkout blocks
		$has_blocks = has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' );

		if ( ! apply_filters( 'wpcot_enqueue_block_scripts', $has_blocks ) ) {
			return;
		}

		wp_enqueue_script( 'wpcot-block-tips', WPCOT_URI . 'assets/js/block-tips.js', [
			'wc-blocks-checkout',
			'wp-data',
			'wp-element',
			'wp-plugins'
		], WPCOT_VERSION, true );
	}

	/**
	 * Expose tips data and an update callback to the Store API used by the Cart/Checkout blocks.
	 */
	public function register_store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data( [
			'endpoint'        => 'cart',
			'namespace'       => 'wpcot',
			'data_callback'   => [ $this, 'get_block_tips_data' ],
			'schema_callback' => [ $this, 'get_block_tips_schema' ],
			'schema_type'     => ARRAY_A,
		] );

		woocommerce_store_api_register_update_callback( [
			'namespace' => 'wpcot',
			'callback'  => [ $this, 'block_update_tip' ],
		] );
	}

	/**
	 * Schema of the data exposed to the Cart/Checkout blocks.
	 *
	 * @return array
	 */
	public function get_block_tips_schema() {
		$string = [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ];

		return [
			'user_id'         => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
			'btn_style'       => $string,
			'no_btn_position' => $string,
			'click_again'     => [ 'type' => 'boolean', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
			'none_label'      => $string,
			'other_label'     => $string,
			'add_label'       => $string,
			'tips'            => [
				'type'     => 'array',
				'context'  => [ 'view', 'edit' ],
				'readonly' => true,
				'items'    => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * Build the tips state for the Cart/Checkout blocks.
	 * Mirrors the logic of get_tips().
	 *
	 * @return array
	 */
	public function get_block_tips_data() {
		$decode = function ( $text ) {
			// Wpcot_Helper()->localization() returns escaped text, but React escapes text on its own
			return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		};

		$data = [
			'user_id'         => get_current_user_id(),
			'btn_style'       => (string) Wpcot_Helper()->get_setting( 'btn_style', 'square' ),
			'no_btn_position' => (string) Wpcot_Helper()->get_setting( 'no_btn_position', 'first' ),
			'click_again'     => Wpcot_Helper()->get_setting( 'click_again', 'yes' ) === 'yes',
			'none_label'      => $decode( Wpcot_Helper()->localization( 'no', __( 'No, Thanks!', 'wpc-order-tip' ) ) ),
			'other_label'     => $decode( Wpcot_Helper()->localization( 'other', __( 'Other', 'wpc-order-tip' ) ) ),
			'add_label'       => $decode( Wpcot_Helper()->localization( 'add', __( 'Add', 'wpc-order-tip' ) ) ),
			'tips'            => [],
		];

		$wc_session  = WC()->session;
		$active_tips = $wc_session ? $wc_session->get( 'wpcot_tips' ) : [];
		$active_tips = is_array( $active_tips ) ? $active_tips : [];

		foreach ( (array) Wpcot_Helper()->get_tips( 'apply' ) as $key => $tip ) {
			$default      = ! empty( $tip['default'] ) ? $tip['default'] : '';
			$custom_min   = ! empty( $tip['custom_min'] ) ? abs( (float) $tip['custom_min'] ) : 0;
			$custom_max   = ! empty( $tip['custom_max'] ) ? abs( (float) $tip['custom_max'] ) : '';
			$custom_step  = ! empty( $tip['custom_step'] ) ? abs( (float) $tip['custom_step'] ) : 'any';
			$custom_value = ! empty( $tip['custom_value'] ) ? abs( (float) $tip['custom_value'] ) : $custom_min;
			$selected     = isset( $active_tips[ $key ] );
			$active_value = ! empty( $active_tips[ $key ]['value'] ) ? $active_tips[ $key ]['value'] : '';
			$has_custom   = $selected && $active_value !== '';
			$values       = [];

			if ( ! empty( $tip['values'] ) && is_array( $tip['values'] ) ) {
				foreach ( $tip['values'] as $value ) {
					if ( ! isset( $value['value'] ) ) {
						continue;
					}

					$is_active = $selected && $active_value !== '' && $active_value == $value['value'];

					if ( $is_active ) {
						$has_custom = false;
					}

					$values[] = [
						'label'      => (string) ( $value['label'] ?? '' ),
						'value'      => (string) $value['value'],
						'active'     => $is_active,
						'is_default' => $default !== '' && $default == $value['value'],
					];
				}
			}

			$data['tips'][] = [
				'key'                => (string) $key,
				'name'               => (string) ( $tip['name'] ?? '' ),
				'desc'               => (string) ( $tip['desc'] ?? '' ),
				'selected'           => $selected,
				'values'             => $values,
				// Selected amount that is not one of the predefined values (e.g. a custom amount)
				'custom_active_html' => ( $has_custom && is_numeric( $active_value ) ) ? wp_kses_post( wc_price( abs( $active_value ) ) ) : '',
				'custom'             => ! empty( $tip['custom'] ) && $tip['custom'] === 'yes',
				'custom_min'         => $custom_min,
				'custom_max'         => $custom_max,
				'custom_step'        => $custom_step,
				'custom_value'       => $custom_value,
			];
		}

		return $data;
	}

	/**
	 * Store API update callback: apply or remove a tip from the Cart/Checkout blocks.
	 *
	 * @param array $data Data sent by extensionCartUpdate().
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the request is not allowed.
	 */
	public function block_update_tip( $data ) {
		$error = function ( $message ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'wpcot_tip_error', esc_html( $message ), 400 );
		};

		$wc_session = WC()->session;
		$action     = isset( $data['action'] ) ? sanitize_key( $data['action'] ) : '';
		$key        = isset( $data['key'] ) ? sanitize_text_field( wp_unslash( $data['key'] ) ) : '';
		$all_tips   = Wpcot_Helper()->get_tips( 'apply' );

		if ( ! $wc_session || ! isset( $all_tips[ $key ] ) ) {
			$error( __( 'Invalid tip.', 'wpc-order-tip' ) );
		}

		$tips = $wc_session->get( 'wpcot_tips' ) ?: [];

		if ( $action === 'remove' ) {
			unset( $tips[ $key ] );
		} elseif ( $action === 'apply' ) {
			$value = isset( $data['value'] ) ? sanitize_text_field( wp_unslash( (string) $data['value'] ) ) : '';
			$value = $this->validate_block_tip_value( $all_tips[ $key ], $value );

			if ( $value === false ) {
				$error( __( 'Invalid tip amount.', 'wpc-order-tip' ) );
			}

			$tips[ $key ]['value'] = $value;
		} else {
			$error( __( 'Invalid tip.', 'wpc-order-tip' ) );
		}

		$wc_session->set( 'wpcot_tips', $tips );
	}

	/**
	 * Check a submitted value against the tip configuration.
	 * Predefined values are accepted as is; other values are only accepted when the custom amount is enabled.
	 *
	 * @param array  $tip   The tip configuration data.
	 * @param string $value The submitted value.
	 *
	 * @return string|false The value to store, or false when it is not allowed.
	 */
	public function validate_block_tip_value( $tip, $value ) {
		if ( $value === '' ) {
			return false;
		}

		if ( ! empty( $tip['values'] ) && is_array( $tip['values'] ) ) {
			foreach ( $tip['values'] as $item ) {
				if ( isset( $item['value'] ) && (string) $item['value'] === $value ) {
					return $value;
				}
			}
		}

		if ( empty( $tip['custom'] ) || $tip['custom'] !== 'yes' || ! is_numeric( $value ) ) {
			return false;
		}

		$amount     = abs( (float) $value );
		$custom_min = ! empty( $tip['custom_min'] ) ? abs( (float) $tip['custom_min'] ) : 0;
		$custom_max = ! empty( $tip['custom_max'] ) ? abs( (float) $tip['custom_max'] ) : 0;

		$amount = max( $amount, $custom_min );

		if ( $custom_max > 0 ) {
			$amount = min( $amount, $custom_max );
		}

		return $amount > 0 ? (string) $amount : false;
	}

	public function enqueue_scripts() {
		$color_default = apply_filters( 'wpcot_active_color_default', '#cc99c2' );
		$color         = Wpcot_Helper()->get_setting( 'active_color', $color_default );
		$inline_css    = '.wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value:hover > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value.active > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom:hover > span, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom.active > span, .wpcot-tips .wpcot-tip .wpcot-tip-custom .wpcot-tip-custom-form input[type=button]:hover {background-color: ' . $color . ';} .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value:hover, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value.active, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom:hover, .wpcot-tips .wpcot-tip .wpcot-tip-values .wpcot-tip-value-custom.active {border-color: ' . $color . ';}';

		wp_enqueue_style( 'wpcot-frontend', WPCOT_URI . 'assets/css/frontend.css', [], WPCOT_VERSION );
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
		echo wp_kses_post( self::get_tips() );
	}

	function order_review_fragments( $fragments ) {
		$fragments['.wpcot-tips'] = self::get_tips();

		return $fragments;
	}

	public function get_tips() {
		ob_start();
		$wc_session  = WC()->session;
		$active_tips = $wc_session ? $wc_session->get( 'wpcot_tips' ) : [];
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
					echo wp_kses_post( $no_btn_html );
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
					echo '<div class="wpcot-tip-value active"><span>' . wp_kses_post( wc_price( abs( $active_value ) ) ) . '</span></div>';
				}

				if ( $no_btn_position === 'before_other' ) {
					echo wp_kses_post( $no_btn_html );
				}

				if ( $custom ) {
					echo '<div class="wpcot-tip-value-custom"><span>' . esc_html( Wpcot_Helper()->localization( 'other', esc_html__( 'Other', 'wpc-order-tip' ) ) ) . '</span></div>';
				}

				if ( $no_btn_position === 'last' ) {
					echo wp_kses_post( $no_btn_html );
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
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), 'wpcot_apply_tip' ) ) {
			die( 'Permissions check failed!' );
		}

		$wc_session = WC()->session;
		$key        = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) : '';
		$all_tips   = Wpcot_Helper()->get_tips( 'apply' );

		// Always read from WC session — works for both guests and logged-in users
		$tips = $wc_session->get( 'wpcot_tips' ) ?: [];

		if ( isset( $all_tips[ $key ] ) ) {
			$tips[ $key ]['value'] = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ) : '';

			// Write to WC session — works for both guests (cookie + DB) and logged-in users
			$wc_session->set( 'wpcot_tips', $tips );

			// For guest users: ensure session cookie is set and data is flushed to DB
			// before the response ends, so the next request (fragment refresh) can read it
			if ( ! is_user_logged_in() ) {
				if ( method_exists( $wc_session, 'set_customer_session_cookie' ) ) {
					$wc_session->set_customer_session_cookie( true );
				}

				if ( method_exists( $wc_session, 'save_data' ) ) {
					$wc_session->save_data();
				}
			}
		}

		wp_send_json( $tips );
	}

	function ajax_remove_tip() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), 'wpcot_remove_tip' ) ) {
			die( 'Permissions check failed!' );
		}

		$wc_session = WC()->session;
		$key        = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) : '';

		// Always read from WC session — works for both guests and logged-in users
		$tips = $wc_session->get( 'wpcot_tips' ) ?: [];

		unset( $tips[ $key ] );

		// Write to WC session — works for both guests (cookie + DB) and logged-in users
		$wc_session->set( 'wpcot_tips', $tips );

		// For guest users: ensure session cookie is set and data is flushed to DB
		// before the response ends, so the next request (fragment refresh) can read it
		if ( ! is_user_logged_in() ) {
			if ( method_exists( $wc_session, 'set_customer_session_cookie' ) ) {
				$wc_session->set_customer_session_cookie( true );
			}

			if ( method_exists( $wc_session, 'save_data' ) ) {
				$wc_session->save_data();
			}
		}

		wp_send_json( $tips );
	}

	function destroy_session() {
		if ( ! is_admin() ) {
			$wc_session = WC()->session;

			if ( $wc_session && $wc_session->get( 'wpcot_tips' ) ) {
				$wc_session->__unset( 'wpcot_tips' );
			}
		}
	}

	function apply_tips() {
		if ( is_admin() ) {
			return;
		}

		// Skip during Elementor editor save/preview requests to avoid unnecessary cart computation
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		$wc_session = WC()->session;
		$tips       = $wc_session ? $wc_session->get( 'wpcot_tips' ) : [];

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

							// Round the tip amount before tax if the setting is enabled
							if ( in_array( Wpcot_Helper()->get_setting( 'round_tip', 'no' ), [ 'yes', 'before_tax' ], true ) ) {
								$amount = round( $amount );
							}
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

	/**
	 * Round tip amount so that (amount + tax) is a whole number.
	 *
	 * Uses WC_Tax::get_rates() to calculate the actual tax rate for the
	 * tip's tax class, then adjusts the pre-tax amount so that the
	 * inclusive total rounds to the nearest integer.
	 *
	 * @param float $amount The tip amount (pre-tax).
	 * @param array $tip    The tip configuration data.
	 *
	 * @return float
	 */
	function round_tip_amount( $amount, $tip ) {
		$round_tip = Wpcot_Helper()->get_setting( 'round_tip', 'no' );

		if ( $round_tip !== 'after_tax' ) {
			return $amount;
		}

		$taxable = wc_tax_enabled() && wc_string_to_bool( $tip['taxable'] ?? 'no' );

		if ( $taxable ) {
			$tax_class = $tip['tax_class'] ?? '';
			$tax_rates = WC_Tax::get_rates( $tax_class );
			$taxes     = WC_Tax::calc_tax( $amount, $tax_rates, false );
			$tax_total = array_sum( $taxes );
			$total     = $amount + $tax_total;

			// Skip if already a whole number
			if ( abs( $total - round( $total ) ) < 0.001 ) {
				return $amount;
			}

			$rounded_total = round( $total );

			if ( $tax_total > 0 ) {
				// Reverse-calculate pre-tax amount from the rounded total
				$tax_rate = $tax_total / $amount;
				$amount   = $rounded_total / ( 1 + $tax_rate );
			} else {
				$amount = $rounded_total;
			}
		} else {
			// No tax, just round the amount directly
			$amount = round( $amount );
		}

		return $amount;
	}

	function clean_value( $value ) {
		return preg_replace( '/[^-.,%0-9]/', '', $value );
	}
}

return Wpcot_Frontend::instance();
