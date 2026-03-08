<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_User_Report extends ScaleAQ_Report_Base {

    public static function render( $atts = array() ) {
        global $wpdb;

        wp_enqueue_style( 'scaleaq-reports' );

        $cat     = sanitize_text_field( $_GET['ur_cat'] ?? '' );
        $from    = self::sanitize_date( $_GET['ur_from'] ?? self::get_default_from() );
        $to      = self::sanitize_date( $_GET['ur_to'] ?? self::get_default_to() );
        $company = sanitize_text_field( $_GET['ur_company'] ?? '' );
        $export  = sanitize_text_field( $_GET['ur_export'] ?? '' );

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
        if ( $company !== '' ) {
            $extra_where = $wpdb->prepare( "AND ms.meta_value = %s", $company );
            $users       = $wpdb->get_results( self::get_base_user_query( $extra_where ) );
        } else {
            $users = $all_users;
        }

        // Check completion if a category is selected.
        $completed_set = array();
        if ( $cat !== '' && isset( $course_map[ $cat ] ) ) {
            $course_ids   = $course_map[ $cat ];
            $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

            $activity_sql = "SELECT user_id, MAX(`{$ts_col}`) as completed_ts
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

            $activity_sql .= " GROUP BY user_id";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $completed_rows = $wpdb->get_results( $wpdb->prepare( $activity_sql, $prepare_args ) );
            foreach ( $completed_rows as $row ) {
                $completed_set[ $row->user_id ] = (int) $row->completed_ts;
            }
        }

        // CSV export.
        if ( $export === '1' ) {
            self::export_csv( $users, $completed_set, $cat, $category_labels );
            return '';
        }

        $cat_display     = ( $cat !== '' && isset( $category_labels[ $cat ] ) ) ? $category_labels[ $cat ] : 'All Courses';
        $company_display = $company !== '' ? $company : 'All';

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
                    <p class="saq-header__subtitle">Snapshot <?php echo ( $from !== '' && $to !== '' ) ? esc_html( $from ) . ' &mdash; ' . esc_html( $to ) : 'All time'; ?></p>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="saq-card" style="animation-delay: 0s;">
                <div class="saq-filters">
                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Category</span>
                        <select name="ur_cat" id="ur_cat">
                            <option value="">All Courses</option>
                            <?php foreach ( $category_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cat, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="saq-filters__group saq-filters__group--grow">
                        <span class="saq-filters__label">Company</span>
                        <select name="ur_company" id="ur_company">
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
                        <input type="date" name="ur_from" id="ur_from" value="<?php echo esc_attr( $from ); ?>" />
                    </div>

                    <div class="saq-filters__group">
                        <span class="saq-filters__label">To</span>
                        <input type="date" name="ur_to" id="ur_to" value="<?php echo esc_attr( $to ); ?>" />
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
                    $csv_url = add_query_arg( array(
                        'ur_cat'     => $cat,
                        'ur_from'    => $from,
                        'ur_to'      => $to,
                        'ur_company' => $company,
                        'ur_export'  => '1',
                    ) );
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
                                <?php if ( $cat !== '' ) : ?>
                                    <th>Status</th>
                                    <th>Completed</th>
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
                                    $user_ts = $completed_set[ $u->ID ] ?? null;
                                ?>
                                    <td>
                                        <?php if ( $user_ts ) : ?>
                                            <span class="saq-badge saq-badge--yes"><span class="saq-badge__dot"></span> Completed</span>
                                        <?php else : ?>
                                            <span class="saq-badge saq-badge--no"><span class="saq-badge__dot"></span> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user_ts ? esc_html( gmdate( 'd/m/Y', $user_ts ) ) : '&mdash;'; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

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
            $headers[] = 'Completed Date';
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
                $ts = $completed_set[ $u->ID ] ?? null;
                $row[] = $ts ? 'Yes' : 'No';
                $row[] = $ts ? gmdate( 'd/m/Y', $ts ) : '';
            }
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
