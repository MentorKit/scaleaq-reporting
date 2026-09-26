<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_Course_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        wp_enqueue_style( 'scaleaq-reports' );

        $metrics  = self::get_metric_definitions();
        $statuses = self::get_status_labels();

        $cat     = sanitize_text_field( $_GET['cr_cat'] ?? 'hse' );
        $period  = sanitize_text_field( $_GET['cr_period'] ?? 'all' );
        $to      = self::sanitize_date( $_GET['cr_to'] ?? '' );
        $companies_selected = self::sanitize_companies( $_GET['cr_company'] ?? array() );
        $export  = sanitize_text_field( $_GET['cr_export'] ?? '' );

        $resolved     = self::resolve_period( $period, $to );
        $to           = $resolved['to'];
        $period_label = $resolved['label'];

        $course_map      = self::get_course_ids_map();
        $category_labels = self::get_category_labels();

        if ( ! isset( $course_map[ $cat ] ) ) {
            $cat = 'hse';
        }

        $category_course_ids = array_map( 'intval', $course_map[ $cat ] );
        $course_id           = self::sanitize_course_id( $_GET['cr_course'] ?? 0, $category_course_ids );
        $course_ids          = self::resolve_course_ids( $cat, $course_id );
        $course_titles       = self::get_course_titles( $category_course_ids );

        $canonical_for_subtitle = $course_id > 0 ? array( $course_id ) : $category_course_ids;
        $report_title           = $category_labels[ $cat ];
        $report_subtitle        = self::format_report_subtitle( $canonical_for_subtitle, $course_ids );

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

        if ( ! empty( $companies_selected ) ) {
            $extra_where = self::build_company_where( $companies_selected );
            $users       = $wpdb->get_results( self::get_base_user_query( $extra_where ) );
        } else {
            $users = $all_users;
        }

        $scope_total      = count( $users );
        $completed_set    = self::fetch_user_completions( $course_ids, $to );
        $enrolled_set     = self::fetch_enrolled_user_ids( $course_ids );
        $started_activity = self::fetch_started_user_activity( $course_ids );

        $enrolled                   = 0;
        $not_started                = 0;
        $in_progress                = 0;
        $completed                  = 0;
        $completed_unenrolled       = 0;
        $by_company                 = array();
        $group_keys                 = self::get_group_labels_ordered();
        $by_group                   = array();
        $enrolled_users             = array();
        $not_started_users          = array();
        $in_progress_users          = array();
        $completed_users            = array();
        $completed_unenrolled_users = array();
        $group_completed            = array();

        foreach ( $group_keys as $gk ) {
            $by_group[ $gk ]        = array( 'enrolled' => 0, 'started' => 0, 'completed' => 0 );
            $group_completed[ $gk ] = array();
        }

        foreach ( $users as $u ) {
            $uid         = (int) $u->ID;
            $is_enrolled = isset( $enrolled_set[ $uid ] );
            $is_done     = isset( $completed_set[ $uid ] );
            $is_started  = isset( $started_activity[ $uid ] );
            $status_key  = self::resolve_user_status( $is_enrolled, $is_started, $is_done );
            $comp_name   = self::format_company_name( $u->company ?? '' );
            $group_label = self::get_group_label( $u->company ?? '' );

            if ( $is_enrolled ) {
                $enrolled++;
                $enrolled_users[] = $u;

                if ( $status_key === 'completed' ) {
                    $completed++;
                    $completed_users[] = $u;
                    $group_completed[ $group_label ][] = $u;
                } elseif ( $status_key === 'in_progress' ) {
                    $in_progress++;
                    $in_progress_users[] = $u;
                } else {
                    $not_started++;
                    $not_started_users[] = $u;
                }

                if ( ! isset( $by_company[ $comp_name ] ) ) {
                    $by_company[ $comp_name ] = array( 'enrolled' => 0, 'started' => 0, 'completed' => 0 );
                }
                $by_company[ $comp_name ]['enrolled']++;
                if ( $is_started || $is_done ) {
                    $by_company[ $comp_name ]['started']++;
                }
                if ( $is_done ) {
                    $by_company[ $comp_name ]['completed']++;
                }

                $by_group[ $group_label ]['enrolled']++;
                if ( $is_started || $is_done ) {
                    $by_group[ $group_label ]['started']++;
                }
                if ( $is_done ) {
                    $by_group[ $group_label ]['completed']++;
                }
            } elseif ( $is_done ) {
                $completed_unenrolled++;
                $completed_unenrolled_users[] = $u;
            }
        }

        $started                = $in_progress + $completed;
        $completion_pct         = $enrolled > 0 ? round( ( $completed / $enrolled ) * 100, 1 ) : 0;
        $completion_pct_started = $started > 0 ? round( ( $completed / $started ) * 100, 1 ) : 0;

        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $enrolled_set, $started_activity, $statuses );
            return '';
        }

        uasort(
            $by_company,
            function ( $a, $b ) {
                $ra = $a['enrolled'] > 0 ? ( $a['completed'] / $a['enrolled'] ) : 0;
                $rb = $b['enrolled'] > 0 ? ( $b['completed'] / $b['enrolled'] ) : 0;
                if ( $ra === $rb ) {
                    return $b['enrolled'] <=> $a['enrolled'];
                }
                return $rb <=> $ra;
            }
        );

        $donut_completed_pct   = $enrolled > 0 ? ( $completed / $enrolled ) * 100 : 0;
        $donut_in_progress_pct = $enrolled > 0 ? ( $in_progress / $enrolled ) * 100 : 0;
        $deg_c = $donut_completed_pct * 3.6;
        $deg_i = $donut_in_progress_pct * 3.6;
        $donut_gradient = sprintf(
            'conic-gradient(#10b981 0deg %sdeg, #0ea5e9 %sdeg %sdeg, #94a3b8 %sdeg 360deg)',
            $deg_c,
            $deg_c,
            $deg_c + $deg_i,
            $deg_c + $deg_i
        );

        ob_start();
        ?>
        <div class="scaleaq-report scaleaq-course-report">

            <div class="saq-header">
                <div class="saq-header__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5"/></svg>
                </div>
                <div>
                    <h2 class="saq-header__title">Course Completion: <?php echo esc_html( $report_title ); ?></h2>
                    <p class="saq-header__subtitle"><?php echo esc_html( $report_subtitle ); ?></p>
                    <?php if ( $period !== 'all' ) : ?>
                        <p class="saq-header__subtitle" style="margin-top: 4px;">Completions by: <?php echo esc_html( $period_label ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="get" class="saq-card" style="animation-delay: 0s; position: relative; z-index: 10;">
                <div class="saq-filters">
                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Category</span>
                        <select name="cr_cat" id="cr_cat" onchange="document.getElementById('cr_course').value=''; this.form.submit();">
                            <?php foreach ( $category_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cat, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Course</span>
                        <select name="cr_course" id="cr_course">
                            <option value="">All courses in category</option>
                            <?php foreach ( $course_titles as $cid => $ctitle ) : ?>
                                <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $course_id, (int) $cid ); ?>>
                                    <?php echo esc_html( $ctitle ); ?>
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

            <div class="saq-stats">
                <?php
                $stat_cards = array(
                    array( 'key' => 'enrolled', 'value' => $enrolled, 'dd' => 'saq-dd-enrolled', 'mod' => 'assigned' ),
                    array( 'key' => 'not_started', 'value' => $not_started, 'dd' => 'saq-dd-not-started', 'mod' => 'pending' ),
                    array( 'key' => 'in_progress', 'value' => $in_progress, 'dd' => 'saq-dd-in-progress', 'mod' => 'started' ),
                    array( 'key' => 'completed', 'value' => $completed, 'dd' => 'saq-dd-completed', 'mod' => 'completed' ),
                    array( 'key' => 'completion_rate', 'value' => $completion_pct . '%', 'dd' => '', 'mod' => 'rate' ),
                    array( 'key' => 'completion_rate_started', 'value' => $completion_pct_started . '%', 'dd' => '', 'mod' => 'rate' ),
                );
                foreach ( $stat_cards as $card ) :
                    $def       = $metrics[ $card['key'] ];
                    $clickable = $card['dd'] !== '';
                    ?>
                <div class="saq-stat saq-stat--<?php echo esc_attr( $card['mod'] ); ?><?php echo $clickable ? ' saq-stat--clickable' : ''; ?>"
                    <?php echo $clickable ? ' data-saq-dd="' . esc_attr( $card['dd'] ) . '" role="button" tabindex="0"' : ''; ?>>
                    <div class="saq-stat__value"><?php echo esc_html( $card['value'] ); ?></div>
                    <div class="saq-stat__label"><?php echo esc_html( $def['label'] ); ?></div>
                    <div class="saq-stat__help"><?php echo esc_html( $def['help'] ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <p class="saq-scope"><?php echo esc_html( self::format_scope_line( $scope_total ) ); ?></p>

            <?php if ( $completed_unenrolled > 0 ) : ?>
            <p class="saq-note saq-note--warning" style="margin: 0 0 20px; font-size: 13px; color: #92400e; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 10px 14px;">
                <strong>Completed (no longer enrolled):</strong>
                <button type="button" class="saq-drilldown-toggle" data-saq-dd="saq-dd-completed-unenrolled" style="font: inherit; color: inherit; text-decoration: underline; background: none; border: none; cursor: pointer; padding: 0;">
                    <?php echo esc_html( $completed_unenrolled ); ?>
                </button>
                — finished the course but do not currently have access.
            </p>
            <?php endif; ?>

            <?php
            self::render_user_drilldown( 'saq-dd-enrolled', $metrics['enrolled']['label'], $enrolled_users, $completed_set, $started_activity, true );
            self::render_user_drilldown( 'saq-dd-not-started', $metrics['not_started']['label'], $not_started_users, $completed_set, $started_activity, false );
            self::render_user_drilldown( 'saq-dd-in-progress', $metrics['in_progress']['label'], $in_progress_users, $completed_set, $started_activity, false );
            self::render_user_drilldown( 'saq-dd-completed', $metrics['completed']['label'], $completed_users, $completed_set, $started_activity, true );
            if ( ! empty( $completed_unenrolled_users ) ) {
                self::render_user_drilldown( 'saq-dd-completed-unenrolled', 'Completed (no longer enrolled)', $completed_unenrolled_users, $completed_set, $started_activity, true );
            }
            ?>

            <div class="saq-charts saq-charts--split">
                <div class="saq-card saq-donut-wrap" style="margin-bottom: 0;">
                    <p class="saq-card__label">Progress</p>
                    <div class="saq-company-donut" style="position: relative; width: 160px; height: 160px; border-radius: 50%; margin: 0 auto;">
                        <div style="position: absolute; inset: 0; border-radius: 50%; background: <?php echo esc_attr( $donut_gradient ); ?>;"></div>
                        <div style="position: absolute; inset: 28px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1;">
                            <span style="font-family: 'Outfit', system-ui, sans-serif; font-size: 28px; font-weight: 800; color: #0b1120; line-height: 1;"><?php echo esc_html( $completion_pct ); ?>%</span>
                            <span style="font-size: 11px; color: #64748b; margin-top: 2px;">of enrolled</span>
                        </div>
                    </div>
                    <ul class="saq-donut-legend saq-donut-legend--vertical">
                        <li class="saq-donut-legend__item"><span class="saq-donut-legend__dot" style="background:#10b981;"></span><span class="saq-donut-legend__text"><?php echo esc_html( $completed ); ?> <?php echo esc_html( $metrics['completed']['label'] ); ?></span></li>
                        <li class="saq-donut-legend__item"><span class="saq-donut-legend__dot" style="background:#0ea5e9;"></span><span class="saq-donut-legend__text"><?php echo esc_html( $in_progress ); ?> <?php echo esc_html( $metrics['in_progress']['label'] ); ?></span></li>
                        <li class="saq-donut-legend__item"><span class="saq-donut-legend__dot" style="background:#94a3b8;"></span><span class="saq-donut-legend__text"><?php echo esc_html( $not_started ); ?> <?php echo esc_html( $metrics['not_started']['label'] ); ?></span></li>
                    </ul>
                </div>

                <div class="saq-card" style="margin-bottom: 0;">
                    <p class="saq-card__label">Company Completion Rates</p>
                    <div class="saq-table-wrap">
                        <table class="saq-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th><?php echo esc_html( $metrics['enrolled']['label'] ); ?></th>
                                    <th><?php echo esc_html( $metrics['started']['label'] ); ?></th>
                                    <th><?php echo esc_html( $metrics['completed']['label'] ); ?></th>
                                    <th style="min-width: 140px;"><?php echo esc_html( $metrics['completion_rate']['label'] ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $by_company as $cname => $cstats ) :
                                    $c_rate = $cstats['enrolled'] > 0
                                        ? round( ( $cstats['completed'] / $cstats['enrolled'] ) * 100, 1 )
                                        : 0;
                                    $c_fill_class = 'saq-progress__fill--high';
                                    if ( $c_rate < 33 ) {
                                        $c_fill_class = 'saq-progress__fill--low';
                                    } elseif ( $c_rate < 66 ) {
                                        $c_fill_class = 'saq-progress__fill--mid';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $cname ); ?></strong></td>
                                    <td><?php echo esc_html( $cstats['enrolled'] ); ?></td>
                                    <td><?php echo esc_html( $cstats['started'] ); ?></td>
                                    <td><?php echo esc_html( $cstats['completed'] ); ?></td>
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
            </div>

            <div class="saq-card">
                <p class="saq-card__label">Group Completion Rates</p>
                <div class="saq-table-wrap">
                    <table class="saq-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th><?php echo esc_html( $metrics['enrolled']['label'] ); ?></th>
                                <th><?php echo esc_html( $metrics['started']['label'] ); ?></th>
                                <th><?php echo esc_html( $metrics['completed']['label'] ); ?></th>
                                <th style="min-width: 180px;"><?php echo esc_html( $metrics['completion_rate']['label'] ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_group as $gname => $gstats ) :
                                $rate = $gstats['enrolled'] > 0
                                    ? round( ( $gstats['completed'] / $gstats['enrolled'] ) * 100, 1 )
                                    : 0;
                                $fill_class = 'saq-progress__fill--high';
                                if ( $rate < 33 ) {
                                    $fill_class = 'saq-progress__fill--low';
                                } elseif ( $rate < 66 ) {
                                    $fill_class = 'saq-progress__fill--mid';
                                }
                                $gslug = sanitize_title( $gname );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $gname ); ?></strong></td>
                                <td><?php echo esc_html( $gstats['enrolled'] ); ?></td>
                                <td><?php echo esc_html( $gstats['started'] ); ?></td>
                                <td>
                                    <?php if ( $gstats['completed'] > 0 ) : ?>
                                        <button type="button" class="saq-drilldown-toggle" onclick="document.getElementById('saq-dd-grp-c-<?php echo esc_attr( $gslug ); ?>').classList.toggle('saq-drilldown--open')"><?php echo esc_html( $gstats['completed'] ); ?></button>
                                    <?php else : ?>
                                        <?php echo esc_html( $gstats['completed'] ); ?>
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
                                        <p class="saq-card__label"><?php echo esc_html( $metrics['completed']['label'] ); ?> — <?php echo esc_html( $gname ); ?> (<?php echo esc_html( count( $group_completed[ $gname ] ) ); ?>)</p>
                                        <table class="saq-table saq-table--nested">
                                            <thead><tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Company</th><th>Completed Date</th><th>Language</th></tr></thead>
                                            <tbody>
                                            <?php foreach ( $group_completed[ $gname ] as $gu ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( $gu->first_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->last_name ); ?></td>
                                                    <td><?php echo esc_html( $gu->user_email ); ?></td>
                                                    <td><?php echo esc_html( self::format_company_name( $gu->company ?? '' ) ); ?></td>
                                                    <td><?php echo esc_html( gmdate( 'd/m/Y', $completed_set[ $gu->ID ]['ts'] ) ); ?></td>
                                                    <td><?php echo esc_html( $completed_set[ $gu->ID ]['lang'] ?: '—' ); ?></td>
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
                    'cr_course' => $course_id > 0 ? $course_id : '',
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
        self::enqueue_reports_js();
        return ob_get_clean();
    }

    private static function render_user_drilldown( $id, $label, $list, $completed_set, $started_activity, $show_date ) {
        ?>
        <div class="saq-drilldown" id="<?php echo esc_attr( $id ); ?>">
            <div class="saq-card">
                <p class="saq-card__label"><?php echo esc_html( $label ); ?> (<?php echo esc_html( count( $list ) ); ?>)</p>
                <div class="saq-table-wrap">
                    <table class="saq-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Company</th>
                                <?php if ( $show_date ) : ?><th>Completed Date</th><?php endif; ?>
                                <th>Language</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $list as $u ) :
                            $uid  = (int) $u->ID;
                            $lang = '';
                            if ( isset( $completed_set[ $uid ] ) ) {
                                $lang = $completed_set[ $uid ]['lang'] ?? '';
                            } elseif ( isset( $started_activity[ $uid ] ) ) {
                                $lang = $started_activity[ $uid ]['lang'] ?? '';
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html( $u->first_name ); ?></td>
                                <td><?php echo esc_html( $u->last_name ); ?></td>
                                <td><?php echo esc_html( $u->user_email ); ?></td>
                                <td><?php echo esc_html( self::format_company_name( $u->company ?? '' ) ); ?></td>
                                <?php if ( $show_date ) : ?>
                                    <td><?php echo isset( $completed_set[ $uid ] ) ? esc_html( gmdate( 'd/m/Y', $completed_set[ $uid ]['ts'] ) ) : '&mdash;'; ?></td>
                                <?php endif; ?>
                                <td><?php echo $lang !== '' ? esc_html( $lang ) : '&mdash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private static function export_csv( $users, $completed_set, $enrolled_set, $started_activity, $statuses ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="course-report.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array(
            'ID',
            'Email',
            'First name',
            'Last name',
            'Company',
            'Group',
            'Enrolled',
            'Started',
            'Completed',
            'Status',
            'Language',
            'Completed date',
        ) );

        foreach ( $users as $u ) {
            $uid         = (int) $u->ID;
            $is_enrolled = isset( $enrolled_set[ $uid ] );
            $is_done     = isset( $completed_set[ $uid ] );
            $is_started  = isset( $started_activity[ $uid ] );
            $status_key  = self::resolve_user_status( $is_enrolled, $is_started, $is_done );
            $lang        = '';
            if ( $is_done ) {
                $lang = $completed_set[ $uid ]['lang'] ?? '';
            } elseif ( $is_started ) {
                $lang = $started_activity[ $uid ]['lang'] ?? '';
            }
            $started_yes = ( $is_enrolled && ( $is_started || $is_done ) );

            fputcsv( $output, array(
                $u->ID,
                $u->user_email,
                $u->first_name,
                $u->last_name,
                self::format_company_name( $u->company ?? '' ),
                self::get_group_label( $u->company ?? '' ),
                $is_enrolled ? 'Yes' : 'No',
                $started_yes ? 'Yes' : 'No',
                $is_done ? 'Yes' : 'No',
                $statuses[ $status_key ] ?? $status_key,
                $lang,
                $is_done ? gmdate( 'd/m/Y', $completed_set[ $uid ]['ts'] ) : '',
            ) );
        }

        fclose( $output );
        exit;
    }
}
