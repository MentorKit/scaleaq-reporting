<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class ScaleAQ_Report_Base {

    /** @var array<int, array<int>> Request cache for get_course_language_ids(). */
    private static $course_language_ids_cache = array();

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
            'hse' => array( 47052 ),
            'coc' => array( 47053 ),
            'it'  => array( 50348 ),
            'ai'  => array( 55110 ),
        );
    }

    public static function get_category_labels() {
        return array(
            'hse' => 'HSE',
            'coc' => 'CoC',
            'it'  => 'IT',
            'ai'  => 'AI',
        );
    }

    /**
     * Hardcoded Polylang translation groups (canonical course ID => all language post IDs).
     *
     * @return array<int, array<int>>
     */
    public static function get_course_translation_fallbacks() {
        return array(
            47052 => array( 46681, 47052, 47386 ),
            47053 => array( 46085, 47053, 47232 ),
            50348 => array( 50346, 50348, 52985 ),
            55110 => array( 55110 ),
        );
    }

    /**
     * All LearnDash course post IDs for a canonical course (Polylang + fallbacks).
     *
     * @param int $course_id Canonical course post ID.
     * @return array<int>
     */
    public static function get_course_language_ids( $course_id ) {
        $course_id = (int) $course_id;
        if ( $course_id <= 0 ) {
            return array();
        }

        if ( isset( self::$course_language_ids_cache[ $course_id ] ) ) {
            return self::$course_language_ids_cache[ $course_id ];
        }

        $ids = array();

        if ( function_exists( 'pll_get_post_translations' ) ) {
            $translations = pll_get_post_translations( $course_id );
            if ( is_array( $translations ) ) {
                foreach ( $translations as $post_id ) {
                    $post_id = (int) $post_id;
                    if ( $post_id <= 0 ) {
                        continue;
                    }
                    if ( get_post_type( $post_id ) !== 'sfwd-courses' ) {
                        continue;
                    }
                    if ( get_post_status( $post_id ) !== 'publish' ) {
                        continue;
                    }
                    $ids[] = $post_id;
                }
            }
        }

        $fallbacks = self::get_course_translation_fallbacks();
        if ( isset( $fallbacks[ $course_id ] ) ) {
            $ids = array_merge( $ids, $fallbacks[ $course_id ] );
        }

        if ( empty( $ids ) ) {
            $ids = array( $course_id );
        }

        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
        self::$course_language_ids_cache[ $course_id ] = $ids;

        return $ids;
    }

    /**
     * Sanitize a course ID against the allowed IDs for a category.
     *
     * @param mixed $raw         Raw GET value.
     * @param array $allowed_ids Course IDs belonging to the category.
     * @return int Course ID, or 0 for "all courses in category".
     */
    public static function sanitize_course_id( $raw, $allowed_ids ) {
        $id = absint( $raw );
        if ( $id <= 0 ) {
            return 0;
        }

        $allowed_ids = array_map( 'intval', $allowed_ids );
        if ( in_array( $id, $allowed_ids, true ) ) {
            return $id;
        }

        // Backward compatibility: accept legacy language-specific course IDs in URLs.
        foreach ( $allowed_ids as $canonical ) {
            $lang_ids = self::get_course_language_ids( $canonical );
            if ( in_array( $id, $lang_ids, true ) ) {
                return $canonical;
            }
        }

        return 0;
    }

    /**
     * Resolve which course IDs to query for a category (+ optional single course).
     *
     * @param string $cat       Category key.
     * @param int    $course_id Specific course ID (0 = all in category).
     * @return array List of course post IDs.
     */
    public static function resolve_course_ids( $cat, $course_id = 0 ) {
        $map       = self::get_course_ids_map();
        $canonical = isset( $map[ $cat ] ) ? array_map( 'intval', $map[ $cat ] ) : array();
        if ( empty( $canonical ) ) {
            return array();
        }

        if ( $course_id > 0 ) {
            $course_id = self::sanitize_course_id( $course_id, $canonical );
            if ( $course_id <= 0 ) {
                return array();
            }
            $canonical = array( $course_id );
        }

        $all_ids = array();
        foreach ( $canonical as $cid ) {
            $all_ids = array_merge( $all_ids, self::get_course_language_ids( $cid ) );
        }

        return array_values( array_unique( array_map( 'intval', $all_ids ) ) );
    }

    /**
     * Format Polylang language slug for display (NO / EN / ES).
     *
     * @param int $post_id LearnDash course post ID used for completion.
     * @return string Uppercase language code, or empty string.
     */
    public static function format_completion_language( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return '';
        }

        if ( function_exists( 'pll_get_post_language' ) ) {
            $slug = pll_get_post_language( $post_id, 'slug' );
            if ( is_string( $slug ) && $slug !== '' ) {
                return strtoupper( $slug );
            }
        }

        return '';
    }

    /**
     * Fetch latest course completion per user (timestamp + post_id for language).
     *
     * @param array  $course_ids LearnDash course post IDs (all language variants).
     * @param string $to         Cutoff date YYYY-MM-DD, or empty for all time.
     * @return array<int, array{ts: int, post_id: int, lang: string}> user_id => completion data.
     */
    public static function fetch_user_completions( $course_ids, $to = '' ) {
        global $wpdb;

        $course_ids = array_values( array_unique( array_map( 'intval', $course_ids ) ) );
        if ( empty( $course_ids ) ) {
            return array();
        }

        $ts_col       = self::detect_timestamp_column();
        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

        $inner_sql = "SELECT user_id, MAX(`{$ts_col}`) AS max_ts
            FROM {$wpdb->prefix}learndash_user_activity
            WHERE activity_type = 'course'
                AND activity_status = 1
                AND post_id IN ({$placeholders})";

        $prepare_args = $course_ids;

        if ( $to !== '' ) {
            $to_ts        = strtotime( $to . ' 23:59:59' );
            $inner_sql   .= $wpdb->prepare( " AND `{$ts_col}` <= %d", $to_ts );
        }

        $inner_sql .= ' GROUP BY user_id';

        $activity_sql = "SELECT a.user_id, a.post_id, a.`{$ts_col}` AS completed_ts
            FROM {$wpdb->prefix}learndash_user_activity a
            INNER JOIN ({$inner_sql}) m
                ON a.user_id = m.user_id AND a.`{$ts_col}` = m.max_ts
            WHERE a.activity_type = 'course'
                AND a.activity_status = 1
                AND a.post_id IN ({$placeholders})";

        $prepare_args = array_merge( $prepare_args, $course_ids );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $activity_sql, $prepare_args ) );

        $completed_set = array();
        foreach ( $rows as $row ) {
            $uid = (int) $row->user_id;
            if ( isset( $completed_set[ $uid ] ) ) {
                continue;
            }
            $post_id = (int) $row->post_id;
            $completed_set[ $uid ] = array(
                'ts'      => (int) $row->completed_ts,
                'post_id' => $post_id,
                'lang'    => self::format_completion_language( $post_id ),
            );
        }

        return $completed_set;
    }

    /**
     * Fetch display titles for course IDs (LearnDash posts).
     *
     * @param array $course_ids Course post IDs.
     * @return array Map of course_id => title.
     */
    public static function get_course_titles( $course_ids ) {
        $titles = array();
        foreach ( $course_ids as $id ) {
            $id    = (int) $id;
            $title = get_the_title( $id );
            $titles[ $id ] = ( $title !== '' ) ? $title : ( 'Course #' . $id );
        }
        return $titles;
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
     * Resolve a period preset to a cumulative cutoff date.
     *
     * @param string $period  One of: all, 2025, 2024, custom.
     * @param string $to      Custom cutoff date (only used when period=custom).
     * @return array { 'to' => string, 'label' => string }
     */
    public static function resolve_period( $period, $to = '' ) {
        switch ( $period ) {
            case '2025':
                return array(
                    'to'    => '2025-12-31',
                    'label' => 'By 31.12.2025',
                );
            case '2024':
                return array(
                    'to'    => '2024-12-31',
                    'label' => 'By 31.12.2024',
                );
            case 'custom':
                $label = $to !== '' ? 'By ' . $to : 'Custom cutoff date';
                return array(
                    'to'    => $to,
                    'label' => $label,
                );
            default: // 'all'
                return array(
                    'to'    => '',
                    'label' => 'All time',
                );
        }
    }

    /**
     * Sanitize company filter input (backward-compatible with string or array).
     *
     * @param mixed $raw  String (v1.2 compat) or array of company names.
     * @return array Sanitized company names (empty = all companies).
     */
    public static function sanitize_companies( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = $raw !== '' ? array( $raw ) : array();
        }
        if ( ! is_array( $raw ) ) {
            return array();
        }
        $clean = array();
        foreach ( $raw as $val ) {
            $val = sanitize_text_field( $val );
            if ( $val !== '' ) {
                $clean[] = $val;
            }
        }
        return array_unique( $clean );
    }

    /**
     * Build SQL WHERE clause for multi-company filtering.
     *
     * @param array $companies Sanitized company names.
     * @return string SQL fragment (empty string if no filter).
     */
    public static function build_company_where( $companies ) {
        global $wpdb;
        if ( empty( $companies ) ) {
            return '';
        }
        $placeholders = implode( ',', array_fill( 0, count( $companies ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->prepare( "AND ms.meta_value IN ({$placeholders})", $companies );
    }

    /**
     * Render a multi-select checkbox dropdown.
     *
     * @param string $name       Input name (e.g. 'cr_company').
     * @param array  $options    Available company names.
     * @param array  $selected   Currently selected company names.
     */
    public static function render_multiselect( $name, $options, $selected ) {
        $count    = count( $selected );
        $total    = count( $options );
        $all      = $count === 0 || $count === $total;
        $btn_text = 'All Companies';
        if ( $count === 1 ) {
            $btn_text = $selected[0];
        } elseif ( $count > 1 && ! $all ) {
            $btn_text = $count . ' companies selected';
        }
        ?>
        <div class="saq-multiselect" data-saq-ms>
            <button type="button" class="saq-multiselect__toggle" aria-expanded="false">
                <span class="saq-multiselect__text"><?php echo esc_html( $btn_text ); ?></span>
                <svg class="saq-multiselect__chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="saq-multiselect__dropdown">
                <label class="saq-multiselect__option saq-multiselect__option--all">
                    <input type="checkbox" data-saq-all<?php echo ( $count > 0 && $count === $total ) ? ' checked' : ''; ?>>
                    <span>Select All</span>
                </label>
                <?php foreach ( $options as $c ) : ?>
                <label class="saq-multiselect__option">
                    <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $c ); ?>"<?php echo in_array( $c, $selected, true ) ? ' checked' : ''; ?>>
                    <span><?php echo esc_html( $c ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue the reports JS (once per page).
     */
    public static function enqueue_reports_js() {
        wp_enqueue_script(
            'scaleaq-reports',
            SCALEAQ_REPORTING_URL . 'assets/js/reports.js',
            array(),
            SCALEAQ_REPORTING_VER,
            true
        );
    }

    public static function get_period_options() {
        return array(
            'all'    => 'All time',
            '2025'   => 'By end of 2025',
            '2024'   => 'By end of 2024',
            'custom' => 'Custom cutoff date',
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
