<?php
/*
Plugin Name: WPU Action Logs
Plugin URI: https://github.com/WordPressUtilities/wpuactionlogs
Description: WPU Action Logs is a wonderful plugin.
Version: 0.4.1
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUActionLogs {
    private $plugin_version = '0.4.1';
    private $plugin_settings = array(
        'id' => 'wpuactionlogs',
        'name' => 'WPU Action Logs'
    );
    private $settings_obj;

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
        load_plugin_textdomain('wpuactionlogs', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    # ADMIN PAGES
    public function load_admin_page() {
        $admin_pages = array(
            'main' => array(
                'icon_url' => 'dashicons-admin-generic',
                'menu_name' => $this->plugin_settings['name'],
                'name' => $this->plugin_settings['name'],
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

        $action_string = __('Enable logs for the “%s” actions', 'wpuactionlogs');
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
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpuactionlogs\WPUBaseSettings($this->settings_details, $this->settings);
    }

    public function page_content__main() {
        add_filter('wpubaseadmindatas_cellcontent', array(&$this, 'wpubaseadmindatas_cellcontent'), 10, 3);

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
        if ($cell_id == 'user_id' && is_numeric($cellcontent)) {
            $user = get_user_by('id', $cellcontent);
            if ($user) {
                $cellcontent = '<img style="vertical-align:middle;margin-right:0.3em" src="' . esc_url(get_avatar_url($user->ID, array('size' => 16))) . '" />';
                $cellcontent .= '<strong style="vertical-align:middle">' . esc_html($user->user_login) . '</strong>';
            }
        }
        if ($cell_id == 'action_detail') {
            $data = json_decode($cellcontent, 1);
            if (is_array($data)) {
                $cellcontent = '';
                $cellcontent .= '<ul style="margin:0">';
                foreach ($data as $key => $var) {
                    $cellcontent .= '<li style="margin:0"><strong>' . $key . ' : </strong><span>' . $var . '</span></li>';
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
    }

    function action__posts($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (defined('WPUACTIONLOGS__ACTION__POSTS__TRIGGERED')) {
            return;
        }
        define('WPUACTIONLOGS__ACTION__POSTS__TRIGGERED', 1);

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

        $excluded_options = array(
            'cron',
            'action_scheduler_lock_async-request-runner'
        );

        if (in_array($option_name, $excluded_options)) {
            return;
        }

        if (substr($option_name, 0, 15) == '_site_transient') {
            return;
        }

        if (substr($option_name, 0, 10) == '_transient') {
            return;
        }

        $this->log_line(array(
            'option_name' => $option_name
        ));
    }
}

$WPUActionLogs = new WPUActionLogs();
