<?php
/**
 * Plugin Name: ScaleAQ Reporting
 * Description: Reporting plugin for ScaleAQ Academy (LearnDash LMS) — course completion and user reports.
 * Version: 1.3.0
 * Author: MentorKit
 * Requires PHP: 8.0
 * License: Proprietary
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCALEAQ_REPORTING_URL', plugin_dir_url( __FILE__ ) );
define( 'SCALEAQ_REPORTING_VER', '1.3.0' );

require_once __DIR__ . '/includes/class-report-base.php';
require_once __DIR__ . '/includes/class-course-report.php';
require_once __DIR__ . '/includes/class-user-report.php';

add_action( 'wp_enqueue_scripts', function () {
    wp_register_style(
        'scaleaq-reports',
        SCALEAQ_REPORTING_URL . 'assets/css/reports.css',
        array(),
        SCALEAQ_REPORTING_VER
    );
} );

add_shortcode( 'scaleaq_course_report', array( 'ScaleAQ_Course_Report', 'render' ) );
add_shortcode( 'scaleaq_user_report', array( 'ScaleAQ_User_Report', 'render' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once __DIR__ . '/includes/class-cli-seed.php';
    WP_CLI::add_command( 'scaleaq seed', 'ScaleAQ_CLI_Seed' );
}
