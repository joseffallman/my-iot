<?php
/**
 * My IOT, Manage your IOT devices
 * 
 * @author      Josef Fällman <josef.fallman@gmail.com>
 * @copyright   2018 Josef Fällman
 * @license     GPL-3.0-or-later
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class myiot_manage_devices extends WP_List_Table {

    function __construct() {
        parent::__construct();
    }

    function view() {
        $page_add_device_url = get_admin_url( NULL , 'admin.php?page=add_iot_device' );

        echo '<div class="wrap"><h1 class="wp-heading-inline">My IOT Devices</h2>';
        echo '<a href=\'' . $page_add_device_url . '\' class="page-title-action">Add Device</a>';
        echo '<hr class="wp-header-end">';
        $this->prepare_items();
        ?>
        <form id="devices" method="post">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <?php  $this->display(); ?>
        </form>
        <?php
        echo '</div>'; 
    }

    function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        /*
         * Sort the array.
         */
        usort( $this->example_data, array( &$this, 'usort_reorder' ) );

        /*
         * Process your actions
         */
        $this->process_action();

        $this->_column_headers = array($columns, $hidden, $sortable);
        //$this->items = $this->example_data;
        $this->items = $this->data();
    }

    function get_columns() {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'id'           => 'Id',
            'name'         => 'Namn',
            'apikey'       => 'Api Key',
            'sensors'      => 'Antal sensorer',
            'time_updated' => 'Senast uppdaterad'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'id'           => array( 'id', false ),
            'time_updated' => array( 'time_updated', false )
        );
        return $sortable_columns;
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'id':
            case 'name':
            case 'apikey':
            case 'sensors':
            case 'time_updated':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

    function column_cb($item) {
        return sprintf( 
            '<input type="checkbox" name="devices[]" value="%s" />', $item['id']
        );
    }

    function process_action() {

    }

    function data() {
        $db = new myiot_db;
        $orderby = ( isset( $_GET['orderby'] ) ) ? $_GET['orderby'] : '';
        $order = ( isset( $_GET['order'] ) ) ? $_GET['order'] : '';

        $devices = $db->get_all_devices( $orderby, $order );
        $s = [];
        foreach ( $devices as $device ) {
            $s[] = $device;
        }
        return $s;
    }



    /**
     * EXAMPLE
     */
    function usort_reorder( $a, $b ) {
        // If no sort, default to title
        $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'id';
        // If no order, default to asc
        $order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
        // Determine sort order
        $result = strcmp( $a[$orderby], $b[$orderby] );
        // Send final sort direction to usort
        return ( $order === 'asc' ) ? $result : -$result;
    }
}
?>