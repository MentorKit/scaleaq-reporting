<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class ScaleAQ_Report_Base {

    public static function get_default_from() {
        return '';
    }

    public static function get_default_to() {
        return '';
    }

    public static function get_base_where() {
        return "um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%\"subscriber\"%'
            AND fn.meta_key = 'first_name' AND fn.meta_value != ''
            AND ln.meta_key = 'last_name' AND ln.meta_value != ''
            AND (
                u.user_email LIKE '%scaleaq.com%'
                OR u.user_email LIKE '%moenmarin.no%'
                OR u.user_email LIKE '%maskon.no%'
                OR u.user_email LIKE '%scaleaq.academy%'
            )
            AND u.user_email NOT LIKE '%demo%'
            AND u.user_email NOT LIKE '%revisor%'
            AND u.user_email NOT LIKE '%test%'
            AND u.user_email NOT LIKE '%dummy%'
            AND u.user_email NOT LIKE '%admin%'
            AND u.user_email NOT LIKE '%support%'
            AND u.user_email NOT LIKE '%spare.equipment%'
            AND u.user_email NOT LIKE '%logistics%'
            AND u.user_email NOT LIKE '%bank%'
            AND u.user_email NOT LIKE '%accounts%'
            AND u.user_email NOT LIKE '%seleccion%'
            AND u.user_email NOT LIKE '%developers%'";
    }

    public static function get_course_ids_map() {
        return array(
            'hse' => array( 46681, 47052, 47386 ),
            'coc' => array( 47232, 46085, 47053 ),
            'it'  => array( 50346, 50348 ),
        );
    }

    public static function get_category_labels() {
        return array(
            'hse' => 'HSE',
            'coc' => 'CoC',
            'it'  => 'IT',
        );
    }

    public static function get_group_label( $company_name ) {
        if ( stripos( $company_name, 'Moen Marin' ) !== false ) {
            return 'Moen Marin AS';
        }
        if ( stripos( $company_name, 'ScaleAQ' ) !== false ) {
            return 'ScaleAQ Group';
        }
        return 'Other';
    }

    public static function detect_timestamp_column() {
        global $wpdb;

        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}learndash_user_activity" );
        $preferred = array( 'activity_completed', 'activity_updated', 'activity_started' );

        foreach ( $preferred as $col ) {
            foreach ( $columns as $column ) {
                if ( $column->Field === $col ) {
                    return $col;
                }
            }
        }

        return 'activity_completed';
    }

    public static function sanitize_date( $date ) {
        $date = sanitize_text_field( $date );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return $date;
        }
        return '';
    }

    /**
     * Resolve a period preset to from/to dates.
     *
     * @param string $period  One of: all, 12m, last_year, custom.
     * @param string $from    Custom from date (only used when period=custom).
     * @param string $to      Custom to date (only used when period=custom).
     * @return array { 'from' => string, 'to' => string, 'label' => string }
     */
    public static function resolve_period( $period, $from = '', $to = '' ) {
        switch ( $period ) {
            case '12m':
                return array(
                    'from'  => gmdate( 'Y-m-d', strtotime( '-12 months' ) ),
                    'to'    => gmdate( 'Y-m-d' ),
                    'label' => 'Last 12 months',
                );
            case 'last_year':
                $year = (int) gmdate( 'Y' ) - 1;
                return array(
                    'from'  => $year . '-01-01',
                    'to'    => $year . '-12-31',
                    'label' => (string) $year,
                );
            case 'custom':
                $label = 'Custom range';
                if ( $from !== '' && $to !== '' ) {
                    $label = $from . ' — ' . $to;
                } elseif ( $from !== '' ) {
                    $label = 'From ' . $from;
                } elseif ( $to !== '' ) {
                    $label = 'Until ' . $to;
                }
                return array(
                    'from'  => $from,
                    'to'    => $to,
                    'label' => $label,
                );
            default: // 'all'
                return array(
                    'from'  => '',
                    'to'    => '',
                    'label' => 'All time',
                );
        }
    }

    public static function get_period_options() {
        return array(
            'all'       => 'All time',
            '12m'       => 'Last 12 months',
            'last_year' => 'Last year (' . ( (int) gmdate( 'Y' ) - 1 ) . ')',
            'custom'    => 'Custom range',
        );
    }

    public static function get_base_user_query( $extra_where = '' ) {
        global $wpdb;

        $base_where = self::get_base_where();

        $sql = "SELECT u.ID, u.user_email,
                    fn.meta_value AS first_name,
                    ln.meta_value AS last_name,
                    ms.meta_value AS company
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                INNER JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id
                INNER JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id
                LEFT JOIN {$wpdb->usermeta} ms ON u.ID = ms.user_id AND ms.meta_key = 'msGraphCompanyName'
                WHERE {$base_where}";

        if ( ! empty( $extra_where ) ) {
            $sql .= ' ' . $extra_where;
        }

        $sql .= ' ORDER BY ln.meta_value ASC, fn.meta_value ASC';

        return $sql;
    }
}
