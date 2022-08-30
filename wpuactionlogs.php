<?php
/*
Plugin Name: WPU Action Logs
Plugin URI: https://github.com/WordPressUtilities/wpuactionlogs
Description: WPU Action Logs is a wonderful plugin.
Version: 0.2.0
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUActionLogs {
    private $plugin_version = '0.2.0';
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

        $action_string = __('Enable logs for the “%s” action', 'wpuactionlogs');
        $this->settings = array(
            'action__save_post' => array(
                'label' => 'save_post',
                'label_check' => sprintf($action_string, 'save_post'),
                'type' => 'checkbox'
            ),
            'action__wp_update_nav_menu' => array(
                'label' => 'wp_update_nav_menu',
                'label_check' => sprintf($action_string, 'wp_update_nav_menu'),
                'type' => 'checkbox'
            ),
            'action__edit_term' => array(
                'label' => 'edit_term',
                'label_check' => sprintf($action_string, 'edit_term'),
                'type' => 'checkbox'
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpuactionlogs\WPUBaseSettings($this->settings_details, $this->settings);
    }

    public function page_content__main() {
        $array_values = false; // ($array_values are automatically retrieved if not a valid array)
        echo $this->baseadmindatas->get_admin_table(
            $array_values,
            array(
                'perpage' => 50,
                'columns' => array(
                    'id' => __('ID', 'wpuactionlogs'),
                    'creation' => __('Date', 'wpuactionlogs'),
                    'user_id' => __('User ID', 'wpuactionlogs'),
                    'action_type' => __('Action type', 'wpuactionlogs'),
                    'action_detail' => __('Action detail', 'wpuactionlogs')
                )
            )
        );
    }

    public function page_content__subpage() {
        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->settings_details['plugin_id']);
        echo submit_button(__('Save Changes', 'wpuactionlogs'));
        echo '</form>';
    }

    /* ----------------------------------------------------------
      Actions
    ---------------------------------------------------------- */

    public function load_actions() {
        add_action('save_post', array(&$this,
            'action__save_post'
        ), 99, 1);
        add_action('wp_update_nav_menu', array(&$this,
            'action__wp_update_nav_menu'
        ), 99, 2);
        add_action('edit_term', array(&$this,
            'action__edit_term'
        ), 99, 3);
    }

    function action__save_post($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (defined('WPUACTIONLOGS__ACTION__SAVE_POST__TRIGGERED')) {
            return;
        }
        define('WPUACTIONLOGS__ACTION__SAVE_POST__TRIGGERED', 1);

        if ($this->settings_obj->get_setting('action__save_post') != '1') {
            return;
        }

        $post_type = get_post_type($post_id);
        if ($post_type == 'nav_menu_item') {
            return;
        }

        $this->baseadmindatas->create_line(array(
            'user_id' => get_current_user_id(),
            'action_type' => 'save_post',
            'action_detail' => json_encode(array(
                'post_id' => $post_id,
                'post_type' => $post_type,
                'post_title' => get_the_title($post_id)
            ))
        ));
    }

    function action__wp_update_nav_menu($menu_id, $menu_data = array()) {

        if (defined('WPUACTIONLOGS__WP_UPDATE_NAV_MENU__TRIGGERED')) {
            return;
        }
        define('WPUACTIONLOGS__WP_UPDATE_NAV_MENU__TRIGGERED', 1);

        if ($this->settings_obj->get_setting('action__wp_update_nav_menu') != '1') {
            return;
        }

        $this->baseadmindatas->create_line(array(
            'user_id' => get_current_user_id(),
            'action_type' => 'wp_update_nav_menu',
            'action_detail' => json_encode(array(
                'menu_id' => $menu_id,
                'menu_data' => $menu_data
            ))
        ));
    }

    function action__edit_term($term_id, $tt_id, $taxonomy) {

        if (defined('WPUACTIONLOGS__EDIT_TERM__TRIGGERED')) {
            return;
        }
        define('WPUACTIONLOGS__EDIT_TERM__TRIGGERED', 1);

        if ($this->settings_obj->get_setting('action__edit_term') != '1') {
            return;
        }

        if ($taxonomy == 'nav_menu') {
            return;
        }

        $this->baseadmindatas->create_line(array(
            'user_id' => get_current_user_id(),
            'action_type' => 'edit_term',
            'action_detail' => json_encode(array(
                'term_id' => $term_id,
                'tt_id' => $tt_id,
                'taxonomy' => $taxonomy
            ))
        ));
    }
}

$WPUActionLogs = new WPUActionLogs();
