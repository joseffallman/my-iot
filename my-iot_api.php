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

/**
 * Recive inputs from device. If no security key is given a new
 * is created and device is updated. 
 * 
 * Page Link example:
 *      <wordpress>/wp-json/my-iot/v1/api
 * 
 * Url args:
 *      apikey
 *      securitykey
 * 
 * Outputs:
 *      securitykey (optional)
 *      refreshtime
 * 
 */
class myiot_api {
    
    // Properties.
    public $api_namespace = 'my-iot/v1';
    public $api_base      = '/api';

    private $db;

    function __construct( $_db ) {
        // Register API endpoints
        add_action( 'rest_api_init', array( $this, 'register_api' ) );
        add_filter( 'rest_authentication_errors', array( $this, 'rest_auth' ) );

        $this->db = $_db;

    }

    function register_api() {
        register_rest_route( $this->api_namespace, $this->api_base, array(
            'methods'  => 'GET',
            'callback' => array( $this, 'api_callback' )
        ) );
    }

    function rest_auth( $result ) {
        global $wp;
        $url = home_url( $wp->request );
        if ( $this->get_api_url() == $url ) {
            return true;
        }
        //return true;
    }

    function get_api_url() {
        return get_rest_url( null, $this->api_namespace . $this->api_base );
    }

    function api_callback( WP_REST_Request $request ) {

        // New approach
        if ( isset( $_GET['apikey'] ) ) {
            $device = $this->db->get_device( NULL, $_GET['apikey'] );
        }

        if ( $device ) {
            if ( isset( $device['securitykey'] ) && isset( $_GET['securitykey'] ) &&
                $device['securitykey'] == $_GET['securitykey'] ) {
                $edited_sensors = $this->api_output_editable_sensors( $device['id'] );
                $updated        = $this->api_update_sensor_values( $device['id'] );
                return array("sensors" => $edited_sensors, "update_success" => $updated);
            } else {
                $this->db->change_device_sensor_output_flag( $device['id'] );
                return $this->api_new_securitykey( $device['id'] );
            }
        } else {
            $error = new WP_Error;
            $error->add( 500, "Could not accept your request" );
            return $error;
        }
    }

    /**
     * When a new security key needs to be givent to device.
     *
     * @param int $id Id of deivce.
     * @return array
     */
    function api_new_securitykey( $id ) {

        $device = $this->db->get_device( $id, $_GET['apikey'] );
        $security_key = ( -1 == $device['securitykey'] ) ? -1 : $this->db->update_security_key( $device['id'] );

        $output =  array(
            'refreshtime'   => $device['refreshtime'],
        );
        if ( -1 != $security_key ) {
            $output['securitykey'] = $security_key;
        }
        return $output;
    }

    /**
     * Insert device sensor values to db.
     * An array with outputs shall be given to the device.
     *
     * @param int $id Id of device.
     * @return array
     */
    function api_update_sensor_values( $id ) {

        if ( !isset( $this->sensors ) ) {
            $this->sensors = $this->db->get_device_sensors( $id );
        }
        $update_sensors = array();

        foreach( $this->sensors as $sensor ) {
            if ( isset( $_GET[ $sensor['slug'] ] ) ) {
                if ( $this->validate_sensor_type( $_GET[ $sensor['slug'] ], $sensor['value_column'] ) ) {
                    $sensor['value'] = $_GET[ $sensor['slug'] ];
                    $update_sensors[] = $sensor;
                }
            }
        }

        if ( 0 < count( $update_sensors ) ) {
            $is_saved = $this->db->add_sensor_values( $id, $update_sensors );
        } else {
            $is_saved = false;
        }

        return $is_saved;
    }


    function api_output_editable_sensors( $id ) {

        if ( !isset( $this->sensors ) ) {
            $this->sensors = $this->db->get_device_sensors( $id );
        }

        $edited_sensors = [];
        foreach( $this->sensors as $key => $sensor ) {
            if ( $sensor['editable'] && $sensor['value'] && $sensor['updated'] ) {
                //unset( $outputs[$key] );
                $edited_sensor['name'] = $sensor['name'];
                $edited_sensor['slug'] = $sensor['slug'];
                $edited_sensor['value'] = $sensor['value'];
                $edited_sensor['time_updated'] = $sensor['time_updated'];
                $edited_sensors[] = $edited_sensor;
            }
        }

        if ( is_array( $edited_sensors) ) {
            $this->db->change_device_sensor_output_flag( $id );
        }
        return $edited_sensors;
    }

    /**
     * Check that given value is of correct type
     *
     * @param string $value to be checked
     * @param string $type Supported types is bool, int, string
     * @return bool
     */
    function validate_sensor_type( $value, $type ) {
        switch( $type ) {
            case "bool":
                $value = (bool) $value;
                return is_bool( $value );
            case "number":
            case "temperatur":
                return is_numeric( $value );
            case "string":
                return is_string( $value );
        }
    }

}

?>