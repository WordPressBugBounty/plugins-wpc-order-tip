<?php
/**
 * @var $key
 * @var $tip
 * @var $active
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $key ) ) {
	$key = Wpcot_Helper()->generate_key();
}

$name         = ! empty( $tip['name'] ) ? esc_html( $tip['name'] ) : esc_html__( 'Default', 'wpc-order-tip' );
$desc         = ! empty( $tip['desc'] ) ? esc_html( $tip['desc'] ) : '';
$default      = ! empty( $tip['default'] ) ? esc_attr( $tip['default'] ) : '';
$taxable      = ! empty( $tip['taxable'] ) ? esc_attr( $tip['taxable'] ) : 'no';
$tax_class    = ! empty( $tip['tax_class'] ) ? esc_attr( $tip['tax_class'] ) : '';
$custom       = ! empty( $tip['custom'] ) ? esc_attr( $tip['custom'] ) : 'no';
$custom_min   = ! empty( $tip['custom_min'] ) ? (float) $tip['custom_min'] : '';
$custom_max   = ! empty( $tip['custom_max'] ) ? (float) $tip['custom_max'] : '';
$custom_step  = ! empty( $tip['custom_step'] ) ? (float) $tip['custom_step'] : '';
$custom_value = ! empty( $tip['custom_value'] ) ? (float) $tip['custom_value'] : '';
$roles        = ! empty( $tip['roles'] ) && is_array( $tip['roles'] ) ? $tip['roles'] : [ 'wpcot_all' ];
?>
<div class="<?php echo esc_attr( $active ? 'wpcot-tip active' : 'wpcot-tip' ); ?>">
    <div class="wpcot-tip-header">
        <span class="wpcot-tip-move ui-sortable-handle"><?php esc_html_e( 'move', 'wpc-order-tip' ); ?></span>
        <span class="wpcot-tip-label"><?php echo esc_html( $name ); ?></span>
        <span class="wpcot-remove-tip"><?php esc_html_e( 'remove', 'wpc-order-tip' ); ?></span>
    </div>
    <div class="wpcot-tip-content">
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'Name *', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label>
                    <input type="text" value="<?php echo esc_attr( $name ); ?>" class="wpcot-tip-name input-block" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][name]' ); ?>" required/>
                </label>
            </div>
        </div>
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'Description', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label>
                    <textarea class="input-block" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][desc]' ); ?>"><?php echo esc_html( $desc ); ?></textarea>
                </label>
            </div>
        </div>
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'Values', 'wpc-order-tip' ); ?></label>
            <div class="wpcot-values-wrap">
                <div class="wpcot-values">
					<?php
					$count = 0;

					if ( ! empty( $tip['values'] ) && is_array( $tip['values'] ) ) {
						foreach ( $tip['values'] as $value ) { ?>
                            <div class="wpcot-value">
			                    <span class="wpcot-label-wrapper">
			                        <label>
<input type="text" value="<?php echo esc_attr( $value['label'] ); ?>" placeholder="<?php esc_attr_e( 'label', 'wpc-order-tip' ); ?>" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][values][' . $count . '][label]' ); ?>"/>
</label>
			                    </span>
                                <span class="wpcot-value-wrapper hint--top" aria-label="<?php esc_attr_e( 'Set a value using a number (eg. "10") or percentage (eg. "15%" of order subtotal)', 'wpc-order-tip' ); ?>">
			                        <label>
<input type="text" value="<?php echo esc_attr( $value['value'] ); ?>" placeholder="<?php esc_attr_e( 'value', 'wpc-order-tip' ); ?>" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][values][' . $count . '][value]' ); ?>"/>
</label>
			                    </span>
                                <span class="wpcot-remove-value hint--top" aria-label="<?php esc_attr_e( 'remove', 'wpc-order-tip' ); ?>">&times;</span>
                            </div>
							<?php
							$count ++;
						}
					} ?>
                </div>
                <div class="wpcot-new-value">
                    <button class="button wpcot-add-value" type="button" data-key="<?php echo esc_attr( $key ); ?>" data-count="<?php echo esc_attr( $count ); ?>">
						<?php esc_html_e( '+ New value', 'wpc-order-tip' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'Default value', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label>
                    <input type="text" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][default]' ); ?>" value="<?php echo esc_attr( $default ); ?>"/>
                </label>
                <p class="description"><?php esc_html_e( 'Default value will be selected automatically. It should match one of above values.', 'wpc-order-tip' ); ?></p>
            </div>
        </div>
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'Custom amount', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label>
                    <select name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][custom]' ); ?>" class="wpcot-custom">
                        <option value="no" <?php selected( $custom, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-order-tip' ); ?></option>
                        <option value="yes" <?php selected( $custom, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-order-tip' ); ?></option>
                    </select> </label>
                <div class="wpcot-show-if-custom">
                    <span class="hint--top" aria-label="<?php esc_attr_e( 'min', 'wpc-order-tip' ); ?>">
                        <label>
<input type="number" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][custom_min]' ); ?>" value="<?php echo esc_attr( $custom_min ); ?>" placeholder="<?php esc_attr_e( 'min', 'wpc-order-tip' ); ?>" style="width: 80px"/>
</label>
                    </span> <span class="hint--top" aria-label="<?php esc_attr_e( 'step', 'wpc-order-tip' ); ?>">
                        <label>
<input type="number" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][custom_step]' ); ?>" value="<?php echo esc_attr( $custom_step ); ?>" placeholder="<?php esc_attr_e( 'step', 'wpc-order-tip' ); ?>" style="width: 80px"/>
</label>
                    </span> <span class="hint--top" aria-label="<?php esc_attr_e( 'max', 'wpc-order-tip' ); ?>">
                        <label>
<input type="number" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][custom_max]' ); ?>" value="<?php echo esc_attr( $custom_max ); ?>" placeholder="<?php esc_attr_e( 'max', 'wpc-order-tip' ); ?>" style="width: 80px"/>
</label>
                    </span>
                    <span class="hint--top" aria-label="<?php esc_attr_e( 'default value', 'wpc-order-tip' ); ?>">
                        <label>
<input type="number" name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][custom_value]' ); ?>" value="<?php echo esc_attr( $custom_value ); ?>" placeholder="<?php esc_attr_e( 'default value', 'wpc-order-tip' ); ?>" style="width: 80px"/>
</label>
                    </span>
                </div>
            </div>
        </div>
        <div class="<?php echo esc_attr( wc_tax_enabled() ? 'wpcot-tip-line' : 'wpcot-tip-line wpcot-tip-line-disabled' ); ?>">
            <label><?php esc_html_e( 'Is taxable', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label> <select name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][taxable]' ); ?>">
                        <option value="no" <?php selected( $taxable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-order-tip' ); ?></option>
                        <option value="yes" <?php selected( $taxable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-order-tip' ); ?></option>
                    </select> </label>
                <p class="description"><?php esc_html_e( 'Define whether the tip is taxable.', 'wpc-order-tip' ); ?>
                    <span style="color: #c9356e">This feature is only available on the Premium Version.</span></p>
            </div>
        </div>
        <div class="<?php echo esc_attr( wc_tax_enabled() ? 'wpcot-tip-line' : 'wpcot-tip-line wpcot-tip-line-disabled' ); ?>">
            <label><?php esc_html_e( 'Tax class', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <label> <select name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][tax_class]' ); ?>">
						<?php
						$tax_options = [
							'' => esc_html__( 'Standard', 'wpc-order-tip' ),
						];

						$tax_classes = WC_Tax::get_tax_classes();

						if ( ! empty( $tax_classes ) ) {
							foreach ( $tax_classes as $class ) {
								$tax_options[ sanitize_title( $class ) ] = esc_html( $class );
							}
						}

						foreach ( $tax_options as $tax_key => $tax_name ) {
							echo '<option value="' . esc_attr( $tax_key ) . '" ' . selected( $tax_class, $tax_key, false ) . '>' . esc_html( $tax_name ) . '</option>';
						}
						?>
                    </select> </label>
            </div>
        </div>
        <div class="wpcot-tip-line">
            <label><?php esc_html_e( 'User roles', 'wpc-order-tip' ); ?></label>
            <div class="input-panel">
                <p class="description"><?php esc_html_e( 'Select which user roles that can see this tip group.', 'wpc-order-tip' ); ?>
                    <span style="color: #c9356e">This feature is only available on the Premium Version.</span></p>
                <label>
                    <select name="<?php echo esc_attr( 'wpcot_tips[' . $key . '][roles][]' ); ?>" multiple class="wpcot_roles_select">
						<?php
						global $wp_roles;

						echo '<option value="wpcot_all" ' . ( in_array( 'wpcot_all', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-order-tip' ) . '</option>';
						echo '<option value="wpcot_user" ' . ( in_array( 'wpcot_user', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-order-tip' ) . '</option>';
						echo '<option value="wpcot_guest" ' . ( in_array( 'wpcot_guest', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-order-tip' ) . '</option>';

						foreach ( $wp_roles->roles as $role => $details ) {
							echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
						}
						?>
                    </select> </label>
            </div>
        </div>
    </div>
</div>
