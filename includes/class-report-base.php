<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class ScaleAQ_Report_Base {

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
                    <input type="checkbox" data-saq-all>
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
     * Render inline JS for multi-select dropdowns (once per page).
     */
    public static function render_multiselect_js() {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        ?>
        <script>
        (function(){
            document.addEventListener('click',function(e){
                document.querySelectorAll('.saq-multiselect').forEach(function(ms){
                    if(!ms.contains(e.target)){
                        ms.classList.remove('saq-multiselect--open');
                        ms.querySelector('.saq-multiselect__toggle').setAttribute('aria-expanded','false');
                    }
                });
            });
            document.querySelectorAll('.saq-multiselect__toggle').forEach(function(btn){
                btn.addEventListener('click',function(e){
                    e.preventDefault();
                    var ms=this.closest('.saq-multiselect');
                    var open=ms.classList.toggle('saq-multiselect--open');
                    this.setAttribute('aria-expanded',open?'true':'false');
                });
            });
            document.querySelectorAll('[data-saq-all]').forEach(function(allCb){
                allCb.addEventListener('change',function(){
                    var dd=this.closest('.saq-multiselect__dropdown');
                    dd.querySelectorAll('input[type=checkbox]:not([data-saq-all])').forEach(function(cb){
                        cb.checked=allCb.checked;
                    });
                    updateLabel(this.closest('.saq-multiselect'));
                });
            });
            document.querySelectorAll('.saq-multiselect__dropdown input[type=checkbox]:not([data-saq-all])').forEach(function(cb){
                cb.addEventListener('change',function(){
                    var ms=this.closest('.saq-multiselect');
                    var dd=ms.querySelector('.saq-multiselect__dropdown');
                    var boxes=dd.querySelectorAll('input[type=checkbox]:not([data-saq-all])');
                    var allCb=dd.querySelector('[data-saq-all]');
                    if(allCb){
                        var allChecked=true;
                        boxes.forEach(function(b){if(!b.checked)allChecked=false;});
                        allCb.checked=allChecked;
                    }
                    updateLabel(ms);
                });
            });
            function updateLabel(ms){
                var checked=ms.querySelectorAll('.saq-multiselect__dropdown input[type=checkbox]:checked:not([data-saq-all])');
                var total=ms.querySelectorAll('.saq-multiselect__dropdown input[type=checkbox]:not([data-saq-all])');
                var txt=ms.querySelector('.saq-multiselect__text');
                if(checked.length===0||checked.length===total.length){
                    txt.textContent='All Companies';
                }else if(checked.length===1){
                    txt.textContent=checked[0].value;
                }else{
                    txt.textContent=checked.length+' companies selected';
                }
            }
        })();
        </script>
        <?php
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
