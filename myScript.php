<?php
/**
 * MU Plugin - Fix Automation for Multisite
 *
 * Plugin Name: MU Fix Automation (Multisite Support)
 * Description: Detects and disables problematic plugins, optimizes the database, and prevents overload in a WordPress Multisite environment.
 * Version: 1.2
 * Author: WP Admin
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Detect and disable problematic plugins across all sites in a multisite network.
 */
function multisite_detect_and_disable_plugins() {
    if (!is_admin() || !current_user_can('manage_network')) {
        return;
    }

    // List of problematic plugins to deactivate
    $problematic_plugins = ['plugin-name1/plugin.php', 'plugin-name2/plugin.php']; // Adjust based on issue

    // Get all sites in the multisite network
    $sites = get_sites(['fields' => 'ids', 'number' => 0]); // 'number' => 0 ensures all sites are retrieved

    foreach ($sites as $site_id) {
        switch_to_blog($site_id);

        foreach ($problematic_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                deactivate_plugins($plugin);
                error_log("[MU Plugin] Deactivated conflicting plugin: $plugin on Site ID: $site_id");

                // Send admin email notification
                $admin_email = get_option('admin_email');
                $subject = "Plugin Disabled on Site $site_id";
                $message = "The following plugin was disabled due to conflicts: $plugin";
                wp_mail($admin_email, $subject, $message);
            }
        }

        restore_current_blog();
    }
}
add_action('admin_init', 'multisite_detect_and_disable_plugins');

/**
 * Optimize the database tables for all sites in the multisite network.
 */
function multisite_optimize_database() {
    global $wpdb;

    // Get all tables in the database
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");

    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE $table");
    }

    error_log("[MU Plugin] Database optimization completed for all tables.");
}
add_action('wp_scheduled_db_cleanup', 'multisite_optimize_database');

/**
 * Schedule daily database cleanup.
 */
function schedule_database_cleanup() {
    if (!wp_next_scheduled('wp_scheduled_db_cleanup')) {
        wp_schedule_event(time(), 'daily', 'wp_scheduled_db_cleanup');
    }
}
add_action('wp_loaded', 'schedule_database_cleanup');