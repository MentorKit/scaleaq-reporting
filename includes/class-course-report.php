<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_Course_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        wp_enqueue_style( 'scaleaq-reports' );

        $cat     = sanitize_text_field( $_GET['cr_cat'] ?? 'hse' );
        $period  = sanitize_text_field( $_GET['cr_period'] ?? 'all' );
        $to      = self::sanitize_date( $_GET['cr_to'] ?? '' );
        $companies_selected = self::sanitize_companies( $_GET['cr_company'] ?? array() );
        $export  = sanitize_text_field( $_GET['cr_export'] ?? '' );

        // Resolve period preset to cutoff date.
        $resolved = self::resolve_period( $period, $to );
        $to       = $resolved['to'];
        $period_label = $resolved['label'];

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
        if ( ! empty( $companies_selected ) ) {
            $extra_where = self::build_company_where( $companies_selected );
            $users       = $wpdb->get_results( self::get_base_user_query( $extra_where ) );
        } else {
            $users = $all_users;
        }

        // Build completion lookup.
        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
        $activity_sql = "SELECT user_id, MAX(`{$ts_col}`) as completed_ts
            FROM {$wpdb->prefix}learndash_user_activity
            WHERE activity_type = 'course'
                AND activity_status = 1
                AND post_id IN ({$placeholders})";

        $prepare_args = $course_ids;

        if ( $to !== '' ) {
            $to_ts        = strtotime( $to . ' 23:59:59' );
            $activity_sql .= $wpdb->prepare( " AND `{$ts_col}` <= %d", $to_ts );
        }

        $activity_sql .= " GROUP BY user_id";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $completed_rows = $wpdb->get_results( $wpdb->prepare( $activity_sql, $prepare_args ) );
        $completed_set  = array();
        foreach ( $completed_rows as $row ) {
            $completed_set[ $row->user_id ] = (int) $row->completed_ts;
        }

        // Tally stats + build drill-down user lists.
        $total              = count( $users );
        $completed          = 0;
        $by_company         = array();
        $completed_users    = array();
        $not_completed_users = array();
        $by_group           = array(
            'Moen Marin AS' => array( 'total' => 0, 'completed' => 0 ),
            'ScaleAQ Group' => array( 'total' => 0, 'completed' => 0 ),
            'Other'         => array( 'total' => 0, 'completed' => 0 ),
        );
        $group_completed     = array( 'Moen Marin AS' => array(), 'ScaleAQ Group' => array(), 'Other' => array() );
        $group_not_completed = array( 'Moen Marin AS' => array(), 'ScaleAQ Group' => array(), 'Other' => array() );

        foreach ( $users as $u ) {
            $done        = isset( $completed_set[ $u->ID ] );
            $comp_name   = trim( $u->company ?? 'Unknown' );
            $group_label = self::get_group_label( $comp_name );

            if ( $done ) {
                $completed++;
                $completed_users[] = $u;
                $group_completed[ $group_label ][] = $u;
            } else {
                $not_completed_users[] = $u;
                $group_not_completed[ $group_label ][] = $u;
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

        // Filter companies to only those with completions, sort descending.
        $by_company_completed = array_filter( $by_company, function ( $stats ) {
            return $stats['completed'] > 0;
        } );
        uasort( $by_company_completed, function ( $a, $b ) {
            return $b['completed'] - $a['completed'];
        } );
        $total_company_completions = array_sum( array_column( $by_company_completed, 'completed' ) );

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
                    <?php if ( $period === 'all' ) : ?>
                        <p class="saq-header__subtitle">Showing all completions recorded, regardless of date</p>
                    <?php else : ?>
                        <p class="saq-header__subtitle">Showing completions recorded by: <?php echo esc_html( $period_label ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="saq-card" style="animation-delay: 0s; position: relative; z-index: 10;">
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
                        <?php self::render_multiselect( 'cr_company', $companies, $companies_selected ); ?>
                    </div>

                    <div class="saq-filters__group">
                        <span class="saq-filters__label">Time Period</span>
                        <select name="cr_period" id="cr_period" onchange="document.getElementById('cr_daterange').style.display=this.value==='custom'?'flex':'none';">
                            <?php foreach ( self::get_period_options() as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__daterange" id="cr_daterange" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; align-items: flex-end; gap: 16px;">
                        <div class="saq-filters__group">
                            <span class="saq-filters__label">Cutoff date</span>
                            <input type="date" name="cr_to" id="cr_to" value="<?php echo esc_attr( $period === 'custom' ? $to : '' ); ?>" />
                        </div>
                    </div>

                    <button type="submit" class="saq-filters__submit" style="font-family: 'Outfit', system-ui, sans-serif !important; font-size: 14px !important; font-weight: 600 !important; height: 40px !important; padding: 0 24px !important; border: none !important; border-radius: 8px !important; background: linear-gradient(135deg, #111827, #334155) !important; color: #fff !important; line-height: 40px !important; text-transform: none !important; box-shadow: none !important; cursor: pointer; white-space: nowrap; letter-spacing: 0.01em;">Filter</button>
                </div>
            </form>

            <?php
            $has_period    = $period !== 'all';
            $lbl_completed = $has_period ? 'Completed by cutoff' : 'Completed';
            $lbl_not       = $has_period ? 'Not completed by cutoff' : 'Not Completed';
            $lbl_rate      = $has_period ? 'Rate by cutoff' : 'Completion Rate';
            ?>
            <!-- Stat Cards -->
            <div class="saq-stats">
                <div class="saq-stat saq-stat--total">
                    <div class="saq-stat__value"><?php echo esc_html( $total ); ?></div>
                    <div class="saq-stat__label">Total Users</div>
                </div>
                <div class="saq-stat saq-stat--completed">
                    <div class="saq-stat__value">
                        <button type="button" class="saq-drilldown-toggle saq-drilldown-toggle--stat" onclick="document.getElementById('saq-dd-completed').classList.toggle('saq-drilldown--open')"><?php echo esc_html( $completed ); ?></button>
                    </div>
                    <div class="saq-stat__label"><?php echo esc_html( $lbl_completed ); ?></div>
                </div>
                <div class="saq-stat saq-stat--pending">
                    <div class="saq-stat__value">
                        <button type="button" class="saq-drilldown-toggle saq-drilldown-toggle--stat" onclick="document.getElementById('saq-dd-not-completed').classList.toggle('saq-drilldown--open')"><?php echo esc_html( $not_completed ); ?></button>
                    </div>
                    <div class="saq-stat__label"><?php echo esc_html( $lbl_not ); ?></div>
                </div>
                <div class="saq-stat saq-stat--rate">
                    <div class="saq-stat__value"><?php echo esc_html( $completion_pct ); ?>%</div>
                    <div class="saq-stat__label"><?php echo esc_html( $lbl_rate ); ?></div>
                </div>
            </div>

            <!-- Drill-Down: Completed Users -->
            <div class="saq-drilldown" id="saq-dd-completed">
                <div class="saq-card">
                    <p class="saq-card__label">Completed Users (<?php echo count( $completed_users ); ?>)</p>
                    <div class="saq-table-wrap">
                        <table class="saq-table">
                            <thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Company</th><th>Completed Date</th></tr></thead>
                            <tbody>
                            <?php foreach ( $completed_users as $u ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $u->first_name ); ?></td>
                                    <td><?php echo esc_html( $u->last_name ); ?></td>
                                    <td><?php echo esc_html( $u->user_email ); ?></td>
                                    <td><?php echo esc_html( $u->company ?? '' ); ?></td>
                                    <td><?php echo esc_html( gmdate( 'd/m/Y', $completed_set[ $u->ID ] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Drill-Down: Not Completed Users -->
            <div class="saq-drilldown" id="saq-dd-not-completed">
                <div class="saq-card">
                    <p class="saq-card__label">Not Completed Users (<?php echo count( $not_completed_users ); ?>)</p>
                    <div class="saq-table-wrap">
                        <table class="saq-table">
                            <thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Company</th></tr></thead>
                            <tbody>
                            <?php foreach ( $not_completed_users as $u ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $u->first_name ); ?></td>
                                    <td><?php echo esc_html( $u->last_name ); ?></td>
                                    <td><?php echo esc_html( $u->user_email ); ?></td>
                                    <td><?php echo esc_html( $u->company ?? '' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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

                <?php if ( ! empty( $by_company_completed ) ) :
                    // Build conic-gradient segments and legend colors.
                    $company_colors = array(
                        '#14b8a6', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444',
                        '#10b981', '#3b82f6', '#ec4899', '#f97316', '#6366f1',
                        '#84cc16', '#0ea5e9', '#d946ef', '#eab308', '#64748b',
                    );
                    $segments = array();
                    $legend_items = array();
                    $deg_cursor = 0;
                    $i = 0;
                    foreach ( $by_company_completed as $cname => $stats ) :
                        $color = $company_colors[ $i % count( $company_colors ) ];
                        $slice_deg = $total_company_completions > 0
                            ? ( $stats['completed'] / $total_company_completions ) * 360
                            : 0;
                        $end_deg = $deg_cursor + $slice_deg;
                        $segments[] = "{$color} {$deg_cursor}deg {$end_deg}deg";
                        $legend_items[] = array( 'name' => $cname, 'count' => $stats['completed'], 'color' => $color );
                        $deg_cursor = $end_deg;
                        $i++;
                    endforeach;
                    $gradient = implode( ', ', $segments );
                ?>
                <div class="saq-card" style="margin-bottom: 0; padding: 0;">
                    <div style="padding: 24px 24px 8px;">
                        <p class="saq-card__label" style="margin-bottom: 0;">Completions by Company</p>
                    </div>
                    <div class="saq-company-chart" style="display: flex; align-items: center; gap: 32px; padding: 24px;">
                        <div class="saq-company-donut" style="position: relative; width: 160px; height: 160px; border-radius: 50%; flex-shrink: 0;">
                            <div class="saq-company-donut__ring" style="position: absolute; inset: 0; border-radius: 50%; background: conic-gradient(<?php echo $gradient; ?>);"></div>
                            <div class="saq-company-donut__hole" style="position: absolute; inset: 28px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1;">
                                <span class="saq-company-donut__total" style="font-family: 'Outfit', system-ui, sans-serif; font-size: 28px; font-weight: 800; color: #0b1120; line-height: 1;"><?php echo esc_html( $total_company_completions ); ?></span>
                                <span class="saq-company-donut__caption" style="font-size: 11px; color: #64748b; margin-top: 2px;">completed</span>
                            </div>
                        </div>
                        <div class="saq-company-legend" style="display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0;">
                            <?php foreach ( $legend_items as $item ) : ?>
                            <div class="saq-company-legend__item" style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #334155; font-weight: 500;">
                                <span class="saq-company-legend__dot" style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
                                <span class="saq-company-legend__name" style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo esc_html( $item['name'] ); ?></span>
                                <span class="saq-company-legend__count" style="font-family: 'Outfit', system-ui, sans-serif; font-weight: 700; color: #1e293b; margin-left: auto; flex-shrink: 0;"><?php echo esc_html( $item['count'] ); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php
            // Per-company breakdown when multiple companies are selected (or all).
            $show_company_table = count( $by_company ) > 1;
            if ( $show_company_table ) :
                // Sort by company name.
                ksort( $by_company );
            ?>
            <!-- Company Completion Table -->
            <div class="saq-card">
                <p class="saq-card__label">Company Completion Rates</p>
                <div class="saq-table-wrap">
                    <table class="saq-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Not Completed</th>
                                <th style="min-width: 180px;">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_company as $cname => $cstats ) :
                                $c_rate = $cstats['total'] > 0
                                    ? round( ( $cstats['completed'] / $cstats['total'] ) * 100, 1 )
                                    : 0;
                                $c_fill_class = 'saq-progress__fill--high';
                                if ( $c_rate < 33 ) {
                                    $c_fill_class = 'saq-progress__fill--low';
                                } elseif ( $c_rate < 66 ) {
                                    $c_fill_class = 'saq-progress__fill--mid';
                                }
                                $c_not = $cstats['total'] - $cstats['completed'];
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $cname ); ?></strong></td>
                                <td><?php echo esc_html( $cstats['total'] ); ?></td>
                                <td><?php echo esc_html( $cstats['completed'] ); ?></td>
                                <td><?php echo esc_html( $c_not ); ?></td>
                                <td>
                                    <div class="saq-progress">
                                        <div class="saq-progress__bar">
                                            <div class="saq-progress__fill <?php echo esc_attr( $c_fill_class ); ?>" style="width: <?php echo esc_attr( $c_rate ); ?>%;"></div>
                                        </div>
                                        <span class="saq-progress__text"><?php echo esc_html( $c_rate ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

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
                                <th>Not Completed</th>
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
                                $gslug       = sanitize_title( $gname );
                                $g_not_count = $gstats['total'] - $gstats['completed'];
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $gname ); ?></strong></td>
                                <td><?php echo esc_html( $gstats['total'] ); ?></td>
                                <td>
                                    <?php if ( $gstats['completed'] > 0 ) : ?>
                                        <button type="button" class="saq-drilldown-toggle" onclick="document.getElementById('saq-dd-grp-c-<?php echo esc_attr( $gslug ); ?>').classList.toggle('saq-drilldown--open')"><?php echo esc_html( $gstats['completed'] ); ?></button>
                                    <?php else : ?>
                                        <?php echo esc_html( $gstats['completed'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $g_not_count > 0 ) : ?>
                                        <button type="button" class="saq-drilldown-toggle" onclick="document.getElementById('saq-dd-grp-nc-<?php echo esc_attr( $gslug ); ?>').classList.toggle('saq-drilldown--open')"><?php echo esc_html( $g_not_count ); ?></button>
                                    <?php else : ?>
                                        <?php echo esc_html( $g_not_count ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="saq-progress">
                                        <div class="saq-progress__bar">
                                            <div class="saq-progress__fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( $rate ); ?>%;"></div>
                                        </div>
                                        <span class="saq-progress__text"><?php echo esc_html( $rate ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php if ( ! empty( $group_completed[ $gname ] ) ) : ?>
                            <tr class="saq-drilldown" id="saq-dd-grp-c-<?php echo esc_attr( $gslug ); ?>">
                                <td colspan="5" style="padding: 0;">
                                    <div class="saq-drilldown__inner">
                                        <p class="saq-card__label">Completed — <?php echo esc_html( $gname ); ?> (<?php echo count( $group_completed[ $gname ] ); ?>)</p>
                                        <table class="saq-table saq-table--nested">
                                            <thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Company</th><th>Completed Date</th></tr></thead>
                                            <tbody>
                                            <?php foreach ( $group_completed[ $gname ] as $gu ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( $gu->first_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->last_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->user_email ); ?></td>
                                                    <td><?php echo esc_html( $gu->company ?? '' ); ?></td>
                                                    <td><?php echo esc_html( gmdate( 'd/m/Y', $completed_set[ $gu->ID ] ) ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if ( ! empty( $group_not_completed[ $gname ] ) ) : ?>
                            <tr class="saq-drilldown" id="saq-dd-grp-nc-<?php echo esc_attr( $gslug ); ?>">
                                <td colspan="5" style="padding: 0;">
                                    <div class="saq-drilldown__inner">
                                        <p class="saq-card__label">Not Completed — <?php echo esc_html( $gname ); ?> (<?php echo count( $group_not_completed[ $gname ] ); ?>)</p>
                                        <table class="saq-table saq-table--nested">
                                            <thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Company</th></tr></thead>
                                            <tbody>
                                            <?php foreach ( $group_not_completed[ $gname ] as $gu ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( $gu->first_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->last_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->user_email ); ?></td>
                                                    <td><?php echo esc_html( $gu->company ?? '' ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $export_params = array(
                    'cr_cat'    => $cat,
                    'cr_period' => $period,
                    'cr_to'     => $to,
                    'cr_export' => '1',
                );
                if ( ! empty( $companies_selected ) ) {
                    $export_params['cr_company'] = $companies_selected;
                }
                $export_url = '?' . http_build_query( $export_params );
                ?>
                <a href="<?php echo esc_url( $export_url ); ?>" class="saq-export">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download CSV
                </a>
            </div>

        </div>
        <?php
        self::render_multiselect_js();
        return ob_get_clean();
    }

    private static function export_csv( $users, $completed_set, $cat, $category_labels ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="course-report-' . esc_attr( $cat ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Email', 'First Name', 'Last Name', 'Company', 'Category', 'Completed', 'Completed Date' ) );

        foreach ( $users as $u ) {
            $ts = $completed_set[ $u->ID ] ?? null;
            fputcsv( $output, array(
                $u->ID,
                $u->user_email,
                $u->first_name,
                $u->last_name,
                $u->company ?? '',
                $category_labels[ $cat ] ?? $cat,
                $ts ? 'Yes' : 'No',
                $ts ? gmdate( 'd/m/Y', $ts ) : '',
            ) );
        }

        fclose( $output );
        exit;
    }
}
