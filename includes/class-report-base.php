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

    /**
     * Allowed email domain substrings for eligible users (shared by SQL filter + scope text).
     *
     * @return array<string>
     */
    public static function get_allowed_email_domains() {
        return array(
            'scaleaq.com',
            'moenmarin.no',
            'maskon.no',
            'scaleaq.academy',
            'pmh.no',
            'probotic.no',
        );
    }

    /**
     * Human-readable scope sentence fragment listing allowed domains.
     *
     * @return string e.g. "scaleaq.com, moenmarin.no, maskon.no or scaleaq.academy"
     */
    public static function format_allowed_domains_phrase() {
        $domains = self::get_allowed_email_domains();
        $n       = count( $domains );
        if ( $n === 0 ) {
            return '';
        }
        if ( $n === 1 ) {
            return $domains[0];
        }
        $last = array_pop( $domains );
        return implode( ', ', $domains ) . ' or ' . $last;
    }

    /**
     * Scope line under stat cards (count + domain list from get_allowed_email_domains()).
     *
     * @param int $scope_total Eligible user count in the current filter.
     * @return string
     */
    public static function format_scope_line( $scope_total ) {
        return sprintf(
            'Of %d employees in scope (subscribers with an email at %s; test and service accounts excluded).',
            (int) $scope_total,
            self::format_allowed_domains_phrase()
        );
    }

    public static function get_base_where() {
        $domain_clauses = array();
        foreach ( self::get_allowed_email_domains() as $domain ) {
            $domain_clauses[] = "u.user_email LIKE '%" . esc_sql( $domain ) . "%'";
        }
        $domain_sql = implode( "\n                OR ", $domain_clauses );

        return "um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%\"subscriber\"%'
            AND fn.meta_key = 'first_name' AND fn.meta_value != ''
            AND ln.meta_key = 'last_name' AND ln.meta_value != ''
            AND (
                {$domain_sql}
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
        if ( stripos( $company_name, 'Maskon' ) !== false ) {
            return 'Maskon';
        }
        if ( stripos( $company_name, 'Probotic' ) !== false ) {
            return 'Probotic';
        }
        if ( stripos( $company_name, 'PMH' ) !== false ) {
            return 'PMH';
        }
        if (
            stripos( $company_name, 'ScaleAQ' ) !== false
            || stripos( $company_name, 'SCALE AQUACULTURE' ) !== false
        ) {
            return 'ScaleAQ Group';
        }
        return 'Other';
    }

    /**
     * Ordered group labels for report tables.
     *
     * @return array<string>
     */
    public static function get_group_labels_ordered() {
        return array( 'ScaleAQ Group', 'Moen Marin AS', 'Maskon', 'Probotic', 'PMH', 'Other' );
    }

    /**
     * Display label for an empty company name.
     *
     * @param string $company_name Raw company meta.
     * @return string
     */
    public static function format_company_name( $company_name ) {
        $company_name = trim( (string) $company_name );
        return $company_name !== '' ? $company_name : '(no company)';
    }

    /**
     * Shared metric labels and help text (UI, tables, CSV).
     *
     * @return array<string, array{label: string, help: string}>
     */
    public static function get_metric_definitions() {
        return array(
            'enrolled'             => array(
                'label' => 'Enrolled',
                'help'  => 'Has access to the course in at least one language, through a group or direct enrollment',
            ),
            'not_started'          => array(
                'label' => 'Not started',
                'help'  => 'Enrolled, but has no LearnDash activity for this course yet',
            ),
            'in_progress'          => array(
                'label' => 'In progress',
                'help'  => 'Has course activity (lesson, topic, quiz or access), but not finished',
            ),
            'completed'            => array(
                'label' => 'Completed',
                'help'  => 'Finished the course in at least one language (counted once per person)',
            ),
            'completion_rate'      => array(
                'label' => 'Completion rate',
                'help'  => 'Completed ÷ Enrolled',
            ),
            'completion_rate_started' => array(
                'label' => 'Completion rate (started)',
                'help'  => 'Completed ÷ Started: how many of those who begin, finish',
            ),
            'started'              => array(
                'label' => 'Started',
                'help'  => 'In progress + Completed (any activity with course_id = language variant)',
            ),
        );
    }

    /**
     * Per-user status labels for CSV / tables.
     *
     * @return array<string, string>
     */
    public static function get_status_labels() {
        return array(
            'not_started' => 'Not started',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'not_enrolled' => 'Not enrolled',
        );
    }

    /**
     * Resolve a user's funnel status.
     *
     * @param bool $enrolled  Has course access.
     * @param bool $started   Has any course activity row.
     * @param bool $completed Has completed (within period).
     * @return string Status key.
     */
    public static function resolve_user_status( $enrolled, $started, $completed ) {
        if ( ! $enrolled ) {
            return 'not_enrolled';
        }
        if ( $completed ) {
            return 'completed';
        }
        if ( $started ) {
            return 'in_progress';
        }
        return 'not_started';
    }

    /**
     * Language codes (NO / EN / ES) for course post IDs, stable order.
     *
     * @param array $course_ids Course post IDs.
     * @return array<string>
     */
    public static function get_language_codes_for_courses( $course_ids ) {
        $order = array( 'NO' => 0, 'EN' => 1, 'ES' => 2 );
        $found = array();
        foreach ( array_map( 'intval', $course_ids ) as $cid ) {
            $code = self::format_completion_language( $cid );
            if ( $code !== '' ) {
                $found[ $code ] = true;
            }
        }
        $codes = array_keys( $found );
        usort(
            $codes,
            function ( $a, $b ) use ( $order ) {
                $oa = $order[ $a ] ?? 99;
                $ob = $order[ $b ] ?? 99;
                return $oa <=> $ob;
            }
        );
        return $codes;
    }

    /**
     * Subtitle: course title(s) · language codes.
     *
     * @param array $canonical_ids Canonical course IDs in selection.
     * @param array $all_ids       All language variant IDs.
     * @return string
     */
    public static function format_report_subtitle( $canonical_ids, $all_ids ) {
        $titles = self::get_course_titles( $canonical_ids );
        $title_parts = array();
        foreach ( $canonical_ids as $cid ) {
            $title_parts[] = $titles[ (int) $cid ] ?? ( 'Course #' . (int) $cid );
        }
        $langs = self::get_language_codes_for_courses( $all_ids );
        $left  = implode( ' · ', $title_parts );
        if ( empty( $langs ) ) {
            return $left;
        }
        return $left . ' · ' . implode( ' / ', $langs );
    }

    /**
     * Whether a LearnDash course is open (anyone can access).
     *
     * @param int $course_id Course post ID.
     * @return bool
     */
    public static function is_course_open( $course_id ) {
        $course_id = (int) $course_id;
        if ( $course_id <= 0 ) {
            return false;
        }

        if ( function_exists( 'learndash_get_setting' ) ) {
            $type = learndash_get_setting( $course_id, 'course_price_type' );
            return is_string( $type ) && strtolower( $type ) === 'open';
        }

        $meta = get_post_meta( $course_id, '_sfwd-courses', true );
        if ( is_array( $meta ) && ! empty( $meta['sfwd-courses_course_price_type'] ) ) {
            return strtolower( (string) $meta['sfwd-courses_course_price_type'] ) === 'open';
        }

        return false;
    }

    /**
     * Collect LearnDash group IDs linked to any of the given courses.
     *
     * @param array $course_ids Course post IDs.
     * @return array<int>
     */
    public static function get_groups_for_courses( $course_ids ) {
        $groups = array();
        foreach ( array_map( 'intval', $course_ids ) as $course_id ) {
            if ( $course_id <= 0 ) {
                continue;
            }
            if ( function_exists( 'learndash_get_course_groups' ) ) {
                $course_groups = learndash_get_course_groups( $course_id );
                if ( is_array( $course_groups ) ) {
                    foreach ( $course_groups as $gid ) {
                        $gid = (int) $gid;
                        if ( $gid > 0 ) {
                            $groups[] = $gid;
                        }
                    }
                }
            }
        }
        return array_values( array_unique( $groups ) );
    }

    /**
     * Eligible users (get_base_where) enrolled in at least one of the course IDs.
     *
     * Enrolled = LearnDash group membership, direct course_{id}_access_from, or open course.
     * One SQL query (plus lightweight group/open lookups) — not sfwd_lms_has_access() per user.
     *
     * @param array $course_ids Language variant course post IDs.
     * @return array<int, true> user_id => true
     */
    public static function fetch_enrolled_user_ids( $course_ids ) {
        global $wpdb;

        $course_ids = array_values( array_unique( array_map( 'intval', $course_ids ) ) );
        if ( empty( $course_ids ) ) {
            return array();
        }

        $base_where = self::get_base_where();

        foreach ( $course_ids as $cid ) {
            if ( self::is_course_open( $cid ) ) {
                $ids = $wpdb->get_col(
                    "SELECT DISTINCT u.ID
                    FROM {$wpdb->users} u
                    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                    INNER JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id
                    INNER JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id
                    WHERE {$base_where}"
                );
                $set = array();
                foreach ( $ids as $id ) {
                    $set[ (int) $id ] = true;
                }
                return $set;
            }
        }

        $meta_keys = array();
        foreach ( $course_ids as $cid ) {
            $meta_keys[] = 'course_' . $cid . '_access_from';
        }
        foreach ( self::get_groups_for_courses( $course_ids ) as $gid ) {
            $meta_keys[] = 'learndash_group_users_' . $gid;
        }
        $meta_keys = array_values( array_unique( $meta_keys ) );
        if ( empty( $meta_keys ) ) {
            return array();
        }

        // Escape keys individually — do not $wpdb->prepare() the full SQL:
        // get_base_where() contains LIKE '%...%' which prepare would treat as placeholders.
        $escaped_keys = array();
        foreach ( $meta_keys as $key ) {
            $escaped_keys[] = $wpdb->prepare( '%s', $key );
        }
        $in_list = implode( ',', $escaped_keys );

        $sql = "SELECT DISTINCT u.ID
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            INNER JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id
            INNER JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id
            INNER JOIN {$wpdb->usermeta} acc ON u.ID = acc.user_id AND acc.meta_key IN ({$in_list})
            WHERE {$base_where}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $ids = $wpdb->get_col( $sql );
        $set = array();
        foreach ( $ids as $id ) {
            $set[ (int) $id ] = true;
        }
        return $set;
    }

    /**
     * Users with any LearnDash activity for the given courses (ProPanel-aligned).
     *
     * Matches DISTINCT user_id WHERE course_id IN (...), any activity_type
     * (course / lesson / topic / quiz / access) — not only activity_type=course.
     * Language comes from the course_id of the latest activity row.
     *
     * @param array $course_ids Language-variant course post IDs.
     * @return array<int, array{post_id: int, lang: string}> user_id => data
     */
    public static function fetch_started_user_activity( $course_ids ) {
        global $wpdb;

        $course_ids = array_values( array_unique( array_map( 'intval', $course_ids ) ) );
        if ( empty( $course_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

        // Prefer completed, then updated/started — activity_completed is often NULL while in progress.
        $ts_expr = 'COALESCE(NULLIF(a.activity_completed, 0), NULLIF(a.activity_updated, 0), NULLIF(a.activity_started, 0), 0)';

        $inner_sql = "SELECT user_id, MAX(COALESCE(NULLIF(activity_completed, 0), NULLIF(activity_updated, 0), NULLIF(activity_started, 0), 0)) AS max_ts
            FROM {$wpdb->prefix}learndash_user_activity
            WHERE course_id IN ({$placeholders})
            GROUP BY user_id";

        $sql = "SELECT a.user_id, a.course_id AS lang_course_id
            FROM {$wpdb->prefix}learndash_user_activity a
            INNER JOIN ({$inner_sql}) m
                ON a.user_id = m.user_id AND {$ts_expr} = m.max_ts
            WHERE a.course_id IN ({$placeholders})";

        $prepare_args = array_merge( $course_ids, $course_ids );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_args ) );

        $set = array();
        foreach ( $rows as $row ) {
            $uid = (int) $row->user_id;
            if ( isset( $set[ $uid ] ) ) {
                continue;
            }
            $lang_course_id = (int) $row->lang_course_id;
            $set[ $uid ]    = array(
                'post_id' => $lang_course_id,
                'lang'    => self::format_completion_language( $lang_course_id ),
            );
        }
        return $set;
    }

    /**
     * Users with any course activity (set form).
     *
     * @param array $course_ids Course post IDs.
     * @return array<int, true>
     */
    public static function fetch_started_user_ids( $course_ids ) {
        $activity = self::fetch_started_user_activity( $course_ids );
        $set      = array();
        foreach ( $activity as $uid => $_ ) {
            $set[ (int) $uid ] = true;
        }
        return $set;
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
