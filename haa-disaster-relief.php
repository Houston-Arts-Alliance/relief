<?php
/**
 * Plugin Name: HAA Disaster Relief System
 * Plugin URI:  https://github.com/haatx/disaster-relief
 * Description: multi-step disaster relief application form for the haa SOAR program. use shortcode [haa_disaster_relief] on any page. critical infrastructure for the disaster relief application. don't deactivate, delete, or modify without first consulting the HAA Senior Data Analytics Manager. changes will break live applicant intake and scoring.
 * Version:     1.7.4
 * Author:      S.R. HAA's Senior Data Analytics Manager; contact me before any changes)
 * License:     GPL-2.0+
 * Text Domain: haa-drs
 *
 * Changelog:
 *   1.7.4 was design update round, no logic or schema changes; hero lockup of SOAR and "Supporting Our Arts Recovery" split by vert line, wrapping not overflowing on littler screens. redundant prog bar removed (four step tabs already make this obv); step nav got an underline concept with circle number badges outlined; navy with gold numbers active stacking vert under 600px; header negative margin and side padding out to align with content column plus fixes a 4px horiz overflow on mobile; Back and Continue buttons wider to 180px minimum; required asterisk added to SVI heading; aria-current="step" now tracking active tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HAA_DRS_VERSION', '1.7.4' );
define( 'HAA_DRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAA_DRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HAA_DRS_TABLE', 'haa_drs_submissions' );

define( 'HAA_DRS_PROTECTED_USER', 'Shareef' );

require_once HAA_DRS_PLUGIN_DIR . 'includes/class-database.php';
require_once HAA_DRS_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once HAA_DRS_PLUGIN_DIR . 'includes/class-admin.php';
require_once HAA_DRS_PLUGIN_DIR . 'includes/class-export.php';

register_activation_hook( __FILE__, [ 'HAA_DRS_Database', 'activate' ] );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $user = wp_get_current_user();
    if ( $user && $user->user_login === HAA_DRS_PROTECTED_USER ) {
        return $links;
    }
    unset( $links['deactivate'], $links['delete'] );
    return $links;
} );

add_action( 'plugins_loaded', function () {
    $stored = get_option( 'haa_drs_db_version', '1.0.0' );
    global $wpdb;
    $table = $wpdb->prefix . HAA_DRS_TABLE;

    if ( version_compare( $stored, '1.1.0', '<' ) ) {
        $wpdb->query(
            "UPDATE `{$table}` SET priority_tier = REPLACE( priority_tier, ' — ', ': ' )
             WHERE priority_tier LIKE '%—%'"
        );
        $stored = '1.1.0';
        update_option( 'haa_drs_db_version', $stored );
    }

    if ( version_compare( $stored, '1.2.0', '<' ) ) {
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'disaster_event'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN disaster_event VARCHAR(255) NOT NULL DEFAULT '' AFTER svi_score" );
        }
        update_option( 'haa_drs_db_version', '1.2.0' );
    }

    if ( version_compare( $stored, '1.3.0', '<' ) ) {
        // first_name
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'first_name'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN first_name VARCHAR(255) NOT NULL DEFAULT '' AFTER priority_tier" );
        }
        // last_name
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'last_name'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN last_name VARCHAR(255) NOT NULL DEFAULT '' AFTER first_name" );
        }
        // race_ethnicity
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'race_ethnicity'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN race_ethnicity VARCHAR(100) NOT NULL DEFAULT '' AFTER artistic_discipline" );
        }
        // race_ethnicity_other
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'race_ethnicity_other'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN race_ethnicity_other VARCHAR(255) NOT NULL DEFAULT '' AFTER race_ethnicity" );
        }

        $wpdb->query(
            "UPDATE `{$table}`
             SET first_name = SUBSTRING_INDEX(full_name, ' ', 1),
                 last_name  = TRIM(SUBSTR(full_name, LOCATE(' ', full_name) + 1))
             WHERE full_name != '' AND first_name = ''"
        );

        update_option( 'haa_drs_db_version', '1.3.0' );
    }

    if ( version_compare( $stored, '1.4.0', '<' ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN race_ethnicity TEXT NOT NULL" );
        update_option( 'haa_drs_db_version', '1.4.0' );
    }

    if ( version_compare( $stored, '1.4.1', '<' ) ) {
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'current_needs_other'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN current_needs_other VARCHAR(255) NOT NULL DEFAULT '' AFTER current_needs" );
        }
        update_option( 'haa_drs_db_version', '1.4.1' );
    }

    if ( version_compare( $stored, '1.7.0', '<' ) ) {
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'household_income'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN household_income VARCHAR(32) NOT NULL DEFAULT '' AFTER children_count" );
        }
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'vulnerability_factors_other'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN vulnerability_factors_other VARCHAR(255) NOT NULL DEFAULT '' AFTER vulnerability_factors" );
        }
        update_option( 'haa_drs_db_version', '1.7.0' );
    }
}, 5 );  // priority 5 = runs before admin init at default 10

add_action( 'init', function () {
    HAA_DRS_Form_Handler::init();
} );

add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        HAA_DRS_Admin::init();
        HAA_DRS_Export::init();
    }
} );

add_action( 'admin_menu', function () {
    add_menu_page(
        __( 'SOAR Applications', 'haa-drs' ),
        __( 'SOAR Applications', 'haa-drs' ),
        'edit_posts',
        'haa-drs',
        [ 'HAA_DRS_Admin', 'render_page' ],
        'dashicons-heart',
        30
    );

    add_submenu_page(
        'haa-drs',
        __( 'All Applications', 'haa-drs' ),
        __( 'All Applications', 'haa-drs' ),
        'edit_posts',
        'haa-drs',
        [ 'HAA_DRS_Admin', 'render_page' ]
    );

    add_submenu_page(
        'haa-drs',
        __( 'Settings', 'haa-drs' ),
        __( 'Settings', 'haa-drs' ),
        'manage_options',
        'haa-drs-settings',
        [ 'HAA_DRS_Admin', 'render_settings_page' ]
    );
} );

register_activation_hook( __FILE__, function () {
    $defaults = [
        'haa_drs_notify_email'  => get_option( 'admin_email' ),
        'haa_drs_webhook_url'   => '',
        'haa_drs_hud_api_token' => '',
        'haa_drs_rate_limit'    => 15, // minutes
        'haa_drs_current_event' => '',
    ];
    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }
} );
