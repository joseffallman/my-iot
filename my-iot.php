<?php
/**
 * My IOT, Manage your IOT devices
 * 
 * @author      Josef Fällman <josef.fallman@gmail.com>
 * @copyright   2018 Josef Fällman
 * @license     GPL-3.0-or-later
 * 
 * Plugin Name: My IOT
 * Plugin URI:  http://github.com/joseffallman/my-iot/
 * Description: Manage and control your IOT devices
 * Author:      Josef Fällman
 * Text Domain: my-iot
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: lang
 * Version:     0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class myiot {
    public  $myiot_dir;
    public  $myiot_api;
    private $db;

    function __construct() {
        // Set properties.
        $this->myiot_dir = dirname( __FILE__ ) . '/';

        // Include files.
        require_once( $this->myiot_dir . 'my-iot_db.php'             );
        require_once( $this->myiot_dir . 'my-iot_manage_devices.php' );
        require_once( $this->myiot_dir . 'my-iot_add_device.php'     );
        require_once( $this->myiot_dir . 'my-iot_api.php'            );
        require_once( $this->myiot_dir . 'my-iot_shortcodes.php'     );

        // Add actions.
        register_activation_hook(   __FILE__,   array( $this, 'plugin_activated'    ) );
        register_deactivation_hook( __FILE__,   array( $this, 'plugin_deactivated'  ) );

        add_action( 'admin_menu',    array( $this, 'admin_menu'   ) );

        // Enable large sql
        $this->db = new myiot_db;
        $this->db->init();

        // Create classes
        $this->myiot_api = new myiot_api( $this->db );
        $shortcode       = new myiot_shortcodes( $this->db );
        register_activation_hook( __FILE__, array( $shortcode, 'register_rewrite_rule') );


        // Default refresh time
        define( 'REFRESH_TIME', 5 );

    }

    /**
     * When plugin is activated
     *
     * @return void
     */
    function plugin_activated() {
        $db = new myiot_db;
        $db->install_plugin_tables();
    }

    /**
     * When plugin is deactivated
     *
     * @return void
     */
    function plugin_deactivated() {
        $mydb = new myiot_db;
        $mydb->uninstall_plugin_tables();
    }

    function admin_menu() {
        add_menu_page(
            'Manage IOT Devices',
            'Manage IOT',
            'read',
            'manage_iot_devices',
            array( $this, 'page_manage_iot' )
        );
        add_submenu_page(
            'manage_iot_devices',
            'Add device',
            'Add device',
            'read',
            'add_iot_device',
            array( $this, 'page_add_device' )
        );
    }

    function page_manage_iot() {
        $myiot_page_manage_iot = new myiot_manage_devices;
        $myiot_page_manage_iot->view();
    }

    function page_add_device() {
        $myiot_page_add_device = new myiot_add_device;

        $args = array(
            'api_url'   => $this->myiot_api->get_api_url() . '?'
        );

        $myiot_page_add_device->view( $args );
    }

};

if (true) {
    $myiot = new myiot;
}

?>