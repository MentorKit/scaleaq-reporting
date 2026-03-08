<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScaleAQ_CLI_Seed {

    private const SEED_META_KEY = '_scaleaq_seed_user';
    private const USER_COUNT    = 50;

    private static function get_course_posts(): array {
        return array(
            46681 => 'HSE Onboarding',
            47052 => 'HSE Safety Procedures',
            47386 => 'HSE Emergency Response',
            47232 => 'CoC Professional Conduct',
            46085 => 'CoC Compliance Basics',
            47053 => 'CoC Ethical Guidelines',
            50346 => 'IT Security Awareness',
            50348 => 'IT Systems Training',
        );
    }

    private static function get_first_names(): array {
        return array(
            'Anders', 'Bjørn', 'Erik', 'Fredrik', 'Geir', 'Håkon', 'Ivar', 'Jan',
            'Knut', 'Lars', 'Magnus', 'Nils', 'Olav', 'Per', 'Rune', 'Sigurd',
            'Terje', 'Ulf', 'Vidar', 'Øystein', 'Anne', 'Berit', 'Cecilie', 'Dagny',
            'Elin', 'Frida', 'Grete', 'Hilde', 'Ingrid', 'Johanne', 'Kari', 'Linn',
            'Marit', 'Nina', 'Oddny', 'Petra', 'Ragnhild', 'Silje', 'Tonje', 'Unni',
            'Vibeke', 'Wenche', 'Astrid', 'Bente', 'Dorthe', 'Eva', 'Gunhild', 'Helene',
            'Kristin', 'Marte',
        );
    }

    private static function get_last_names(): array {
        return array(
            'Hansen', 'Johansen', 'Olsen', 'Larsen', 'Andersen', 'Pedersen', 'Nilsen',
            'Kristiansen', 'Jensen', 'Karlsen', 'Johnsen', 'Pettersen', 'Eriksen',
            'Berg', 'Haugen', 'Hagen', 'Bakken', 'Lie', 'Dahl', 'Lund', 'Moen',
            'Solberg', 'Strand', 'Vik', 'Nordby', 'Fjeld', 'Sørensen', 'Hauge',
            'Gulbrandsen', 'Henriksen', 'Bråthen', 'Thorsen', 'Aasen', 'Berge',
            'Lien', 'Myhre', 'Ruud', 'Bakke', 'Nygård', 'Ellingsen', 'Gjerde',
            'Holmberg', 'Kvalheim', 'Rønning', 'Sandvik', 'Tangen', 'Vestby',
            'Aune', 'Brekke', 'Hegge',
        );
    }

    private static function get_company_distribution(): array {
        return array_merge(
            array_fill( 0, 15, 'ScaleAQ AS' ),
            array_fill( 0, 15, 'Moen Marin AS' ),
            array_fill( 0, 10, 'AquaTech Solutions' ),
            array_fill( 0, 5, 'Nordic Fish Farms' ),
            array_fill( 0, 5, '' ),
        );
    }

    /**
     * Main seed command.
     *
     * ## OPTIONS
     *
     * [--reset]
     * : Delete all seed data before regenerating.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'reset', false ) ) {
            $this->reset();
        }

        $this->seed_courses();
        $user_ids = $this->seed_users();
        $this->seed_activity( $user_ids );

        WP_CLI::success( sprintf( 'Seeded %d users with LearnDash activity.', count( $user_ids ) ) );
    }

    private function reset(): void {
        global $wpdb;

        $seed_user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
                self::SEED_META_KEY
            )
        );

        if ( empty( $seed_user_ids ) ) {
            WP_CLI::log( 'No seed users found to remove.' );
            return;
        }

        $id_placeholders = implode( ',', array_fill( 0, count( $seed_user_ids ), '%d' ) );

        // Delete LearnDash activity rows.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}learndash_user_activity WHERE user_id IN ($id_placeholders)",
                ...$seed_user_ids
            )
        );

        // Delete users (cascades usermeta via wp_delete_user).
        foreach ( $seed_user_ids as $uid ) {
            wp_delete_user( (int) $uid );
        }

        WP_CLI::log( sprintf( 'Reset: removed %d seed users and their activity.', count( $seed_user_ids ) ) );
    }

    private function seed_courses(): void {
        global $wpdb;

        foreach ( self::get_course_posts() as $post_id => $title ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id )
            );

            if ( $exists ) {
                WP_CLI::log( sprintf( 'Course %d already exists, skipping.', $post_id ) );
                continue;
            }

            $wpdb->insert(
                $wpdb->posts,
                array(
                    'ID'           => $post_id,
                    'post_author'  => 1,
                    'post_title'   => $title,
                    'post_name'    => sanitize_title( $title ),
                    'post_status'  => 'publish',
                    'post_type'    => 'sfwd-courses',
                    'post_date'    => current_time( 'mysql' ),
                    'post_date_gmt' => current_time( 'mysql', true ),
                    'post_modified' => current_time( 'mysql' ),
                    'post_modified_gmt' => current_time( 'mysql', true ),
                    'post_content' => '',
                    'post_excerpt' => '',
                    'to_ping'      => '',
                    'pinged'       => '',
                    'post_content_filtered' => '',
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            WP_CLI::log( sprintf( 'Created course: %s (ID %d)', $title, $post_id ) );
        }
    }

    private function seed_users(): array {
        $first_names = self::get_first_names();
        $last_names  = self::get_last_names();
        $companies   = self::get_company_distribution();

        shuffle( $first_names );
        shuffle( $last_names );
        shuffle( $companies );

        $user_ids = array();

        for ( $i = 1; $i <= self::USER_COUNT; $i++ ) {
            $first = $first_names[ ( $i - 1 ) % count( $first_names ) ];
            $last  = $last_names[ ( $i - 1 ) % count( $last_names ) ];

            $company = $companies[ ( $i - 1 ) % count( $companies ) ];
            $domain  = match ( $company ) {
                'ScaleAQ AS'       => 'scaleaq.com',
                'Moen Marin AS'    => 'moenmarin.no',
                'Nordic Fish Farms' => 'maskon.no',
                default             => 'scaleaq.academy',
            };

            $email_local = strtolower(
                preg_replace( '/[^a-zA-Z]/', '', $first ) . '.' . preg_replace( '/[^a-zA-Z]/', '', $last )
            );
            $login = $email_local . '_' . $i;

            $existing = username_exists( $login );
            if ( $existing ) {
                WP_CLI::log( sprintf( 'User %s already exists (ID %d), skipping.', $login, $existing ) );
                $user_ids[] = $existing;
                continue;
            }

            $user_id = wp_insert_user( array(
                'user_login' => $login,
                'user_email' => sprintf( '%s@%s', $email_local, $domain ),
                'user_pass'  => wp_generate_password( 16 ),
                'first_name' => $first,
                'last_name'  => $last,
                'role'       => 'subscriber',
            ) );

            if ( is_wp_error( $user_id ) ) {
                WP_CLI::warning( sprintf( 'Failed to create %s: %s', $login, $user_id->get_error_message() ) );
                continue;
            }

            if ( $company !== '' ) {
                update_user_meta( $user_id, 'msGraphCompanyName', $company );
            }
            update_user_meta( $user_id, self::SEED_META_KEY, '1' );

            $user_ids[] = $user_id;
        }

        WP_CLI::log( sprintf( 'Created %d users.', count( $user_ids ) ) );
        return $user_ids;
    }

    private function ensure_activity_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'learndash_user_activity';
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

        if ( $exists ) {
            return;
        }

        $charset = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE {$table} (
                activity_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                activity_type VARCHAR(50) NOT NULL DEFAULT '',
                activity_status TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                activity_started INT(11) UNSIGNED NOT NULL DEFAULT 0,
                activity_completed INT(11) UNSIGNED NOT NULL DEFAULT 0,
                activity_updated INT(11) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (activity_id),
                KEY user_id (user_id),
                KEY post_id (post_id),
                KEY activity_status (activity_status)
            ) {$charset}"
        );

        WP_CLI::log( 'Created learndash_user_activity table (LearnDash not installed).' );
    }

    private function seed_activity( array $user_ids ): void {
        global $wpdb;

        $this->ensure_activity_table();

        $course_map    = ScaleAQ_Report_Base::get_course_ids_map();
        $activity_table = $wpdb->prefix . 'learndash_user_activity';
        $count         = 0;
        $now           = time();
        $six_months_ago = $now - ( 180 * DAY_IN_SECONDS );

        foreach ( $user_ids as $user_id ) {
            foreach ( $course_map as $category => $course_ids ) {
                // Decide completion pattern for this category:
                // 40% complete all, 25% complete none, 35% complete partial.
                $rand = wp_rand( 1, 100 );

                if ( $rand <= 40 ) {
                    $courses_to_complete = $course_ids;
                } elseif ( $rand <= 65 ) {
                    $courses_to_complete = array();
                } else {
                    $partial_count = wp_rand( 1, count( $course_ids ) - 1 );
                    shuffle( $course_ids );
                    $courses_to_complete = array_slice( $course_ids, 0, $partial_count );
                }

                foreach ( $courses_to_complete as $course_id ) {
                    $started   = wp_rand( $six_months_ago, $now );
                    $duration  = wp_rand( 1, 30 ) * DAY_IN_SECONDS;
                    $completed = min( $started + $duration, $now );

                    $wpdb->insert(
                        $activity_table,
                        array(
                            'user_id'            => $user_id,
                            'post_id'            => $course_id,
                            'activity_type'      => 'course',
                            'activity_status'    => 1,
                            'activity_started'   => $started,
                            'activity_completed' => $completed,
                            'activity_updated'   => $completed,
                        ),
                        array( '%d', '%d', '%s', '%d', '%d', '%d', '%d' )
                    );

                    $count++;
                }
            }
        }

        WP_CLI::log( sprintf( 'Created %d activity rows.', $count ) );
    }
}
