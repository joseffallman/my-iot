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

class myiot_shortcodes {

    function __construct( $_db ) {
        add_shortcode( 'iot_widget',                array( $this, 'iot_widget' ) );
        add_action( 'wp_enqueue_scripts',           array( $this, 'register_script' ) );
        add_action( 'wp_ajax_nopriv_sensor_change', array( $this, 'sensor_change' ) );
        add_action( 'wp_ajax_sensor_change',        array( $this, 'sensor_change' ) );
        add_action( 'wp_ajax_nopriv_device_reload', array( $this, 'device_reload' ) );
        add_action( 'wp_ajax_device_reload',        array( $this, 'device_reload' ) );

        add_action( 'template_redirect', array( $this, 'handle_view' ) );
        add_filter( 'query_vars',        array( $this, 'add_rewrite_var' ) );

        $this->myiot_db = $_db;
        $this->js_data = array();

        //add_action( 'init', array( $this, 'register_rewrite_rule' ) );
    }

    function register_rewrite_rule() {
        $this->add_rewrite_rule();
        flush_rewrite_rules();
    }

    function add_rewrite_rule() {
        add_rewrite_rule(
            '^/device/?$',
            'index.php?my-iot_device_page=1',
            'top'
        );
    }

    function add_rewrite_var( $vars ) {
        $vars[] = 'my-iot_device_page';
        return $vars;
    }

    function register_script() {
        wp_register_script(
            'iot_widget_js',
            plugins_url( '/my-iot/js/shortcode.js' ),
            array( 'jquery' )
        );
        wp_enqueue_style(
            'iot_widget',
            plugins_url( 'my-iot/style/shortcode.css' ),
            array(),
            '1.0'
        );
    }
    function enqueue_script() {
        wp_enqueue_style(   'iot_widget' );
        wp_enqueue_script( 'iot_widget_js' );
        wp_localize_script( 'iot_widget_js', 'param', $this->js_data );
    }

    function iot_widget( $args, $content, $shortcodename ) {
        //wp_enqueue_script(  'iot_widget' );
        wp_enqueue_style(   'iot_widget' );
        //wp_localize_script( 'iot_widget', 'args', $args );

        $device = ( isset( $args['device'] ) ) ? $args['device'] : false;
        $sensor = ( isset( $args['sensor'] ) ) ? $args['sensor'] : false;
        $devices = $this->myiot_db->get_all_devices();
        $form_url = site_url();

        $output = "<div class='iot_widget'>";
        $output .= "Välj en enhet";
        foreach ( $devices as $device ) {
            $id      = $device['id'];
            $pin     = $device['securitykey'];
            $output .= "<form action='$form_url/device/' method='post'>";
            $output .= "<div class='iot_widget_device'>";
            $output .= "<input type='hidden' name='my-iot_device_page' value='$id' >";
            $output .= "<input type='hidden' name='id' value='$id' >";
            $output .= wp_nonce_field( 'my-iot', '_wpnonce', true, false );
            $output .= "<div class='alignleft'>";
            $output .= "<div class='iot_infotext'>Namn:</div>";
            $output .= $device['name'];
            $output .= "</div>";
            $output .= "<div class='alignright'>";
            $output .= "<div class='iot_infotext'>Antal sensorer:</div>";
            $output .= $device['sensors'];
            $output .= "</div>";
            $output .= "<span class='clear'></span>";
            $output .= "<div class='iot_widget_form_elements'>";
            $output .= "<div class='iot_infotext'>Enter device pin code:</div>";
            $output .= "<input type='number' value='$pin' name='PIN' placeholder='PIN code' class='alignleft PIN_code'>";
            $output .= "<input type='Submit' value='Välj' class='clear'>";
            $output .= "<span class='clear'></span>";
            $output .= "</div>";
            $output .= "</div>";
            $output .= "</form>";
        }
        $output .= "";
        $output .= "</div>";

        return $output;
    }

    function handle_view() {
        if (    get_query_var( 'my-iot_device_page' ) &&
                isset( $_POST['id'] ) &&
                wp_verify_nonce( $_REQUEST['_wpnonce'], 'my-iot' )
            ) {

            wp_enqueue_style(  'iot_widget' );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );

            $this->js_data = array(
                'ajax_url' => admin_url( 'admin-ajax.php' )
            );

            status_header( 200 );
            
            echo "<html><head><title>";
            echo get_bloginfo( 'name' );
            echo "</title>";
            echo "<meta charset='UTF-8'>";
            echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
            wp_head();
            echo "</head>";
            echo "<div class='iot_device_center aligncenter'>";
            echo $this->device_controll();
            echo "</div>";
            die();
        }
    }

    function device_controll( $args = "" ) {
        $id         = ( isset( $args['id'] ) ) ? $args['id'] : $_POST['id'];
        $device     = $this->myiot_db->get_device($id);
        $sensors    = $this->myiot_db->get_device_sensors($id);
        $last_upd   = $this->myiot_db->get_latest_update($id);
        $pin        = ( isset( $args['PIN'] ) ) ? $args['PIN'] : $_POST['PIN'];
        $fullHtml   = ( isset( $args['inner'] ) && $args['inner'] ) ? false : true ;

        $diff       = strtotime( current_time('mysql') ) - strtotime( $last_upd );
        
        ob_start();

        if ( $fullHtml ) {
            echo "<div class='iot_widget_device' id='$id'>";
        }

?>
            <span class='title'><?php echo $device['name']; ?></span>
            <div class='flex_item PIN_box'>
            <span class='PIN_Info'>PIN code: </span>
            <span class='PIN alignright'><?php echo $pin;?></span>
            </div>
            <div class='refresh_line' last='<?php echo $last_upd; ?>' diff_seconds='<?php echo $diff; ?>' refresh='<?php echo $device['refreshtime'];?>'></div>
            <?php
                foreach( $sensors as $sensor ) {
                    //echo "<br><pre>";
                    //print_r($sensor);
                    //echo "</pre>";
                    if ( $sensor['value_column'] == 'bool' ) {
                        $value = ($sensor[ $sensor['value_column'] ]) ? "on" : "off";
                        $checked = ($sensor[ $sensor['value_column'] ]) ? "checked" : "";
                    } else {
                        $value = $sensor[ $sensor['value_column'] ];
                    }
                    ?>
                    <div class='iot_sensor'>
                        <div class='flex_item sensorname '>
                            <?php echo $sensor['name']; ?>
                        </div>
                        <div class='flex_item value'>
                            <?php 
                            if( $sensor['editable'] && $sensor['value_column'] == 'bool' && $device['securitykey'] == $pin ) {
                            ?>
                                <div class="onoffswitch">
                                    <input  type="checkbox"
                                            name="onoffswitch"
                                            class="onoffswitch-checkbox"
                                            sensor="<?php echo $sensor['sensor_id'] ?>"
                                            id="myonoffswitch" <?php echo $checked ?>>
                                    <label class="onoffswitch-label" for="myonoffswitch">
                                        <span class="onoffswitch-inner"></span>
                                        <span class="onoffswitch-switch"></span>
                                    </label>
                                </div>
                            <?php
                            } else {
                                echo "<span class='iot_infotext'>";
                                echo $value;
                                echo "</span>";
                            }
                            ?>
                        </div>
                        <span class='clear'></span>
                    </div>
                    <?php
                }
        if ( $fullHtml ) {
            echo "</div>";
        }

        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * The ajax return function
     *
     * @return void
     */
    function sensor_change() {
        $id = $_REQUEST['sensor_id'];
        $sensor = $this->myiot_db->get_sensor( $id );

        $sensor['value'] = $_REQUEST['sensor_value'];
        $success = $this->myiot_db->add_sensor_values( $sensor['device_id'], array( $sensor ) );
        $this->myiot_db->sensor_output_flag( $sensor['id'], $success );

        $this->device_reload( $success );
    }

    function device_reload( $success = true ) {
        $device_controll_args = array(
            "id"    => $_REQUEST['device_id'],
            "PIN"   => $_REQUEST['pin'],
            "inner" => true
        );
        $html = $this->device_controll( $device_controll_args );

        $return = array( 
            "success"   => $success,
            "html"      => $html
        );
        wp_send_json( $return );
        die();
    }

}
 ?>