<?php
defined( 'ABSPATH' ) || exit;

class Wpcot_Backend {
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Settings
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		// Links
		add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
		add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

		// AJAX
		add_action( 'wp_ajax_wpcot_add_tip', [ $this, 'ajax_add_tip' ] );
	}

	public function enqueue_scripts( $hook ) {
		if ( str_contains( $hook, 'wpcot' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'hint', WPCOT_URI . 'assets/css/hint.css' );
			wp_enqueue_style( 'wpcot-backend', WPCOT_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCOT_VERSION );
			wp_enqueue_script( 'wpcot-backend', WPCOT_URI . 'assets/js/backend.js', [
				'jquery',
				'wp-color-picker',
				'jquery-ui-sortable',
				'selectWoo',
			], WPCOT_VERSION, true );
			wp_localize_script( 'wpcot-backend', 'wpcot_vars', [
				'hint_value'  => htmlentities( esc_attr__( 'Set a value using a number (eg. "10") or percentage (eg. "15%" of order subtotal)', 'wpc-order-tip' ) ),
				'hint_remove' => esc_attr__( 'remove', 'wpc-order-tip' ),
			] );
		}
	}

	function register_settings() {
		// settings
		register_setting( 'wpcot_settings', 'wpcot_tips' );
		register_setting( 'wpcot_settings', 'wpcot_settings' );

		// localization
		register_setting( 'wpcot_localization', 'wpcot_localization' );
	}

	public function admin_menu() {
		add_submenu_page( 'wpclever', 'WPC Order Tip', 'Order Tip', 'manage_options', 'wpclever-wpcot', [
			$this,
			'admin_menu_content'
		] );
	}

	public function admin_menu_content() {
		$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
		?>
        <div class="wpclever_settings_page wrap">
            <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Order Tip', 'wpc-order-tip' ) . ' ' . esc_html( WPCOT_VERSION ) . ' ' . ( defined( 'WPCOT_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-order-tip' ) . '</span>' : '' ); ?></h1>
            <div class="wpclever_settings_page_desc about-text">
                <p>
					<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-order-tip' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                    <br/>
                    <a href="<?php echo esc_url( WPCOT_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-order-tip' ); ?></a> |
                    <a href="<?php echo esc_url( WPCOT_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-order-tip' ); ?></a> |
                    <a href="<?php echo esc_url( WPCOT_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-order-tip' ); ?></a>
                </p>
            </div>
			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings updated.', 'wpc-order-tip' ); ?></p>
                </div>
			<?php } ?>
            <div class="wpclever_settings_page_nav">
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcot&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
						<?php esc_html_e( 'Settings', 'wpc-order-tip' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcot&tab=localization' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
						<?php esc_html_e( 'Localization', 'wpc-order-tip' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=wpcot' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'reports' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
						<?php esc_html_e( 'Tip Reports', 'wpc-order-tip' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcot&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
						<?php esc_html_e( 'Premium Version', 'wpc-order-tip' ); ?>
                    </a> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
						<?php esc_html_e( 'Essential Kit', 'wpc-order-tip' ); ?>
                    </a>
                </h2>
            </div>
            <div class="wpclever_settings_page_content">
				<?php if ( $active_tab === 'settings' ) {
					$click_again       = Wpcot_Helper()->get_setting( 'click_again', 'yes' );
					$position_cart     = Wpcot_Helper()->get_setting( 'position_cart', 'before_totals' );
					$position_checkout = Wpcot_Helper()->get_setting( 'position_checkout', 'before_order_review' );
					$no_btn_position   = Wpcot_Helper()->get_setting( 'no_btn_position', 'first' );
					$btn_style         = Wpcot_Helper()->get_setting( 'btn_style', 'square' );
					?>
                    <form method="post" action="options.php">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'Position on cart page', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label> <select name="wpcot_settings[position_cart]">
                                            <option value="before_cart" <?php selected( $position_cart, 'before_cart' ); ?>><?php esc_html_e( 'Before cart', 'wpc-order-tip' ); ?></option>
                                            <option value="after_coupon" <?php selected( $position_cart, 'after_coupon' ); ?>><?php esc_html_e( 'After coupon', 'wpc-order-tip' ); ?></option>
                                            <option value="before_totals" <?php selected( $position_cart, 'before_totals' ); ?>><?php esc_html_e( 'Before totals', 'wpc-order-tip' ); ?></option>
                                            <option value="after_cart" <?php selected( $position_cart, 'after_cart' ); ?>><?php esc_html_e( 'After cart', 'wpc-order-tip' ); ?></option>
                                            <option value="none" <?php selected( $position_cart, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-order-tip' ); ?></option>
                                        </select> </label>
                                    <span class="description"><?php esc_html_e( 'You also can use shortcode [wpcot] to place it where you want.', 'wpc-order-tip' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Position on checkout page', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label> <select name="wpcot_settings[position_checkout]">
                                            <option value="before_checkout_form" <?php selected( $position_checkout, 'before_checkout_form' ); ?>><?php esc_html_e( 'Before checkout form', 'wpc-order-tip' ); ?></option>
                                            <option value="before_order_notes" <?php selected( $position_checkout, 'before_order_notes' ); ?>><?php esc_html_e( 'Before order details', 'wpc-order-tip' ); ?></option>
                                            <option value="after_customer_details" <?php selected( $position_checkout, 'after_customer_details' ); ?>><?php esc_html_e( 'After customer details', 'wpc-order-tip' ); ?></option>
                                            <option value="before_order_review" <?php selected( $position_checkout, 'before_order_review' ); ?>><?php esc_html_e( 'Before order review', 'wpc-order-tip' ); ?></option>
                                            <option value="after_checkout_form" <?php selected( $position_checkout, 'after_checkout_form' ); ?>><?php esc_html_e( 'After checkout form', 'wpc-order-tip' ); ?></option>
                                            <option value="none" <?php selected( $position_checkout, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-order-tip' ); ?></option>
                                        </select> </label>
                                    <span class="description"><?php esc_html_e( 'You also can use shortcode [wpcot] to place it where you want.', 'wpc-order-tip' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '"No, Thanks!" button position', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label> <select name="wpcot_settings[no_btn_position]">
                                            <option value="first" <?php selected( $no_btn_position, 'first' ); ?>><?php esc_html_e( 'First', 'wpc-order-tip' ); ?></option>
                                            <option value="last" <?php selected( $no_btn_position, 'last' ); ?>><?php esc_html_e( 'Last', 'wpc-order-tip' ); ?></option>
                                            <option value="before_other" <?php selected( $no_btn_position, 'before_other' ); ?>><?php esc_html_e( 'Before "Other" button', 'wpc-order-tip' ); ?></option>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Remove when clicking again', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label> <select name="wpcot_settings[click_again]">
                                            <option value="yes" <?php selected( $click_again, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-order-tip' ); ?></option>
                                            <option value="no" <?php selected( $click_again, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-order-tip' ); ?></option>
                                        </select> </label>
                                    <span class="description"><?php esc_html_e( 'Do you want to remove when clicking on an active tip?', 'wpc-order-tip' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Button style', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label> <select name="wpcot_settings[btn_style]">
                                            <option value="square" <?php selected( $btn_style, 'square' ); ?>><?php esc_html_e( 'Square', 'wpc-order-tip' ); ?></option>
                                            <option value="rounded" <?php selected( $btn_style, 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'wpc-order-tip' ); ?></option>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Active button color', 'wpc-order-tip' ); ?></th>
                                <td>
									<?php $active_color_default = apply_filters( 'wpcot_active_color_default', '#cc99c2' ); ?>
                                    <label>
                                        <input type="text" name="wpcot_settings[active_color]" class="wpcot_color_picker" value="<?php echo esc_attr( Wpcot_Helper()->get_setting( 'active_color', $active_color_default ) ); ?>"/>
                                    </label>
                                    <span class="description"><?php printf( /* translators: color */ esc_html__( 'Choose the color for the active button, default %s', 'wpc-order-tip' ), '<code>' . $active_color_default . '</code>' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Tips', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <div class="wpcot-tips-wrapper">
                                        <div class="wpcot-tips wpcot-tips">
											<?php
											$tips = Wpcot_Helper()->get_tips();

											if ( empty( $tips ) ) {
												$key    = Wpcot_Helper()->generate_key();
												$active = true;
												$tip    = [];
												include WPCOT_DIR . 'includes/templates/tip.php';
											} else {
												$i = 0;

												foreach ( $tips as $key => $tip ) {
													if ( $i === 0 ) {
														$active = true;
													} else {
														$active = false;
													}

													include WPCOT_DIR . 'includes/templates/tip.php';
													$i ++;
												}
											}
											?>
                                        </div>
                                    </div>
                                    <div class="wpcot-tips-new">
                                        <input type="button" class="button wpcot-add-tip" value="<?php esc_attr_e( '+ Add tip', 'wpc-order-tip' ); ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr class="submit">
                                <th colspan="2">
									<?php settings_fields( 'wpcot_settings' ); ?><?php submit_button(); ?>
                                </th>
                            </tr>
                        </table>
                    </form>
				<?php } elseif ( $active_tab === 'localization' ) { ?>
                    <form method="post" action="options.php">
                        <table class="form-table">
                            <tr class="heading">
                                <th scope="row"><?php esc_html_e( 'Localization', 'wpc-order-tip' ); ?></th>
                                <td>
									<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-order-tip' ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'No, Thanks!', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label>
                                        <input type="text" class="regular-text" name="wpcot_localization[no]" value="<?php echo esc_attr( Wpcot_Helper()->localization( 'no' ) ); ?>" placeholder="<?php esc_attr_e( 'No, Thanks!', 'wpc-order-tip' ); ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Other', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label>
                                        <input type="text" class="regular-text" name="wpcot_localization[other]" value="<?php echo esc_attr( Wpcot_Helper()->localization( 'other' ) ); ?>" placeholder="<?php esc_attr_e( 'Other', 'wpc-order-tip' ); ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Add', 'wpc-order-tip' ); ?></th>
                                <td>
                                    <label>
                                        <input type="text" class="regular-text" name="wpcot_localization[add]" value="<?php echo esc_attr( Wpcot_Helper()->localization( 'add' ) ); ?>" placeholder="<?php esc_attr_e( 'Add', 'wpc-order-tip' ); ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="submit">
                                <th colspan="2">
									<?php settings_fields( 'wpcot_localization' ); ?><?php submit_button(); ?>
                                </th>
                            </tr>
                        </table>
                    </form>
				<?php } elseif ( $active_tab == 'premium' ) { ?>
                    <div class="wpclever_settings_page_content_text">
                        <p>Get the Premium Version just $29!
                            <a href="https://wpclever.net/downloads/wpc-order-tip/?utm_source=pro&utm_medium=wpcot&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-order-tip/</a>
                        </p>
                        <p><strong>Extra features for Premium Version:</strong></p>
                        <ul style="margin-bottom: 0">
                            <li>- Define whether the tip is taxable.</li>
                            <li>- Set user roles for each tip.</li>
                            <li>- Get the lifetime update & premium support.</li>
                        </ul>
                    </div>
				<?php } ?>
            </div><!-- /.wpclever_settings_page_content -->
            <div class="wpclever_settings_page_suggestion">
                <div class="wpclever_settings_page_suggestion_label">
                    <span class="dashicons dashicons-yes-alt"></span> Suggestion
                </div>
                <div class="wpclever_settings_page_suggestion_content">
                    <div>
                        To display custom engaging real-time messages on any wished positions, please install
                        <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                    </div>
                    <div>
                        Wanna save your precious time working on variations? Try our brand-new free plugin
                        <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                        <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                    </div>
                </div>
            </div>
        </div>
		<?php
	}

	function action_links( $links, $file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( WPCOT_FILE );
		}

		if ( $plugin === $file ) {
			$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcot&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-order-tip' ) . '</a>';
			$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcot&tab=premium' ) ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'wpc-order-tip' ) . '</a>';
			array_unshift( $links, $settings );
		}

		return (array) $links;
	}

	function row_meta( $links, $file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( WPCOT_FILE );
		}

		if ( $plugin === $file ) {
			$row_meta = [
				'support' => '<a href="' . esc_url( WPCOT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-order-tip' ) . '</a>',
			];

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	public function ajax_add_tip() {
		$key    = Wpcot_Helper()->generate_key();
		$active = true;
		$tip    = [];
		include WPCOT_DIR . 'includes/templates/tip.php';

		wp_die();
	}
}

return Wpcot_Backend::instance();
