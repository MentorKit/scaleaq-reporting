<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_User_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        $cat     = sanitize_text_field( $_GET['cat'] ?? '' );
        $from    = self::sanitize_date( $_GET['from'] ?? '' );
        $to      = self::sanitize_date( $_GET['to'] ?? '' );
        $company = sanitize_text_field( $_GET['company'] ?? '' );
        $export  = sanitize_text_field( $_GET['export'] ?? '' );

        $course_map      = self::get_course_ids_map();
        $category_labels = self::get_category_labels();
        $ts_col          = self::detect_timestamp_column();

        // Build company dropdown options.
        $company_sql  = self::get_base_user_query();
        $all_users    = $wpdb->get_results( $company_sql );

        $companies = array();
        foreach ( $all_users as $u ) {
            $c = trim( $u->company ?? '' );
            if ( $c !== '' && ! in_array( $c, $companies, true ) ) {
                $companies[] = $c;
            }
        }
        sort( $companies );

        // Filter users by company if selected.
        $extra_where = '';
        if ( $company !== '' ) {
            $extra_where = $wpdb->prepare( "AND ms.meta_value = %s", $company );
        }

        $users = $wpdb->get_results( self::get_base_user_query( $extra_where ) );

        // Check completion if a category is selected.
        $completed_set = array();
        if ( $cat !== '' && isset( $course_map[ $cat ] ) ) {
            $course_ids   = $course_map[ $cat ];
            $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

            $activity_sql = "SELECT DISTINCT user_id
                FROM {$wpdb->prefix}learndash_user_activity
                WHERE activity_type = 'course'
                    AND activity_status = 1
                    AND post_id IN ({$placeholders})";

            $prepare_args = $course_ids;

            if ( $from !== '' ) {
                $from_ts      = strtotime( $from . ' 00:00:00' );
                $activity_sql .= $wpdb->prepare( " AND `{$ts_col}` >= %d", $from_ts );
            }

            if ( $to !== '' ) {
                $to_ts        = strtotime( $to . ' 23:59:59' );
                $activity_sql .= $wpdb->prepare( " AND `{$ts_col}` <= %d", $to_ts );
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $completed_users = $wpdb->get_col( $wpdb->prepare( $activity_sql, $prepare_args ) );
            $completed_set   = array_flip( $completed_users );
        }

        // CSV export.
        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $cat, $category_labels );
            return '';
        }

        // Render output.
        ob_start();
        ?>
        <div class="scaleaq-report scaleaq-user-report">
            <h2>User Report</h2>

            <form method="get" style="margin-bottom: 20px;">
                <label for="cat">Category:</label>
                <select name="cat" id="cat">
                    <option value="">All Courses</option>
                    <?php foreach ( $category_labels as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cat, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="company">Company:</label>
                <select name="company" id="company">
                    <option value="">All Companies</option>
                    <?php foreach ( $companies as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $company, $c ); ?>>
                            <?php echo esc_html( $c ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="from">From:</label>
                <input type="date" name="from" id="from" value="<?php echo esc_attr( $from ); ?>" />

                <label for="to">To:</label>
                <input type="date" name="to" id="to" value="<?php echo esc_attr( $to ); ?>" />

                <button type="submit">Filter</button>
            </form>

            <p>
                <strong>Category:</strong> <?php echo esc_html( $cat !== '' && isset( $category_labels[ $cat ] ) ? $category_labels[ $cat ] : 'All Courses' ); ?> |
                <strong>Company:</strong> <?php echo esc_html( $company !== '' ? $company : 'All' ); ?> |
                <strong>Total users:</strong> <?php echo esc_html( count( $users ) ); ?>
            </p>

            <?php
            $csv_url = add_query_arg( array(
                'cat'     => $cat,
                'from'    => $from,
                'to'      => $to,
                'company' => $company,
                'export'  => '1',
            ) );
            ?>
            <p><a href="<?php echo esc_url( $csv_url ); ?>">Download CSV</a></p>

            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Company</th>
                        <?php if ( $cat !== '' ) : ?>
                            <th>Has Completed</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $u ) : ?>
                    <tr>
                        <td><?php echo esc_html( $u->ID ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><?php echo esc_html( $u->first_name ); ?></td>
                        <td><?php echo esc_html( $u->last_name ); ?></td>
                        <td><?php echo esc_html( $u->company ?? '' ); ?></td>
                        <?php if ( $cat !== '' ) : ?>
                            <td><?php echo isset( $completed_set[ $u->ID ] ) ? 'Yes' : 'No'; ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function export_csv( $users, $completed_set, $cat, $category_labels ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="user-report.csv"' );

        $output = fopen( 'php://output', 'w' );

        $headers = array( 'ID', 'Email', 'First Name', 'Last Name', 'Company' );
        if ( $cat !== '' ) {
            $headers[] = 'Has Completed';
        }
        fputcsv( $output, $headers );

        foreach ( $users as $u ) {
            $row = array(
                $u->ID,
                $u->user_email,
                $u->first_name,
                $u->last_name,
                $u->company ?? '',
            );
            if ( $cat !== '' ) {
                $row[] = isset( $completed_set[ $u->ID ] ) ? 'Yes' : 'No';
            }
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
