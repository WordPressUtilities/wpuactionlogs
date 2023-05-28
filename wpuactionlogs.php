<?php
/*
Plugin Name: WPU Action Logs
Plugin URI: https://github.com/WordPressUtilities/wpuactionlogs
Update URI: https://github.com/WordPressUtilities/wpuactionlogs
Description: WPU Action Logs is a wonderful plugin.
Version: 0.8.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpuactionlogs
Domain Path: /lang
Requires at least: 6.0
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUActionLogs {
    public $plugin_description;
    public $adminpages;
    public $settings_update;
    public $baseadmindatas;
    public $settings_details;
    public $settings;
    private $plugin_version = '0.8.0';
    private $plugin_settings = array(
        'id' => 'wpuactionlogs',
        'name' => 'WPU Action Logs'
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
            'load_actions'
        ));
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
        $this->plugin_description = __('WPU Action Logs is a wonderful plugin.', 'wpuactionlogs');
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
            'subpage' => array(
                'parent' => 'main',
                'name' => __('Settings', 'wpuactionlogs'),
                'settings_link' => true,
                'settings_name' => __('Settings', 'wpuactionlogs'),
                'has_form' => false,
                'function_content' => array(&$this,
                    'page_content__subpage'
                )
            )
        );
        $pages_options = array(
            'id' => $this->plugin_settings['id'],
            'level' => 'manage_options',
            'basename' => plugin_basename(__FILE__)
        );
        include dirname(__FILE__) . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
        $this->adminpages = new \wpuactionlogs\WPUBaseAdminPage();
        $this->adminpages->init($pages_options, $admin_pages);
    }

    function load_update() {
        include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpuactionlogs\WPUBaseUpdate(
            'WordPressUtilities',
            'wpuactionlogs',
            $this->plugin_version);
    }

    # CUSTOM TABLE
    public function load_custom_table() {
        include dirname(__FILE__) . '/inc/WPUBaseAdminDatas/WPUBaseAdminDatas.php';
        $this->baseadmindatas = new \wpuactionlogs\WPUBaseAdminDatas();
        $this->baseadmindatas->init(array(
            'handle_database' => false,
            'can_edit' => true,
            'plugin_id' => $this->plugin_settings['id'],
            'plugin_pageid' => $this->admin_page_id,
            'table_name' => 'wpuactionlogs',
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
                    'name' => __('Actions', 'wpuactionlogs')
                )
            )
        );

        $action_string = __('Enable logs for “%s”', 'wpuactionlogs');
        $this->settings = array(
            'action__posts' => array(
                'label' => __('Posts', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Posts', 'wpuactionlogs')),
                'type' => 'checkbox'
            ),
            'action__wp_update_nav_menu' => array(
                'label' => __('Menus', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Menus', 'wpuactionlogs')),
                'type' => 'checkbox'
            ),
            'action__terms' => array(
                'label' => __('Terms', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Terms', 'wpuactionlogs')),
                'type' => 'checkbox'
            ),
            'action__options' => array(
                'label' => __('Options', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Options', 'wpuactionlogs')),
                'type' => 'checkbox'
            ),
            'action__mails' => array(
                'label' => __('Emails', 'wpuactionlogs'),
                'label_check' => sprintf($action_string, __('Emails', 'wpuactionlogs')),
                'type' => 'checkbox'
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpuactionlogs\WPUBaseSettings($this->settings_details, $this->settings);
    }

    public function page_content__main() {
        add_filter('wpubaseadmindatas_cellcontent', array(&$this, 'wpubaseadmindatas_cellcontent'), 10, 3);

        /* Filter by user */
        $current_user = isset($_GET['filter_key'], $_GET['filter_value']) && $_GET['filter_key'] == 'user_id' && is_numeric($_GET['filter_value']) ? $_GET['filter_value'] : '';
        $table = $this->plugin_settings['id'];
        global $wpdb;
        $users = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}{$table} WHERE user_id != ''");
        if ($users) {
            echo '<p><label for="wpuactionlogs_select_user">' . __('Select an user: ', 'wpuactionlogs') . '</label><select id="wpuactionlogs_select_user" onchange="window.location=this.value?this.getAttribute(\'data-base-url\')+\'&amp;filter_key=user_id&amp;filter_value=\'+this.value:this.getAttribute(\'data-base-url\')" data-base-url="' . admin_url('admin.php?page=' . $this->admin_page_id) . '" name="user_id" id="user_id">';
            echo '<option ' . ($current_user == '' ? 'selected' : '') . ' value="">' . __('All', 'wpuactionlogs') . '</option>';
            foreach ($users as $usr_id) {
                echo '<option ' . ($current_user == $usr_id ? 'selected' : '') . ' value="' . $usr_id . '">' . get_the_author_meta('nicename', $usr_id) . '</option>';
            }
            echo '</select></p>';
        }

        $array_values = false; // ($array_values are automatically retrieved if not a valid array)
        echo $this->baseadmindatas->get_admin_table(
            $array_values,
            array(
                'perpage' => 50,
                'columns' => array(
                    'id' => __('ID', 'wpuactionlogs'),
                    'creation' => __('Date', 'wpuactionlogs'),
                    'user_id' => __('Account', 'wpuactionlogs'),
                    'action_type' => __('Action type', 'wpuactionlogs'),
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
                $login = '<a href="' . esc_url($filter_url) . '">' . esc_html($user->user_login) . '</a>';
                $cellcontent = '<img style="vertical-align:middle;margin-right:0.3em" src="' . esc_url(get_avatar_url($user->ID, array('size' => 16))) . '" />';
                $cellcontent .= '<strong style="vertical-align:middle">' . $login . '</strong>';
            }
        }
        if ($cell_id == 'action_type') {
            $cellcontent = '<a href="' . esc_url($filter_url) . '">' . esc_html($cellcontent) . '</a>';
        }
        if ($cell_id == 'action_detail') {
            $data = json_decode($cellcontent, 1);
            if (is_array($data)) {
                if (isset($data['to'], $data['subject'], $data['message'])) {
                    $data['message'] = $this->text_truncate($data['message'], 100);
                }
                $cellcontent = '';
                $cellcontent .= '<ul style="margin:0">';
                foreach ($data as $key => $var) {
                    $var_display = $var;
                    if (is_array($var_display) && isset($var_display[0])) {
                        $var_display = implode(', ', $var_display);
                    }
                    $cellcontent .= '<li style="margin:0"><strong>' . $key . ' : </strong><span>' . $var_display . '</span></li>';
                }
                $cellcontent .= '</ul>';
            }
        }
        return $cellcontent;
    }

    public function page_content__subpage() {
        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->settings_details['plugin_id']);
        echo submit_button(__('Save Changes', 'wpuactionlogs'));
        echo '</form>';
    }

    /* ----------------------------------------------------------
      Log
    ---------------------------------------------------------- */

    function log_line($args) {

        if (!is_array($args)) {
            $args = array(
                'text' => $args
            );
        }

        $this->baseadmindatas->create_line(array(
            'user_id' => get_current_user_id(),
            'action_interface' => php_sapi_name(),
            'action_type' => current_action(),
            'action_detail' => json_encode($args)
        ));
    }

    /* ----------------------------------------------------------
      Actions
    ---------------------------------------------------------- */

    public function load_actions() {
        $post_hooks = array(
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
            'updated_option'
        );
        foreach ($options_hooks as $option_hook) {
            add_action($option_hook, array(&$this,
                'action__options'
            ), 99, 1);
        }

        /* Emails */
        add_filter('wp_mail', array(&$this,
            'action__mails'
        ), 9999, 1);
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

        $excluded_options = apply_filters('wpuactionlogs__action__options__excluded_options', array(
            'cron',
            'action_scheduler_lock_async-request-runner'
        ));

        if (in_array($option_name, $excluded_options)) {
            return;
        }

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
