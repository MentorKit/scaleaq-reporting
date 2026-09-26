<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_User_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        wp_enqueue_style( 'scaleaq-reports' );

        $cat     = sanitize_text_field( $_GET['ur_cat'] ?? '' );
        $period  = sanitize_text_field( $_GET['ur_period'] ?? 'all' );
        $to      = self::sanitize_date( $_GET['ur_to'] ?? '' );
        $companies_selected = self::sanitize_companies( $_GET['ur_company'] ?? array() );
        $export  = sanitize_text_field( $_GET['ur_export'] ?? '' );

        // Resolve period preset to cutoff date.
        $resolved = self::resolve_period( $period, $to );
        $to       = $resolved['to'];
        $period_label = $resolved['label'];

        $course_map      = self::get_course_ids_map();
        $category_labels = self::get_category_labels();

        $category_course_ids = ( $cat !== '' && isset( $course_map[ $cat ] ) )
            ? array_map( 'intval', $course_map[ $cat ] )
            : array();
        $course_id     = self::sanitize_course_id( $_GET['ur_course'] ?? 0, $category_course_ids );
        $course_titles = self::get_course_titles( $category_course_ids );

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
        if ( ! empty( $companies_selected ) ) {
            $extra_where = self::build_company_where( $companies_selected );
            $users       = $wpdb->get_results( self::get_base_user_query( $extra_where ) );
        } else {
            $users = $all_users;
        }

        // Check completion / assignment if a category is selected.
        $completed_set = array();
        $assigned_set  = array();
        $started_raw   = array();
        if ( $cat !== '' && isset( $course_map[ $cat ] ) ) {
            $course_ids    = self::resolve_course_ids( $cat, $course_id );
            $completed_set = self::fetch_user_completions( $course_ids, $to );
            $assigned_set  = self::fetch_assigned_user_ids( $course_ids );
            $started_raw   = self::fetch_started_user_ids( $course_ids );
        }

        // CSV export.
        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $assigned_set, $started_raw, $cat, $category_labels );
            return '';
        }

        $cat_display = ( $cat !== '' && isset( $category_labels[ $cat ] ) ) ? $category_labels[ $cat ] : 'All Courses';
        if ( $course_id > 0 ) {
            $cat_display .= ' — ' . ( $course_titles[ $course_id ] ?? ( 'Course #' . $course_id ) );
        }
        $company_display = ! empty( $companies_selected ) ? implode( ', ', $companies_selected ) : 'All';

        // Render output.
        ob_start();
        ?>
        <div class="scaleaq-report scaleaq-user-report">

            <!-- Header -->
            <div class="saq-header">
                <div class="saq-header__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <h2 class="saq-header__title">User Report</h2>
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
                        <select name="ur_cat" id="ur_cat" onchange="var c=document.getElementById('ur_course'); if(c){c.value='';} this.form.submit();">
                            <option value="">All Courses</option>
                            <?php foreach ( $category_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cat, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Course</span>
                        <select name="ur_course" id="ur_course" <?php disabled( $cat === '' || empty( $course_titles ) ); ?>>
                            <?php if ( $cat === '' || empty( $course_titles ) ) : ?>
                                <option value="">Select a category first</option>
                            <?php else : ?>
                                <option value="">All courses in category</option>
                                <?php foreach ( $course_titles as $cid => $ctitle ) : ?>
                                    <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $course_id, (int) $cid ); ?>>
                                        <?php echo esc_html( $ctitle ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Company</span>
                        <?php self::render_multiselect( 'ur_company', $companies, $companies_selected ); ?>
                    </div>

                    <div class="saq-filters__group">
                        <span class="saq-filters__label">Time Period</span>
                        <select name="ur_period" id="ur_period" onchange="document.getElementById('ur_daterange').style.display=this.value==='custom'?'flex':'none';">
                            <?php foreach ( self::get_period_options() as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__daterange" id="ur_daterange" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; align-items: flex-end; gap: 16px;">
                        <div class="saq-filters__group">
                            <span class="saq-filters__label">Cutoff date</span>
                            <input type="date" name="ur_to" id="ur_to" value="<?php echo esc_attr( $period === 'custom' ? $to : '' ); ?>" />
                        </div>
                    </div>

                    <button type="submit" class="saq-filters__submit" style="font-family: 'Outfit', system-ui, sans-serif !important; font-size: 14px !important; font-weight: 600 !important; height: 40px !important; padding: 0 24px !important; border: none !important; border-radius: 8px !important; background: linear-gradient(135deg, #111827, #334155) !important; color: #fff !important; line-height: 40px !important; text-transform: none !important; box-shadow: none !important; cursor: pointer; white-space: nowrap; letter-spacing: 0.01em;">Filter</button>
                </div>
            </form>

            <!-- Data Table -->
            <div class="saq-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <p class="saq-card__label" style="margin-bottom: 8px;">User List</p>
                        <div class="saq-summary">
                            <span class="saq-summary__item">
                                <span class="saq-summary__label">Category:</span>
                                <span class="saq-summary__value"><?php echo esc_html( $cat_display ); ?></span>
                            </span>
                            <span class="saq-summary__item">
                                <span class="saq-summary__label">Company:</span>
                                <span class="saq-summary__value"><?php echo esc_html( $company_display ); ?></span>
                            </span>
                            <span class="saq-summary__item">
                                <span class="saq-summary__label">Users:</span>
                                <span class="saq-summary__value"><?php echo esc_html( count( $users ) ); ?></span>
                            </span>
                        </div>
                    </div>

                    <?php
                    $csv_params = array(
                        'ur_cat'    => $cat,
                        'ur_course' => $course_id > 0 ? $course_id : '',
                        'ur_period' => $period,
                        'ur_to'     => $to,
                        'ur_export' => '1',
                    );
                    if ( ! empty( $companies_selected ) ) {
                        $csv_params['ur_company'] = $companies_selected;
                    }
                    $csv_url = '?' . http_build_query( $csv_params );
                    ?>
                    <a href="<?php echo esc_url( $csv_url ); ?>" class="saq-export">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download CSV
                    </a>
                </div>

                <div class="saq-table-wrap">
                    <table class="saq-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Company</th>
                                <?php if ( $cat !== '' ) :
                                    $has_period = $period !== 'all';
                                ?>
                                    <th>Assigned</th>
                                    <th>Started</th>
                                    <th><?php echo $has_period ? 'Status (by cutoff)' : 'Status'; ?></th>
                                    <th><?php echo $has_period ? 'Completed Date' : 'Completed'; ?></th>
                                    <th>Language</th>
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
                                <?php if ( $cat !== '' ) :
                                    $uid        = (int) $u->ID;
                                    $completion = $completed_set[ $uid ] ?? null;
                                    $user_ts    = $completion['ts'] ?? null;
                                    $u_assigned = isset( $assigned_set[ $uid ] );
                                    $u_started  = $u_assigned && isset( $started_raw[ $uid ] );
                                ?>
                                    <td><?php echo $u_assigned ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo $u_started ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <?php if ( $user_ts ) : ?>
                                            <span class="saq-badge saq-badge--yes"><span class="saq-badge__dot"></span> Completed</span>
                                        <?php else : ?>
                                            <span class="saq-badge saq-badge--no"><span class="saq-badge__dot"></span> <?php echo $has_period ? 'Not completed' : 'Pending'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user_ts ? esc_html( gmdate( 'd/m/Y', $user_ts ) ) : '&mdash;'; ?></td>
                                    <td><?php echo $user_ts ? esc_html( $completion['lang'] ?: '—' ) : '&mdash;'; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php
        self::enqueue_reports_js();
        return ob_get_clean();
    }

    private static function export_csv( $users, $completed_set, $assigned_set, $started_raw, $cat, $category_labels ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="user-report.csv"' );

        $output = fopen( 'php://output', 'w' );

        $headers = array( 'ID', 'Email', 'First Name', 'Last Name', 'Company' );
        if ( $cat !== '' ) {
            $headers[] = 'Assigned';
            $headers[] = 'Started';
            $headers[] = 'Has Completed';
            $headers[] = 'Completed Date';
            $headers[] = 'Language';
        }
        fputcsv( $output, $headers );

        foreach ( $users as $u ) {
            $uid = (int) $u->ID;
            $row = array(
                $u->ID,
                $u->user_email,
                $u->first_name,
                $u->last_name,
                $u->company ?? '',
            );
            if ( $cat !== '' ) {
                $completion  = $completed_set[ $uid ] ?? null;
                $ts          = $completion['ts'] ?? null;
                $is_assigned = isset( $assigned_set[ $uid ] );
                $is_started  = $is_assigned && isset( $started_raw[ $uid ] );
                $row[]       = $is_assigned ? 'Yes' : 'No';
                $row[]       = $is_started ? 'Yes' : 'No';
                $row[]       = $ts ? 'Yes' : 'No';
                $row[]       = $ts ? gmdate( 'd/m/Y', $ts ) : '';
                $row[]       = $completion ? ( $completion['lang'] ?? '' ) : '';
            }
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
