<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wpcot_Reports {
    function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'woocommerce_admin_reports', [ $this, 'tip_reports' ] );
        add_action( 'wp_ajax_wpcot_display_reports', [ $this, 'ajax_display_reports' ] );
        add_action( 'admin_init', [ $this, 'export_tips_csv' ] );
    }

    function enqueue_scripts() {
        wp_enqueue_style( 'wpcot-reports', WPCOT_URI . 'assets/css/reports.css', [], WPCOT_VERSION );
        wp_enqueue_script( 'wpcot-reports', WPCOT_URI . 'assets/js/reports.js', [
                'jquery',
                'jquery-ui-datepicker'
        ], WPCOT_VERSION, true );
    }

    function get_order_statuses() {
        $wc_statuses = wc_get_order_statuses();

        return array_keys( $wc_statuses );
    }

    function tip_reports( $reports ) {
        $reports['wpcot'] = [
                'title'   => esc_html__( 'Order Tip', 'wpc-order-tip' ),
                'reports' => [
                        'tip' => [
                                'title'       => esc_html__( 'Order Tip', 'wpc-order-tip' ),
                                'description' => '',
                                'hide_title'  => true,
                                'callback'    => [ $this, 'display_reports' ]
                        ]
                ]
        ];

        return $reports;
    }

    function display_reports() {
        $names    = apply_filters( 'wpcot_default_tip_names', [ esc_html__( 'Tip', 'wpc-order-tip' ) ] );
        $to       = gmdate( 'Y-m-d' );
        $from     = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $ids      = [];
        $status   = 'all';
        $statuses = $this->get_order_statuses();

        $query = new WC_Order_Query( apply_filters( 'wpcot_order_query_args', [
                'limit'       => - 1,
                'status'      => [ 'completed', 'processing', 'on-hold', 'cancelled' ],
                'post_status' => $statuses ?: [ 'wc-completed' ],
                'orderby'     => 'date',
                'order'       => 'DESC',
                'date_query'  => [
                        [
                                'after'     => [
                                        'year'  => gmdate( 'Y', strtotime( $from ) ),
                                        'month' => gmdate( 'm', strtotime( $from ) ),
                                        'day'   => gmdate( 'd', strtotime( $from ) )
                                ],
                                'before'    => [
                                        'year'  => gmdate( 'Y', strtotime( $to ) ),
                                        'month' => gmdate( 'm', strtotime( $to ) ),
                                        'day'   => gmdate( 'd', strtotime( $to ) )
                                ],
                                'inclusive' => true
                        ],
                ]
        ], $names, $from, $to, $status ) );

        if ( $orders = $query->get_orders() ) {
            foreach ( $orders as $order ) {
                $has_tip   = false;
                $fees      = $order->get_fees();
                $tip_total = 0;
                $tip_names = [];

                foreach ( $fees as $fee ) {
                    $tip_name = $fee->get_name();

                    if ( self::check_tip_name( $tip_name, $names ) ) {
                        $has_tip     = true;
                        $tip_names[] = $tip_name;
                        $tip_total   += floatval( $fee->get_total() );
                    }
                }

                if ( $has_tip ) {
                    $ids[ $order->get_id() ] = [
                            'date'     => $order->get_date_created(),
                            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            'value'    => $tip_total,
                            'name'     => implode( ', ', $tip_names )
                    ];
                }
            }
        }

        $data = [
                'ids'   => $ids,
                'names' => $names,
                'from'  => $from,
                'to'    => $to
        ];

        echo wp_kses( $this->view_reports( $data ), $this->get_allowed_html() );
    }

    function check_tip_name( $name = '', $names = [] ) {
        $names = array_map( 'trim', $names );

        if ( empty( $names ) ) {
            // match all fees
            return true;
        } else {
            foreach ( $names as $n ) {
                if ( str_contains( strtolower( $name ), strtolower( $n ) ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    function ajax_display_reports() {
        $names  = isset( $_REQUEST['names'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['names'] ?? '' ) ) ) : apply_filters( 'wpcot_default_tip_names', [ esc_html__( 'Tip', 'wpc-order-tip' ) ] );
        $from   = sanitize_text_field( wp_unslash( $_REQUEST['from'] ?? '' ) );
        $to     = sanitize_text_field( wp_unslash( $_REQUEST['to'] ?? '' ) );
        $status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? 'all' ) );
        $result = '';
        $errors = [];

        if ( ! empty( $from ) && ! empty( $to ) ) {
            $data = $this->get_filtered_data( $names, $from, $to, $status );

            if ( $data['ids'] && ! $data['errors'] ) {
                ob_start();

                echo wp_kses( $this->view_reports_table( $data ), $this->get_allowed_html() );

                $result = ob_get_clean();
            } else {
                $errors[] = esc_html__( 'There are no orders with tips based on your date range.', 'wpc-order-tip' );
            }
        } else {
            $errors[] = esc_html__( 'There are no orders with tips based on your date range.', 'wpc-order-tip' );
        }

        wp_send_json( [
                'status' => $errors ? 'error' : 'success',
                'result' => $result,
                'errors' => $errors
        ] );
    }

    function get_filtered_data( $names, $from, $to, $status ) {
        if ( ! $from || ! $to ) {
            return null;
        }

        $errors   = $ids = [];
        $names    = array_map( 'trim', (array) $names );
        $statuses = $status == 'all' ? $this->get_order_statuses() : [ $status ];

        $query = new WC_Order_Query( apply_filters( 'wpcot_order_query_args', [
                'limit'       => - 1,
                'status'      => [ 'completed', 'processing', 'on-hold', 'cancelled' ],
                'post_status' => $statuses ?: [ 'wc-completed' ],
                'orderby'     => 'date',
                'order'       => 'DESC',
                'date_query'  => [
                        [
                                'after'     => [
                                        'year'  => gmdate( 'Y', strtotime( $from ) ),
                                        'month' => gmdate( 'm', strtotime( $from ) ),
                                        'day'   => gmdate( 'd', strtotime( $from ) )
                                ],
                                'before'    => [
                                        'year'  => gmdate( 'Y', strtotime( $to ) ),
                                        'month' => gmdate( 'm', strtotime( $to ) ),
                                        'day'   => gmdate( 'd', strtotime( $to ) )
                                ],
                                'inclusive' => true
                        ],
                ]
        ], $names, $from, $to, $status ) );

        if ( $orders = $query->get_orders() ) {
            foreach ( $orders as $order ) {
                $has_tip   = false;
                $fees      = $order->get_fees();
                $tip_total = 0;
                $tip_names = [];

                foreach ( $fees as $fee ) {
                    $tip_name = $fee->get_name();

                    if ( self::check_tip_name( $tip_name, $names ) ) {
                        $has_tip     = true;
                        $tip_names[] = $tip_name;
                        $tip_total   += floatval( $fee->get_total() );
                    }
                }

                if ( $has_tip ) {
                    $ids[ $order->get_id() ] = [
                            'date'     => $order->get_date_created(),
                            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            'value'    => $tip_total,
                            'name'     => implode( ', ', $tip_names )
                    ];
                }
            }
        } else {
            $errors[] = esc_html__( 'There are no orders with tips based on your date range.', 'wpc-order-tip' );
        }

        return [
                'ids'    => $ids,
                'names'  => $names,
                'from'   => $from,
                'to'     => $to,
                'status' => $status,
                'errors' => $errors
        ];
    }

    function export_tips_csv() {
        if (
                isset( $_REQUEST['a'] ) && 'export' === sanitize_text_field( wp_unslash( $_REQUEST['a'] ) ) &&
                isset( $_REQUEST['from'] ) && sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) &&
                isset( $_REQUEST['to'] ) && sanitize_text_field( wp_unslash( $_REQUEST['to'] ) )
        ) {
            $names  = isset( $_REQUEST['names'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['names'] ?? '' ) ) ) : apply_filters( 'wpcot_default_tip_names', [ esc_html__( 'Tip', 'wpc-order-tip' ) ] );
            $from   = sanitize_text_field( wp_unslash( $_REQUEST['from'] ?? '' ) );
            $to     = sanitize_text_field( wp_unslash( $_REQUEST['to'] ?? '' ) );
            $status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
            $fp     = $this->get_tips_csv_header( $from, $to );
            $this->create_tips_csv_lines( $fp, $names, $from, $to, $status );
            fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            exit;
        }
    }

    function get_tips_csv_header( $from, $to ) {
        $filename = 'wpc-order-tip-' . sanitize_title( $from ) . '-' . sanitize_title( $to ) . '.csv';

        header( 'Content-Type: application/excel' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $fp      = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $columns = [
                esc_html__( 'Order ID', 'wpc-order-tip' ),
                esc_html__( 'Order Status', 'wpc-order-tip' ),
                esc_html__( 'Customer', 'wpc-order-tip' ),
                esc_html__( 'Name', 'wpc-order-tip' ),
                esc_html__( 'Value', 'wpc-order-tip' ),
                esc_html__( 'Date/Time', 'wpc-order-tip' )
        ];

        $csvheader = $columns;
        $csvheader = array_map( 'utf8_decode', $csvheader );

        fputcsv( $fp, $csvheader, ',' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv

        return $fp;
    }

    function create_tips_csv_lines( $fp, $names, $from, $to, $status ) {
        $statuses = $status == 'all' ? $this->get_order_statuses() : [ $status ];

        $query = new WC_Order_Query( apply_filters( 'wpcot_order_query_args', [
                'limit'       => - 1,
                'status'      => [ 'completed', 'processing', 'on-hold', 'cancelled' ],
                'post_status' => $statuses ?: [ 'wc-completed' ],
                'orderby'     => 'date',
                'order'       => 'DESC',
                'date_query'  => [
                        [
                                'after'     => [
                                        'year'  => gmdate( 'Y', strtotime( $from ) ),
                                        'month' => gmdate( 'm', strtotime( $from ) ),
                                        'day'   => gmdate( 'd', strtotime( $from ) )
                                ],
                                'before'    => [
                                        'year'  => gmdate( 'Y', strtotime( $to ) ),
                                        'month' => gmdate( 'm', strtotime( $to ) ),
                                        'day'   => gmdate( 'd', strtotime( $to ) )
                                ],
                                'inclusive' => true
                        ],
                ]
        ], $names, $from, $to, $status ) );

        if ( $orders = $query->get_orders() ) {
            $total = 0;

            foreach ( $orders as $order ) {
                $has_tip   = false;
                $fees      = $order->get_fees();
                $tip_total = 0;
                $tip_names = [];

                foreach ( $fees as $fee ) {
                    $tip_name = $fee->get_name();

                    if ( self::check_tip_name( $tip_name, $names ) ) {
                        $has_tip     = true;
                        $tip_names[] = $tip_name;
                        $tip_total   += floatval( $fee->get_total() );
                    }
                }

                if ( $has_tip ) {
                    $total += $tip_total;

                    fputcsv( $fp, [ // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
                            $order->get_id(),
                            wc_get_order_status_name( $status ),
                            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            implode( ', ', $tip_names ),
                            $tip_total,
                            gmdate( get_option( 'date_format' ), strtotime( $order->get_date_created() ) )
                    ] );
                }
            }

            fputcsv( $fp, [] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
            fputcsv( $fp, [ esc_html__( 'Total', 'wpc-order-tip' ), $total ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
            fputcsv( $fp, [ esc_html__( 'Currency', 'wpc-order-tip' ), get_woocommerce_currency() ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
        }
    }

    function view_reports( $data ) {
        ob_start();
        ?>
        <div id="wpcot-reports">
            <div id="wpcot-reports-date-range">
                <div class="wpcot-reports-col">
                    <label for="wpcot-reports-from">
                        <?php esc_html_e( 'From', 'wpc-order-tip' ); ?>
                    </label>
                    <input type="text" id="wpcot-reports-from" placeholder="Click to choose date"
                           value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"/>
                </div>
                <div class="wpcot-reports-col">
                    <label for="wpcot-reports-to">
                        <?php esc_html_e( 'To', 'wpc-order-tip' ); ?>
                    </label>
                    <input type="text" id="wpcot-reports-to" placeholder="Click to choose date"
                           value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"/>
                </div>
                <div class="wpcot-reports-col">
                    <label for="wpcot-reports-status">
                        <?php esc_html_e( 'Order Status', 'wpc-order-tip' ); ?>
                    </label> <select id="wpcot-reports-status">
                        <option value="all"><?php esc_html_e( 'All', 'wpc-order-tip' ); ?></option>
                        <?php
                        if ( $wc_statuses = wc_get_order_statuses() ) {
                            foreach ( $wc_statuses as $status => $label ) {
                                echo '<option value="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="wpcot-reports-col">
                    <label for="wpcot-reports-names">
                        <?php esc_html_e( 'Name(s)', 'wpc-order-tip' ); ?>
                    </label>
                    <input type="text" id="wpcot-reports-names" placeholder="Name(s) to check, split by a comma"
                           value="<?php echo esc_attr( implode( ', ', apply_filters( 'wpcot_default_tip_names', [ esc_html__( 'Tip', 'wpc-order-tip' ) ] ) ) ); ?>"/>
                </div>
                <div class="wpcot-reports-col">
                    <button id="wpcot-reports-filter"
                            class="button"><?php esc_html_e( 'Filter', 'wpc-order-tip' ); ?></button>
                </div>
            </div>
            <div id="wpcot-reports-error"></div>
            <div id="wpcot-reports-result">
                <?php echo wp_kses( $this->view_reports_table( $data ), $this->get_allowed_html() ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function view_reports_table( $data ) {
        ob_start();
        $names  = isset( $data['names'] ) ? ( is_array( $data['names'] ) ? implode( ',', array_map( 'trim', $data['names'] ) ) : $data['names'] ) : '';
        $from   = $data['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $to     = $data['to'] ?? gmdate( 'Y-m-d' );
        $status = $data['status'] ?? 'all';
        ?>
        <p id="displaying-from-to">
            <?php printf(
            /* translators: date */ esc_html__( 'Displaying orders between %1$s and %2$s', 'wpc-order-tip' ),
                    '<span id="displaying-from">' . esc_html( $from ) . '</span>',
                    '<span id="displaying-to">' . esc_html( $to ) . '</span>'
            ); ?>
            <a id="wpcot-export-csv"
               href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=wpcot&a=export&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) . '&names=' . rawurlencode( $names ) . '&status=' . rawurlencode( $status ) ) ); ?>"
               class="button"><?php esc_html_e( 'Export to CSV', 'wpc-order-tip' ); ?></a>
        </p>
        <table id="wpcot-reports-table" class="wp-list-table widefat fixed striped table-view-list pages">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Order ID', 'wpc-order-tip' ); ?></th>
                <th><?php esc_html_e( 'Order Status', 'wpc-order-tip' ); ?></th>
                <th><?php esc_html_e( 'Customer', 'wpc-order-tip' ); ?></th>
                <th><?php esc_html_e( 'Name', 'wpc-order-tip' ); ?></th>
                <th><?php esc_html_e( 'Value', 'wpc-order-tip' ); ?></th>
                <th><?php esc_html_e( 'Date/Time', 'wpc-order-tip' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $total = 0;

            foreach ( $data['ids'] as $oi => $od ) {
                $order  = wc_get_order( $oi );
                $status = $order->get_status();
                $total  += $od['value'];
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oi . '&action=edit' ) ); ?>"
                           target="_blank"><?php echo esc_html( $oi ); ?></a>
                    </td>
                    <td>
                        <?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
                    </td>
                    <td>
                        <?php echo esc_html( $od['customer'] ); ?>
                    </td>
                    <td>
                        <?php echo esc_html( ! empty( $od['name'] ) ? $od['name'] : '' ); ?>
                    </td>
                    <td>
                        <?php echo wp_kses_post( wc_price( $od['value'] ) ); ?>
                    </td>
                    <td>
                        <?php echo esc_html( gmdate( get_option( 'date_format' ), strtotime( $od['date'] ) ) ); ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
            <?php if ( $data['ids'] && $total ) { ?>
                <tfoot>
                <td colspan="6"><?php echo esc_html__( 'Total: ', 'wpc-order-tip' ) . wp_kses_post( wc_price( $total ) ); ?></td>
                </tfoot>
            <?php } ?>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Get allowed HTML tags and attributes for escaping report views.
     *
     * @return array Allowed HTML tags.
     */
    private function get_allowed_html() {
        return [
            'div'    => [
                'id'    => true,
                'class' => true,
            ],
            'label'  => [
                'for' => true,
            ],
            'input'  => [
                'type'        => true,
                'id'          => true,
                'placeholder' => true,
                'value'       => true,
                'class'       => true,
            ],
            'select' => [
                'id'    => true,
                'class' => true,
            ],
            'option' => [
                'value'    => true,
                'selected' => true,
            ],
            'button' => [
                'id'    => true,
                'class' => true,
            ],
            'p'      => [
                'id' => true,
            ],
            'span'   => [
                'id' => true,
            ],
            'a'      => [
                'id'     => true,
                'href'   => true,
                'class'  => true,
                'target' => true,
            ],
            'table'  => [
                'id'    => true,
                'class' => true,
            ],
            'thead'  => [],
            'tbody'  => [],
            'tfoot'  => [],
            'tr'     => [],
            'th'     => [],
            'td'     => [
                'colspan' => true,
            ],
        ];
    }
}

new Wpcot_Reports();
