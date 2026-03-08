<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_Course_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        wp_enqueue_style( 'scaleaq-reports' );

        $cat     = sanitize_text_field( $_GET['cr_cat'] ?? 'hse' );
        $from    = self::sanitize_date( $_GET['cr_from'] ?? self::get_default_from() );
        $to      = self::sanitize_date( $_GET['cr_to'] ?? self::get_default_to() );
        $company = sanitize_text_field( $_GET['cr_company'] ?? '' );
        $export  = sanitize_text_field( $_GET['cr_export'] ?? '' );

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
        if ( $company !== '' ) {
            $extra_where = $wpdb->prepare( "AND ms.meta_value = %s", $company );
            $users       = $wpdb->get_results( self::get_base_user_query( $extra_where ) );
        } else {
            $users = $all_users;
        }

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

        $not_completed  = $total - $completed;
        $completion_pct = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

        // CSV export.
        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $cat, $category_labels );
            return '';
        }

        // Sort companies by completed count descending for the bar chart.
        arsort( $by_company );
        $max_company_completed = max( array_column( $by_company, 'completed' ) ?: array( 1 ) );

        // Render output.
        ob_start();
        ?>
        <div class="scaleaq-report scaleaq-course-report">

            <!-- Header -->
            <div class="saq-header">
                <div class="saq-header__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5"/></svg>
                </div>
                <div>
                    <h2 class="saq-header__title">Course Completion: <?php echo esc_html( $category_labels[ $cat ] ); ?></h2>
                    <p class="saq-header__subtitle">Snapshot <?php echo ( $from !== '' && $to !== '' ) ? esc_html( $from ) . ' &mdash; ' . esc_html( $to ) : 'All time'; ?></p>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="saq-card" style="animation-delay: 0s;">
                <div class="saq-filters">
                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Category</span>
                        <select name="cr_cat" id="cr_cat">
                            <?php foreach ( $category_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cat, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Company</span>
                        <select name="cr_company" id="cr_company">
                            <option value="">All Companies</option>
                            <?php foreach ( $companies as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $company, $c ); ?>>
                                    <?php echo esc_html( $c ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group">
                        <span class="saq-filters__label">From</span>
                        <input type="date" name="cr_from" id="cr_from" value="<?php echo esc_attr( $from ); ?>" />
                    </div>

                    <div class="saq-filters__group">
                        <span class="saq-filters__label">To</span>
                        <input type="date" name="cr_to" id="cr_to" value="<?php echo esc_attr( $to ); ?>" />
                    </div>

                    <button type="submit" class="saq-filters__submit">Filter</button>
                </div>
            </form>

            <!-- Stat Cards -->
            <div class="saq-stats">
                <div class="saq-stat saq-stat--total">
                    <div class="saq-stat__value"><?php echo esc_html( $total ); ?></div>
                    <div class="saq-stat__label">Total Users</div>
                </div>
                <div class="saq-stat saq-stat--completed">
                    <div class="saq-stat__value"><?php echo esc_html( $completed ); ?></div>
                    <div class="saq-stat__label">Completed</div>
                </div>
                <div class="saq-stat saq-stat--pending">
                    <div class="saq-stat__value"><?php echo esc_html( $not_completed ); ?></div>
                    <div class="saq-stat__label">Not Completed</div>
                </div>
                <div class="saq-stat saq-stat--rate">
                    <div class="saq-stat__value"><?php echo esc_html( $completion_pct ); ?>%</div>
                    <div class="saq-stat__label">Completion Rate</div>
                </div>
            </div>

            <!-- Charts: Donut + Company Bars -->
            <div class="saq-charts">
                <div class="saq-card saq-donut-wrap" style="margin-bottom: 0;">
                    <p class="saq-card__label">Completion</p>
                    <div class="saq-donut" style="--saq-pct: <?php echo esc_attr( $completion_pct ); ?>;">
                        <div class="saq-donut__ring"></div>
                        <div class="saq-donut__hole">
                            <span class="saq-donut__pct"><?php echo esc_html( $completion_pct ); ?><span class="saq-donut__pct-sign">%</span></span>
                            <span class="saq-donut__caption">completed</span>
                        </div>
                    </div>
                    <div class="saq-donut-legend">
                        <span class="saq-donut-legend__item">
                            <span class="saq-donut-legend__dot saq-donut-legend__dot--completed"></span>
                            <?php echo esc_html( $completed ); ?> done
                        </span>
                        <span class="saq-donut-legend__item">
                            <span class="saq-donut-legend__dot saq-donut-legend__dot--pending"></span>
                            <?php echo esc_html( $not_completed ); ?> remaining
                        </span>
                    </div>
                </div>

                <div class="saq-card" style="margin-bottom: 0; padding: 0;">
                    <div style="padding: 24px 24px 8px;">
                        <p class="saq-card__label" style="margin-bottom: 0;">Completions by Company</p>
                    </div>
                    <div class="saq-bars">
                        <?php foreach ( $by_company as $cname => $stats ) :
                            $bar_pct = $max_company_completed > 0
                                ? round( ( $stats['completed'] / $max_company_completed ) * 100 )
                                : 0;
                        ?>
                        <div class="saq-bar">
                            <span class="saq-bar__label" title="<?php echo esc_attr( $cname ); ?>"><?php echo esc_html( $cname ); ?></span>
                            <div class="saq-bar__track">
                                <div class="saq-bar__fill" style="width: <?php echo esc_attr( $bar_pct ); ?>%;"></div>
                            </div>
                            <span class="saq-bar__value"><?php echo esc_html( $stats['completed'] ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Group Completion Table -->
            <div class="saq-card">
                <p class="saq-card__label">Group Completion Rates</p>
                <div class="saq-table-wrap">
                    <table class="saq-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th style="min-width: 180px;">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_group as $gname => $gstats ) :
                                $rate = $gstats['total'] > 0
                                    ? round( ( $gstats['completed'] / $gstats['total'] ) * 100, 1 )
                                    : 0;
                                $fill_class = 'saq-progress__fill--high';
                                if ( $rate < 33 ) {
                                    $fill_class = 'saq-progress__fill--low';
                                } elseif ( $rate < 66 ) {
                                    $fill_class = 'saq-progress__fill--mid';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $gname ); ?></strong></td>
                                <td><?php echo esc_html( $gstats['total'] ); ?></td>
                                <td><?php echo esc_html( $gstats['completed'] ); ?></td>
                                <td>
                                    <div class="saq-progress">
                                        <div class="saq-progress__bar">
                                            <div class="saq-progress__fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( $rate ); ?>%;"></div>
                                        </div>
                                        <span class="saq-progress__text"><?php echo esc_html( $rate ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $export_url = add_query_arg( array(
                    'cr_cat'     => $cat,
                    'cr_from'    => $from,
                    'cr_to'      => $to,
                    'cr_company' => $company,
                    'cr_export'  => '1',
                ) );
                ?>
                <a href="<?php echo esc_url( $export_url ); ?>" class="saq-export">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download CSV
                </a>
            </div>

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
