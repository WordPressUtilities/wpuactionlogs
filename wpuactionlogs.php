<?php
defined('ABSPATH') || die;
/*
Plugin Name: WPU Action Logs
Plugin URI: https://github.com/WordPressUtilities/wpuactionlogs
Update URI: https://github.com/WordPressUtilities/wpuactionlogs
Description: Useful logs about what’s happening on your website admin.
Version: 0.21.1
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpuactionlogs
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUActionLogs {
    public $wpubasemessages;
    public $basecron;
    public $plugin_description;
    public $adminpages;
    public $settings_update;
    public $baseadmindatas;
    public $settings_details;
    public $settings;
    private $plugin_version = '0.21.1';
    private $plugin_settings = array(
        'id' => 'wpuactionlogs',
        'name' => 'WPU Action Logs',
        'transient_action_prefix' => 'user_last_page_'
    );
    private $settings_obj;
    private $last_post_id;
    private $admin_page_id = 'wpuactionlogs-main';

    public function __construct() {
        add_filter('plugins_loaded', array(&$this,
            'load_translation'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_update'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_admin_page'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_custom_table'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_settings'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_messages'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_cron'
        ));
        add_filter('plugins_loaded', array(&$this,
            'load_actions'
        ));
        add_action('admin_init', array(&$this,
            'log_current_user_action'
        ));
        add_action('admin_enqueue_scripts', array(&$this,
            'admin_enqueue_scripts'
        ));

        add_action('wp_dashboard_setup', function () {
            if (!$this->can_view_active_users()) {
                return;
            }
            wp_add_dashboard_widget(
                'wpuactionlogs_dashboard_widget',
                __('Currently online', 'wpuactionlogs'),
                array(&$this, 'dashboard_widget_content')
            );
        });

        add_action('admin_bar_menu', array(&$this,
            'admin_bar_menu_display_active_users'
        ), 999);
        add_action('wpuactionlogs__cron_hook', array(&$this,
            'wpuactionlogs__callback_function'
        ), 10);
    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    # TRANSLATION
    function load_translation() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wpuactionlogs', false, $lang_dir)) {
            load_muplugin_textdomain('wpuactionlogs', $lang_dir);
        }
        $this->plugin_description = __('Useful logs about what’s happening on your website admin.', 'wpuactionlogs');
    }

    # ASSETS
    function admin_enqueue_scripts() {
        wp_enqueue_style('wpuactionlogs-style', plugins_url('assets/admin.css', __FILE__), array(), $this->plugin_version);
    }

    # ADMIN PAGES
    public function load_admin_page() {
        $admin_pages = array(
            'main' => array(
                'icon_url' => 'dashicons-admin-generic',
                'menu_name' => $this->plugin_settings['name'],
                'name' => $this->plugin_settings['name'],
                'has_form' => false,
                'function_content' => array(&$this,
                    'page_content__main'
                )
            ),
            'settings' => array(
                'aliases' => array(
                    'subpage'
                ),
                'parent' => 'main',
                'name' => __('Settings', 'wpuactionlogs'),
                'settings_link' => true,
                'settings_name' => __('Settings', 'wpuactionlogs'),
                'has_form' => false,
                'function_content' => array(&$this,
                    'page_content__settings'
                )
            ),
            'actions' => array(
                'parent' => 'main',
                'name' => __('Actions', 'wpuactionlogs'),
                'settings_link' => false,
                'has_form' => true,
                'function_content' => array(&$this,
                    'page_content__actions'
                ),
                'function_action' => array(&$this,
                    'page_action__actions'
                )
            )
        );
        $pages_options = array(
            'id' => $this->plugin_settings['id'],
            'level' => 'manage_options',
            'basename' => plugin_basename(__FILE__)
        );
        require_once __DIR__ . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
        $this->adminpages = new \wpuactionlogs\WPUBaseAdminPage();
        $this->adminpages->init($pages_options, $admin_pages);
    }

    function load_update() {
        require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpuactionlogs\WPUBaseUpdate(
            'WordPressUtilities',
            'wpuactionlogs',
            $this->plugin_version);
    }

    # CUSTOM TABLE
    public function load_custom_table() {
        require_once __DIR__ . '/inc/WPUBaseAdminDatas/WPUBaseAdminDatas.php';
        $this->baseadmindatas = new \wpuactionlogs\WPUBaseAdminDatas();
        $this->baseadmindatas->init(array(
            'handle_database' => false,
            'can_edit' => true,
            'plugin_id' => $this->plugin_settings['id'],
            'plugin_pageid' => $this->admin_page_id,
            'table_name' => $this->plugin_settings['id'],
            'table_fields' => array(
                'user_id' => array(
                    'public_name' => __('User ID', 'wpuactionlogs'),
                    'type' => 'number'
                ),
                'action_type' => array(
                    'public_name' => __('Action type', 'wpuactionlogs'),
                    'type' => 'varchar'
                ),
                'action_detail' => array(
                    'public_name' => __('Action detail', 'wpuactionlogs'),
                    'type' => 'sql',
                    'sql' => 'TEXT'
                ),
                'action_source' => array(
                    'public_name' => __('Action source', 'wpuactionlogs'),
                    'type' => 'varchar'
                ),
                'action_interface' => array(
                    'public_name' => __('Action interface', 'wpuactionlogs'),
                    'type' => 'varchar'
                )
            )
        ));
    }

    # SETTINGS
    public function load_settings() {
        $this->settings_details = array(
            # Admin page
            'parent_page' => 'admin.php?page=wpuactionlogs-main',
            'plugin_basename' => plugin_basename(__FILE__),
            # Default
            'plugin_name' => $this->plugin_settings['name'],
            'plugin_id' => $this->plugin_settings['id'],
            'option_id' => $this->plugin_settings['id'] . '_options',
            'sections' => array(
                'actions' => array(
                    'name' => __('Actions', 'wpuactionlogs'),
                    'wpubasesettings_checkall' => true
                ),
                'interfaces' => array(
                    'name' => __('Interfaces', 'wpuactionlogs'),
                    'wpubasesettings_checkall' => true
                ),
                'extras' => array(
                    'name' => __('Extras', 'wpuactionlogs')
                )
            )
        );

        $action_string = __('Enable logs for “%s”', 'wpuactionlogs');
        $interface_string = __('Disable logs on %s', 'wpuactionlogs');
        $this->settings = array(
            'action__posts' => array(
                'label' => __('Posts', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Posts', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__wp_update_nav_menu' => array(
                'label' => __('Menus', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Menus', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__terms' => array(
                'label' => __('Terms', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Terms', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__options' => array(
                'label' => __('Options', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Options', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__mails' => array(
                'label' => __('Emails', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Emails', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__users' => array(
                'label' => __('Users', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Users', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'action__plugins' => array(
                'label' => __('Plugins', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Plugins', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'actions'
            ),
            'interface_web_disabled' => array(
                'label' => __('Disable on web', 'wpuactionlogs'),
                'label_check' => sprintf($interface_string, __('web interface', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'interfaces'
            ),
            'interface_cron_disabled' => array(
                'label' => __('Disable on cron', 'wpuactionlogs'),
                'label_check' => sprintf($interface_string, __('cron interface', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'interfaces'
            ),
            'interface_cli_disabled' => array(
                'label' => __('Disable on CLI', 'wpuactionlogs'),
                'label_check' => sprintf($interface_string, __('CLI interface', 'wpuactionlogs')),
                'type' => 'checkbox',
                'section' => 'interfaces'
            ),
            'extras__display_active_users' => array(
                'label' => __('Display active users', 'wpuactionlogs'),
                'type' => 'checkbox',
                'section' => 'extras'
            ),
            'extras__purge_after' => array(
                'label' => __('Purge after N days', 'wpuactionlogs'),
                'help' => __('Logs will be automatically deleted after this number of days. Set to 0 to disable', 'wpuactionlogs'),
                'type' => 'number',
                'section' => 'extras'
            )
        );
        require_once __DIR__ . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpuactionlogs\WPUBaseSettings($this->settings_details, $this->settings);
    }

    function load_messages() {
        require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
        $this->wpubasemessages = new \wpuactionlogs\WPUBaseMessages($this->plugin_settings['id']);
    }

    function get_call_stack() {
        $backtrace = debug_backtrace();
        $files = array();
        foreach ($backtrace as $trace) {
            if (!isset($trace['file'])) {
                continue;
            }
            if (strpos($trace['file'], 'wp-content/') === false) {
                continue;
            }
            preg_match('/wp-content\/([a-z0-9-_]+)\/([a-z0-9-_]+)\//isU', $trace['file'], $matches);
            if (!$matches) {
                continue;
            }
            $files[] = $matches[1] . '/' . $matches[2];
        }
        $files = array_unique($files);

        if (count($files) > 1 && ($key = array_search('plugins/wpuactionlogs', $files)) !== false) {
            unset($files[$key]);
        }

        return $files;

    }

    public function page_content__main() {

        add_filter('wpubaseadmindatas_cellcontent', array(&$this, 'wpubaseadmindatas_cellcontent'), 10, 3);

        /* Filter by user */
        $current_user = isset($_GET['filter_key'], $_GET['filter_value']) && $_GET['filter_key'] == 'user_id' && is_numeric($_GET['filter_value']) ? $_GET['filter_value'] : '';
        $current_user_empty = isset($_GET['filter_key'], $_GET['filter_value']) && $_GET['filter_key'] == 'user_id' && $_GET['filter_value'] == '';
        $table = $this->plugin_settings['id'];
        global $wpdb;
        $q = "SELECT DISTINCT user_id, display_name FROM {$wpdb->prefix}{$table}  LEFT JOIN {$wpdb->users} ON user_id = {$wpdb->users}.ID   WHERE user_id != '' ORDER BY display_name ASC";
        $users = $wpdb->get_results($q);

        if ($users) {
            $users_with_name = array();
            foreach ($users as $usr) {
                $users_with_name[$usr->user_id] = $usr->display_name . ' (#' . $usr->user_id . ')';
            }

            echo '<p><label for="wpuactionlogs_select_user">' . __('Select an user: ', 'wpuactionlogs') . '</label><select id="wpuactionlogs_select_user" onchange="window.location=this.value?this.getAttribute(\'data-base-url\')+\'&amp;filter_key=user_id&amp;filter_value=\'+(this.value==\'-\'?\'\':this.value):this.getAttribute(\'data-base-url\')" data-base-url="' . admin_url('admin.php?page=' . $this->admin_page_id) . '" name="user_id" id="user_id">';
            echo '<option ' . (!$current_user_empty && $current_user == '' ? 'selected' : '') . ' value="">' . __('All', 'wpuactionlogs') . '</option>';
            echo '<option ' . ($current_user_empty ? 'selected' : '') . ' value="-">' . __('None', 'wpuactionlogs') . '</option>';
            foreach ($users_with_name as $usr_id => $user_name) {
                echo '<option ' . ($current_user == $usr_id ? 'selected' : '') . ' value="' . $usr_id . '">' . esc_html($user_name) . '</option>';
            }
            echo '</select></p>';
        }

        echo $this->baseadmindatas->get_admin_table(
            false,
            array(
                'perpage' => 50,
                'columns' => array(
                    'id' => __('ID', 'wpuactionlogs'),
                    'creation' => __('Date', 'wpuactionlogs'),
                    'user_id' => __('Account', 'wpuactionlogs'),
                    'action_type' => __('Action type', 'wpuactionlogs'),
                    'action_source' => __('Action source', 'wpuactionlogs'),
                    'action_detail' => __('Action detail', 'wpuactionlogs'),
                    'action_interface' => __('Action interface', 'wpuactionlogs')
                )
            )
        );
    }

    function wpubaseadmindatas_cellcontent($cellcontent, $cell_id, $settings) {
        $admin_url = admin_url('admin.php?page=' . $this->admin_page_id);
        $filter_url = $admin_url . '&' . http_build_query(array(
            'filter_key' => $cell_id,
            'filter_value' => $cellcontent
        ));
        if ($cell_id == 'user_id' && is_numeric($cellcontent)) {
            $user_id = $cellcontent;
            $user = get_user_by('id', $user_id);
            if ($user) {
                $login = '<a href="' . esc_url($filter_url) . '">' . esc_html($user->display_name) . '</a>';
                $cellcontent = '<img loading="lazy" class="wpuactionlogs-cell-avatar" src="' . esc_url(get_avatar_url($user->ID, array('size' => 16))) . '" />';
                $cellcontent .= '<strong style="vertical-align:middle">' . $login . '</strong>';
            }
        }
        if ($cell_id == 'action_type' || $cell_id == 'action_interface') {
            $cellcontent = '<a href="' . esc_url($filter_url) . '">' . esc_html($cellcontent) . '</a>';
        }
        if ($cell_id == 'action_source') {
            $cellcontent_raw = json_decode($cellcontent, 1);
            if ($cellcontent_raw) {
                $cellcontent_raw = array_map(function ($a) {
                    $admin_url = admin_url('admin.php?page=' . $this->admin_page_id);
                    $filter_link = $admin_url . '&' . http_build_query(array(
                        'where_text' => str_replace('/', '\\/', $a)
                    ));
                    return '<a href="' . esc_url($filter_link) . '">' . $a . '</a>';
                }, $cellcontent_raw);
                $cellcontent = implode('<br/>', $cellcontent_raw);
            }
        }
        if ($cell_id == 'action_detail') {
            $data = json_decode($cellcontent, 1);
            if (is_array($data)) {
                $cellcontent = '';

                if (isset($data['to'], $data['subject'], $data['message'])) {
                    $data['message'] = $this->text_truncate($data['message'], 100);
                }
                /* Post ids */
                $post_id = false;
                if (isset($data['post_id'])) {
                    $post_id = $data['post_id'];
                    $data['post_id'] = '<a href="' . get_edit_post_link($data['post_id']) . '">' . $data['post_id'] . '</a>';
                }

                /* Attachments */
                if ($post_id && isset($data['post_type']) && $data['post_type'] == 'attachment') {
                    $image_html = wp_get_attachment_image($post_id, 'thumbnail', false, array('style' => 'max-width:50px;height:auto;float:left;margin-right:1em;'));
                    $cellcontent .= '<a href="' . get_edit_post_link($post_id) . '">' . $image_html . '</a>';
                }

                /* Crop post_title */
                if (isset($data['post_title'])) {
                    $data['post_title'] = $this->text_truncate($data['post_title'], 50);
                }

                /* Cells */
                $cellcontent .= '<ul class="wpuactionlogs-cell-list">';
                foreach ($data as $key => $var) {
                    $var_display = $var;
                    if (is_array($var_display) && isset($var_display[0])) {
                        $var_display = implode(', ', $var_display);
                    }
                    $cellcontent .= '<li><strong>' . $key . ' : </strong><span>' . $var_display . '</span></li>';
                }
                $cellcontent .= '</ul>';

            }
        }
        return $cellcontent;
    }

    public function page_content__settings() {
        settings_errors();
        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->settings_details['plugin_id']);
        submit_button(__('Save Changes', 'wpuactionlogs'));
        echo '</form>';
    }

    /* ----------------------------------------------------------
        Actions
    ---------------------------------------------------------- */

    function page_content__actions() {
        submit_button(__('Purge old logs', 'wpuactionlogs'), 'primary', 'purge_old_logs');
        submit_button(__('Delete all logs', 'wpuactionlogs'), 'secondary', 'delete_old_logs');
    }

    function page_action__actions() {

        if (!isset($_POST['purge_old_logs']) && !isset($_POST['delete_old_logs'])) {
            return;
        }

        if (isset($_POST['delete_old_logs'])) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->prefix}{$this->plugin_settings['id']}");
            $this->wpubasemessages->set_message('wpuactionlogs_delete_old_logs_success', __('All logs have been deleted', 'wpuactionlogs'), 'updated');
            return;
        }
        if (isset($_POST['purge_old_logs'])) {
            $this->purge_old_actions();
            $this->wpubasemessages->set_message('wpuactionlogs_purge_old_logs_success', __('Old logs have been purged', 'wpuactionlogs'), 'updated');
            return;
        }
    }

    /* ----------------------------------------------------------
      Admin widget
    ---------------------------------------------------------- */

    function dashboard_widget_content() {
        $active_users = $this->get_active_users();
        if (!$active_users) {
            echo '<p>' . __('No active users', 'wpuactionlogs') . '</p>';
            return;
        }
        echo '<ul>';
        foreach ($active_users as $user) {
            echo '<li> • ' . $user->display_name . '</li>';
        }
        echo '</ul>';
    }

    /* ----------------------------------------------------------
      Log
    ---------------------------------------------------------- */

    function log_line($args, $extra = array()) {

        if (!is_array($args)) {
            $args = array(
                'text' => $args
            );
        }

        if (!is_array($extra)) {
            $extra = array();
        }
        if (!isset($extra['user_id'])) {
            $extra['user_id'] = get_current_user_id();
        }

        $this->baseadmindatas->create_line(array(
            'user_id' => $extra['user_id'],
            'action_source' => json_encode($this->get_call_stack()),
            'action_interface' => php_sapi_name(),
            'action_type' => current_action(),
            'action_detail' => json_encode($args)
        ));
    }

    /* ----------------------------------------------------------
      Actions
    ---------------------------------------------------------- */

    public function load_actions() {

        $is_cli = php_sapi_name() == 'cli';
        $is_web = !$is_cli;
        $is_cron = wp_doing_cron();

        if ($is_cli && $this->settings_obj->get_setting('interface_cli_disabled') == '1') {
            return;
        }

        if ($is_web && $this->settings_obj->get_setting('interface_web_disabled') == '1') {
            return;
        }

        if ($is_cron && $this->settings_obj->get_setting('interface_cron_disabled') == '1') {
            return;
        }

        if (apply_filters('wpuactionlogs__actions__disable_save_logs', false)) {
            return;
        }

        /* Posts */
        $post_hooks = array(
            'edit_attachment',
            'save_post',
            'delete_post'
        );
        foreach ($post_hooks as $post_hook) {
            add_action($post_hook, array(&$this,
                'action__posts'
            ), 99, 1);
        }

        /* Menus */
        add_action('wp_update_nav_menu', array(&$this,
            'action__wp_update_nav_menu'
        ), 99, 2);

        /* Terms */
        $term_hooks = array(
            'edit_term',
            'create_term',
            'delete_term'
        );
        foreach ($term_hooks as $term_hook) {
            add_action($term_hook, array(&$this,
                'action__terms'
            ), 99, 4);
        }

        /* Plugins */
        $options_hooks = array(
            'add_option',
            'updated_option',
            'delete_option'
        );
        foreach ($options_hooks as $option_hook) {
            add_action($option_hook, array(&$this,
                'action__options'
            ), 99, 1);
        }

        /* Emails */
        add_action('wp_mail', array(&$this,
            'action__mails'
        ), 9999, 1);

        /* Users */
        $user_hooks = array(
            'wp_update_user',
            'wp_logout',
            'wp_login'
        );
        foreach ($user_hooks as $user_hook) {
            add_action($user_hook, array(&$this,
                'action__users'
            ), 99, 2);
        }

        /* Plugins */
        $plugin_hooks = array(
            'activate_plugin',
            'deactivate_plugin'
        );
        foreach ($plugin_hooks as $plugin_hook) {
            add_action($plugin_hook, array(&$this,
                'action__plugins'
            ), 99, 1);
        }
    }

    function action__posts($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        /* Trigger only once per call per post */
        if ($this->last_post_id == $post_id) {
            return;
        }
        $this->last_post_id = $post_id;

        if ($this->settings_obj->get_setting('action__posts') != '1') {
            return;
        }

        $post_type = get_post_type($post_id);
        if ($post_type == 'nav_menu_item') {
            return;
        }

        $args = array(
            'post_id' => $post_id,
            'post_type' => $post_type
        );
        $p = get_post($post_id);
        if (!is_wp_error($p) && $p && $p->post_title) {
            $args['post_title'] = $p->post_title;
            $args['post_status'] = $p->post_status;
        }

        $this->log_line($args);
    }

    function action__terms($term_id, $tt_id, $taxonomy, $old_term = false) {
        if ($this->settings_obj->get_setting('action__terms') != '1') {
            return;
        }

        if ($taxonomy == 'nav_menu') {
            return;
        }

        $args = array(
            'term_id' => $term_id,
            'tt_id' => $tt_id,
            'taxonomy' => $taxonomy
        );
        $term = get_term($term_id);
        if (!is_wp_error($term) && $term && $term->name) {
            $args['name'] = $term->name;
        }
        if (is_object($old_term) && $old_term->name) {
            $args['name'] = $old_term->name;
        }
        $this->log_line($args);
    }

    function action__options($option_name) {
        if ($this->settings_obj->get_setting('action__options') != '1') {
            return;
        }

        if (!is_admin()) {
            return;
        }

        /* Excluded names */
        $excluded_options = apply_filters('wpuactionlogs__action__options__excluded_options', array(
            'cron',
            'action_scheduler_lock_async-request-runner'
        ));

        if (in_array($option_name, $excluded_options)) {
            return;
        }

        /* Excluded start */
        $excluded_options_start = apply_filters('wpuactionlogs__action__options__excluded_options_start', array(
            'rocket_partial_preload_batch_',
            '_site_transient',
            '_transient'
        ));

        foreach ($excluded_options_start as $start) {
            $start_length = strlen($start);
            if (substr($option_name, 0, $start_length) == $start) {
                return;
            }
        }

        /* Excluded end */
        $excluded_options_end = apply_filters('wpuactionlogs__action__options__excluded_options_end', array(
            '__cron_hook_lastexec'
        ));

        foreach ($excluded_options_end as $end) {
            $end_length = strlen($end);
            if (substr($option_name, 0 - $end_length) == $end) {
                return;
            }
        }

        /* Log */
        $this->log_line(array(
            'option_name' => $option_name
        ));
    }

    function action__mails($args) {
        if ($this->settings_obj->get_setting('action__mails') == '1') {
            $this->log_line(array(
                'to' => $args['to'],
                'subject' => $args['subject'],
                'message' => $this->strip_tags_content($args['message'])
            ));
        }
        return $args;
    }

    function action__wp_update_nav_menu($menu_id, $menu_data = array()) {
        if ($this->settings_obj->get_setting('action__wp_update_nav_menu') == '1' && $menu_data) {
            $this->log_line($menu_data);
        }
    }

    function action__users($user_id, $userdata = array()) {
        $settings = array();
        $current_action = current_action();
        if (!$this->settings_obj->get_setting('action__users') == '1') {
            return;
        }
        if ($user_id && $current_action == 'wp_login') {
            if (!is_numeric($user_id)) {
                $user = get_user_by('login', $user_id);
                if (is_object($user)) {
                    $user_id = $user->ID;
                }
            }
            $this->log_line(array(
            ), array(
                'user_id' => $user_id
            ));
            return;
        }
        if ($user_id && $current_action == 'wp_logout') {
            $this->log_line(array(
            ), array(
                'user_id' => $user_id
            ));
            return;
        }
        if ($userdata) {
            $this->log_line(array(
                'user_id' => $user_id,
                'user_login' => $userdata['user_login']
            ));
        }
    }

    function action__plugins($plugin) {
        $settings = array();
        $current_action = current_action();
        if (!$this->settings_obj->get_setting('action__plugins') == '1') {
            return;
        }
        $this->log_line(array(
            'plugin' => $plugin
        ));
    }

    /* ----------------------------------------------------------
      Cron & Cleanup
    ---------------------------------------------------------- */

    public function load_cron() {
        require_once __DIR__ . '/inc/WPUBaseCron/WPUBaseCron.php';
        $this->basecron = new \wpuactionlogs\WPUBaseCron(array(
            'pluginname' => 'WPU Action Logs',
            'cronhook' => 'wpuactionlogs__cron_hook',
            'croninterval' => 3600
        ));
    }

    public function wpuactionlogs__callback_function() {
        $this->purge_old_actions();
    }

    public function purge_old_actions() {
        global $wpdb;

        /* Stop if purge is not needed */
        $number_of_days = $this->settings_obj->get_setting('extras__purge_after');
        if (!$number_of_days || !is_numeric($number_of_days)) {
            return false;
        }

        /* Prepare the query selecting old logs */
        $table = $this->plugin_settings['id'];
        $q_suffix = $wpdb->prepare(" {$wpdb->prefix}{$table} WHERE creation < DATE_SUB(NOW(), INTERVAL %d DAY)", $number_of_days);

        /* Stop if no lines will be deleted */
        $number_of_deleted_lines = $wpdb->get_var("SELECT COUNT(*) FROM $q_suffix");
        if (!$number_of_deleted_lines) {
            return false;
        }

        /* Delete */
        $wpdb->query("DELETE FROM " . $q_suffix);

        /* Log action */
        $this->log_line(array(
            'action' => 'purge_old_actions',
            'deleted_lines' => $number_of_deleted_lines
        ));
    }

    /* ----------------------------------------------------------
      Extras
    ---------------------------------------------------------- */

    function can_view_active_users() {
        if ($this->settings_obj->get_setting('extras__display_active_users') != '1') {
            return false;
        }
        if (!current_user_can('edit_users')) {
            return false;
        }
        return true;
    }

    function log_current_user_action() {
        if ($this->settings_obj->get_setting('extras__display_active_users') != '1') {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        $current_page = $this->get_current_page();
        if (!$current_page) {
            return;
        }
        $current_user = wp_get_current_user();
        set_transient($this->plugin_settings['transient_action_prefix'] . $current_user->ID, $current_page, 60);
    }

    function admin_bar_menu_display_active_users() {
        if (!$this->can_view_active_users()) {
            return;
        }

        $current_page = $this->get_current_page();
        if (!$current_page) {
            return;
        }

        $active_users = $this->get_others_active_users_on_this_page();
        if (!$active_users || count($active_users) < 2) {
            return;
        }

        $avatars_html = '';
        $number_users_max = 3;
        foreach ($active_users as $i => $active_user) {
            if ($number_users_max-- == 0) {
                $avatars_html .= ' &hellip;';
                break;
            }
            $avatars_html .= '<img loading="lazy" class="wpuactionlogs-active-avatar" src="' . $active_user['avatar'] . '" alt="' . esc_attr($active_user['name']) . '" title="' . esc_attr($active_user['name']) . '" />';
        }

        global $wp_admin_bar;
        $menu_id = 'wpuactionlogs-active-users';
        $args = [
            'id' => $menu_id,
            'title' => __('Online here:', 'wpuactionlogs') . ' ' . $avatars_html
        ];
        $wp_admin_bar->add_node($args);
        foreach ($active_users as $user) {
            $title_html = '<img loading="lazy" src="' . $user['avatar'] . '" alt="" title="' . esc_attr($user['name']) . '" class="wpuactionlogs-active-avatar" />';
            $title_html .= $user['name'] . ($user['id'] == get_current_user_id() ? ' ' . __('(you)', 'wpuactionlogs') : '');
            $wp_admin_bar->add_node([
                'id' => $menu_id . '-' . $user['id'],
                'title' => $title_html,
                'parent' => $menu_id
            ]);
        }
    }

    function get_current_page() {

        /* Retrieve URI */
        $current_page = esc_url($_SERVER['REQUEST_URI']);

        /* Exclude some args */
        $excluded_args = array(
            'error',
            'info',
            'message',
            'notice',
            'success',
            'updated',
            'warning',
            'wp_http_referer'
        );
        foreach ($excluded_args as $excluded_arg) {
            $current_page = remove_query_arg($excluded_arg, $current_page);
        }

        /* Do not track some pages */
        $excluded_pages = array(
            '/wp-admin/profile.php'
        );
        if (in_array($current_page, $excluded_pages)) {
            return false;
        }

        /* Ignore some URL parts */
        $ignored_parts = array(
            array('/index.php', '/')
        );
        foreach ($ignored_parts as $ignored_part) {
            $current_page = str_replace($ignored_part[0], $ignored_part[1], $current_page);
        }

        return $current_page;
    }

    function get_active_users() {
        global $wpdb;
        $users_with_transient = [];
        $q = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_{$this->plugin_settings['transient_action_prefix']}%'";
        $results = $wpdb->get_results($q);
        foreach ($results as $result) {
            $users_with_transient[] = str_replace('_transient_' . $this->plugin_settings['transient_action_prefix'], '', $result->option_name);
        }
        if (!$users_with_transient) {
            return false;
        }
        return get_users(array(
            'include' => $users_with_transient
        ));
    }

    function get_others_active_users_on_this_page() {

        $users = $this->get_active_users();

        /* List users */
        $active_users = [];
        $current_page = $this->get_current_page();
        foreach ($users as $user) {
            $transient_key = $this->plugin_settings['transient_action_prefix'] . $user->ID;
            $last_page = get_transient($transient_key);
            if ($last_page == $current_page) {
                $active_users[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'last_page' => $last_page,
                    'avatar' => get_avatar_url($user->ID, ['size' => 16])
                ];
            }
        }
        return $active_users;
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    function strip_tags_content($string) {
        // Remove script and style contents
        $string = preg_replace(array(
            '#<script[^>]*?>(.*)</script>#siU',
            '#<style[^>]*?>(.*)</style>#siU'
        ), '', $string);

        // Remove HTML tags
        $string = str_replace('>', '> ', $string);
        $string = strip_tags($string);
        $string = preg_replace('/\s+/', ' ', $string);

        // Trim each line
        $lines = preg_split("/(\r\n|\n|\r)/", $string);
        $lines = array_map('trim', $lines);

        // Remove empty lines
        $lines = array_filter($lines, 'strlen');

        return implode("\n", $lines);
    }

    function text_truncate($string, $length = 150, $more = '...') {
        $_new_string = '';
        $string = strip_tags($string);
        $_maxlen = $length - strlen($more);
        $_words = explode(' ', $string);

        /* Add word to word */
        foreach ($_words as $_word) {
            if (strlen($_word) + strlen($_new_string) >= $_maxlen) {
                break;
            }

            /* Separate by spaces */
            if (!empty($_new_string)) {
                $_new_string .= ' ';
            }
            $_new_string .= $_word;
        }

        /* If new string is shorter than original */
        if (strlen($_new_string) < strlen($string)) {

            /* Add the after text */
            $_new_string .= $more;
        }

        return $_new_string;
    }

}

$WPUActionLogs = new WPUActionLogs();

if (defined('WP_CLI') && WP_CLI) {
    /* Simple action */
    WP_CLI::add_command('wpuactionlogs-purge', function ($args = array()) {
        $WPUActionLogs = new WPUActionLogs();
        $WPUActionLogs->load_settings();
        $WPUActionLogs->load_custom_table();
        $WPUActionLogs->purge_old_actions();
        WP_CLI::success('Old action logs purged');
    }, array(
        'shortdesc' => 'Purge old action logs',
        'synopsis' => array()
    ));
}
