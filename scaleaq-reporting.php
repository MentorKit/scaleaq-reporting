<?php
/**
 * Plugin Name: ScaleAQ Reporting
 * Description: Reporting plugin for ScaleAQ Academy (LearnDash LMS) — course completion and user reports.
 * Version: 1.0.0
 * Author: MentorKit
 * Requires PHP: 8.0
 * License: Proprietary
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-report-base.php';
require_once __DIR__ . '/includes/class-course-report.php';
require_once __DIR__ . '/includes/class-user-report.php';

add_shortcode( 'scaleaq_course_report', array( 'ScaleAQ_Course_Report', 'render' ) );
add_shortcode( 'my_course_report_form', array( 'ScaleAQ_Course_Report', 'render' ) );
add_shortcode( 'scaleaq_user_report', array( 'ScaleAQ_User_Report', 'render' ) );
add_shortcode( 'simple_user_report', array( 'ScaleAQ_User_Report', 'render' ) );
