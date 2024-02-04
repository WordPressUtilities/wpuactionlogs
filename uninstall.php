<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpuactionlogs_options',
    'wpuactionlogs_wpuactionlogs_version'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}

/* Delete tables */
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpuactionlogs");
