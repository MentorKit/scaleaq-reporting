<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_Course_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        $cat     = sanitize_text_field( $_GET['cat'] ?? 'hse' );
        $from    = self::sanitize_date( $_GET['from'] ?? '' );
        $to      = self::sanitize_date( $_GET['to'] ?? '' );
        $company = sanitize_text_field( $_GET['company'] ?? '' );
        $export  = sanitize_text_field( $_GET['export'] ?? '' );

        $course_map      = self::get_course_ids_map();
        $category_labels = self::get_category_labels();

        if ( ! isset( $course_map[ $cat ] ) ) {
            $cat = 'hse';
        }

        $course_ids = $course_map[ $cat ];
        $ts_col     = self::detect_timestamp_column();

        // Build company dropdown options.
        $company_sql = self::get_base_user_query();
        $all_users   = $wpdb->get_results( $company_sql );

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

        // Build completion lookup.
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

        // Tally stats.
        $total         = count( $users );
        $completed     = 0;
        $by_company    = array();
        $by_group      = array(
            'Moen Marin AS' => array( 'total' => 0, 'completed' => 0 ),
            'ScaleAQ Group' => array( 'total' => 0, 'completed' => 0 ),
            'Other'         => array( 'total' => 0, 'completed' => 0 ),
        );

        foreach ( $users as $u ) {
            $done        = isset( $completed_set[ $u->ID ] );
            $comp_name   = trim( $u->company ?? 'Unknown' );
            $group_label = self::get_group_label( $comp_name );

            if ( $done ) {
                $completed++;
            }

            if ( ! isset( $by_company[ $comp_name ] ) ) {
                $by_company[ $comp_name ] = array( 'total' => 0, 'completed' => 0 );
            }
            $by_company[ $comp_name ]['total']++;
            if ( $done ) {
                $by_company[ $comp_name ]['completed']++;
            }

            $by_group[ $group_label ]['total']++;
            if ( $done ) {
                $by_group[ $group_label ]['completed']++;
            }
        }

        $not_completed = $total - $completed;

        // CSV export.
        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $cat, $category_labels );
            return '';
        }

        // Render output.
        ob_start();
        ?>
        <div class="scaleaq-report scaleaq-course-report">
            <h2>Course Completion Report: <?php echo esc_html( $category_labels[ $cat ] ); ?></h2>

            <form method="get" style="margin-bottom: 20px;">
                <label for="cat">Category:</label>
                <select name="cat" id="cat">
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

            <p><strong>Total users:</strong> <?php echo esc_html( $total ); ?> |
               <strong>Completed:</strong> <?php echo esc_html( $completed ); ?> |
               <strong>Not completed:</strong> <?php echo esc_html( $not_completed ); ?></p>

            <div id="scaleaq-pie-completion" style="width: 400px; height: 300px; display: inline-block;"></div>
            <div id="scaleaq-pie-company" style="width: 500px; height: 300px; display: inline-block;"></div>

            <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
            <script type="text/javascript">
                google.charts.load('current', {'packages':['corechart']});
                google.charts.setOnLoadCallback(drawCharts);
                function drawCharts() {
                    // Completion pie chart.
                    var compData = google.visualization.arrayToDataTable([
                        ['Status', 'Count'],
                        ['Completed', <?php echo esc_js( $completed ); ?>],
                        ['Not Completed', <?php echo esc_js( $not_completed ); ?>]
                    ]);
                    var compChart = new google.visualization.PieChart(document.getElementById('scaleaq-pie-completion'));
                    compChart.draw(compData, {title: 'Completion Status', colors: ['#4CAF50', '#F44336']});

                    // Company pie chart.
                    var coData = google.visualization.arrayToDataTable([
                        ['Company', 'Completed'],
                        <?php foreach ( $by_company as $cname => $stats ) : ?>
                        [<?php echo wp_json_encode( $cname ); ?>, <?php echo (int) $stats['completed']; ?>],
                        <?php endforeach; ?>
                    ]);
                    var coChart = new google.visualization.PieChart(document.getElementById('scaleaq-pie-company'));
                    coChart.draw(coData, {title: 'Completions by Company'});
                }
            </script>

            <h3>Group Completion Rates</h3>
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Total</th>
                        <th>Completed</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $by_group as $gname => $gstats ) : ?>
                    <tr>
                        <td><?php echo esc_html( $gname ); ?></td>
                        <td><?php echo esc_html( $gstats['total'] ); ?></td>
                        <td><?php echo esc_html( $gstats['completed'] ); ?></td>
                        <td>
                            <?php
                            $rate = $gstats['total'] > 0
                                ? round( ( $gstats['completed'] / $gstats['total'] ) * 100, 1 )
                                : 0;
                            echo esc_html( $rate . '%' );
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $export_url = add_query_arg( array(
                'cat'     => $cat,
                'from'    => $from,
                'to'      => $to,
                'company' => $company,
                'export'  => '1',
            ) );
            ?>
            <p><a href="<?php echo esc_url( $export_url ); ?>">Download CSV</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function export_csv( $users, $completed_set, $cat, $category_labels ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="course-report-' . esc_attr( $cat ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Email', 'First Name', 'Last Name', 'Company', 'Category', 'Completed' ) );

        foreach ( $users as $u ) {
            fputcsv( $output, array(
                $u->ID,
                $u->user_email,
                $u->first_name,
                $u->last_name,
                $u->company ?? '',
                $category_labels[ $cat ] ?? $cat,
                isset( $completed_set[ $u->ID ] ) ? 'Yes' : 'No',
            ) );
        }

        fclose( $output );
        exit;
    }
}
