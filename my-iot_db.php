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

class myiot_db {

    function install_plugin_tables() {
        global $wpdb;

        $charset_collate     = $wpdb->get_charset_collate();
        $device_table        = $this->get_device_table();
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table        = $this->get_sensor_table();

        $iot_devices = "CREATE TABLE $device_table (
            id MEDIUMINT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100),
            apikey TINYTEXT NOT NULL,
            userrole TINYTEXT NOT NULL,
            securitykey INT NOT NULL,
            refreshtime INT NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";

        $iot_sensors = "CREATE TABLE $device_sensors_table ( 
            id MEDIUMINT NOT NULL AUTO_INCREMENT,
            device_id MEDIUMINT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug TINYTEXT NOT NULL,
            editable BOOLEAN,
            value_column TINYTEXT NOT NULL,
            updated BOOLEAN,
            value TEXT,
            time_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
            ) $charset_collate;";

        $iot_sensor_values = "CREATE TABLE $sensor_table ( 
            id MEDIUMINT NOT NULL AUTO_INCREMENT,
            sensor_id MEDIUMINT NOT NULL,
            bool BOOLEAN,
            number INT,
            temperatur DECIMAL(13,3),
            string TEXT,
            time_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
            ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $iot_devices );
        dbDelta( $iot_sensors );
        dbDelta( $iot_sensor_values );


        add_option( "my-iot_db_version", "1.0" );
    }

    function uninstall_plugin_tables() {
        global $wpdb;
        $device_table      = $this->get_device_table();
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table      = $this->get_sensor_table();

        $query_devices = "DROP TABLE IF EXISTS $device_table";
        $query_devices_sensors = "DROP TABLE IF EXISTS $device_sensors_table";
        $query_sensor_values = "DROP TABLE IF EXISTS $sensor_table";
        $wpdb->query( $query_devices );
        $wpdb->query( $query_devices_sensors );
        $wpdb->query( $query_sensor_values );

        delete_option( "my-iot_db_version" );
    }

    /**
     * Returns the name of the devices db table.
     *
     * @return string Table name.
     */
    function get_device_table() {
        global $wpdb;
        return $wpdb->prefix . "iot_devices";
    }

    function get_device_sensors_table() {
        global $wpdb;
        return $wpdb->prefix . "iot_device_sensors";
    }

    /**
     * Returns the name of the sensors values db table.
     *
     * @return string Table name.
     */
    function get_sensor_table() {
        global $wpdb;
        return $wpdb->prefix . "iot_sensors_values";
    }

    /**
     * Saves a new device in database, or updates an existing.
     *
     * @param int    $id, Id of device or -1.
     * @param string $name
     * @param string $api_key
     * @param string $role
     * @param string $securety
     * @param int    $refreshtime
     * @return int Rows inserted or false on error.
     */
    function save_new_device( $id, $name, $api_key, $role, $security, $refreshtime ) {
        global $wpdb;

        $insert = array(
            'name' => $name,
            'apikey' => $api_key,
            'userrole' => $role,
            'securitykey' => $security,
            'refreshtime' => $refreshtime,
        );
        $formats = array(
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
        );

        if ( -1 == $id ) {
            $bool = $wpdb->insert( 
                $this->get_device_table(),
                $insert,
                $formats
            );
        } else {
            $bool = $wpdb->update( 
                $this->get_device_table(),
                $insert,
                array(
                    'id' => $id
                ),
                $formats
            );
        }

        return $bool;
    }

    function check_api_key( $key ) {
        global $wpdb;
        $device_table = $this->get_device_table();

        $query = $wpdb->prepare(
            "
            SELECT      id
            FROM        $device_table
            WHERE       apikey = %s
            ",
            $key
        );
        
        return $wpdb->get_var( $query );
    }

    /**
     * Get a device by id or api key.
     *
     * @param int $id of device.
     * @param string $apikey of device.
     * @return array Device information.
     */
    function get_device( $id = null, $apikey = null ) {
        global $wpdb;
        $device_table = $this->get_device_table();
        $device_sensors_table = $this->get_device_sensors_table();

        if ( null != $id ) {
            $where = " AND d.id = %d ";
            $where_var = $id;
        } elseif ( null != $apikey ) {
            $where = " AND d.apikey = %s ";
            $where_var = $apikey;
        } else {
            return false;
        }

        $query = $wpdb->prepare(
            "
            SELECT      d.*, ds.time_updated, count(ds.id) AS sensors
            FROM        $device_table d
            LEFT JOIN   $device_sensors_table ds
            ON          (d.id = ds.device_id)
            $where
            ",
            array( 
                $where_var
            )
        );
        $device = $wpdb->get_results( $query, ARRAY_A );
        if ( false === $device ) {
            return false;
        }
        return $device[0];
    }

    function get_all_devices( $orderby = '', $order = '') {
        global $wpdb;
        $device_table = $this->get_device_table();
        $device_sensors_table = $this->get_device_sensors_table();

        $orderby = ( 'time_updated' == $orderby ) ? "ORDER BY ds.time_updated" : "ORDER BY d.id" ;
        $order = ( 'DESC' == strtoupper( $order ) ) ? "DESC" : "ASC";

        $query = $wpdb->prepare(
            "
            SELECT      d.*, ds.time_updated, count(ds.id) AS sensors
            FROM        $device_table d
            LEFT JOIN   $device_sensors_table ds
            ON          (d.id = ds.device_id)
            $orderby $order
            ",
            array(
            )
        );

        $devices = $wpdb->get_results( $query, ARRAY_A );
        return $devices;
    }

    /**
     * Check that this security key is matching with a device.
     * This shall also return true if no key is needed.
     *
     * @param mixed $id or api key of device to check.
     * @param string $security_key to match.
     * @return int id of matching device.
     */
    function check_security_key( $id, $security_key ) {
        global $wpdb;
        $device_table = $this->get_device_table();

        $query = $wpdb->prepare(
            "
            SELECT      id
            FROM        $device_table
            WHERE       securitykey = %s
            AND         ( id = %s OR apikey = %s )
            ",
            array(
                $security_key,
                $id,
                $id
            )
        );
        $device_id = $wpdb->get_var( $query );
        return $device_id;
    }

    /**
     * Updates the security key of a device.
     *
     * @param int $id of device to update
     * @return int new security key or false if failure
     */
    function update_security_key( $id ) {
        global $wpdb;
        $device_table = $this->get_device_table();
        $security_key = myiot_add_device::generate_security_key();

        $update = $wpdb->update(
            $device_table,
            array( 'securitykey' => $security_key ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
        if ( false === $update ) {
            return '';
        }
        else {
            return $security_key;
        }
    }

    /**
     * Check that api and security keys matching a device.
     *
     * @param string $api_key
     * @param int $security_key or '' if not needed
     * @return int Id of matching device or false on failure
     */
    function check_api_and_security_key( $api_key, $security_key = '' ) {
        global $wpdb;
        $device_table = $this->get_device_table();
        $security_key = ( '' == $security_key ) ? -1 : $security_key;

        $query = $wpdb->prepare(
            "
            SELECT      id
            FROM        $device_table
            WHERE       apikey = %s
            AND         securitykey = %d
            ",
            array(
                $api_key,
                $security_key
            )
        );
        $device_id = $wpdb->get_var( $query );
        return $device_id;
    }

    /**
     * This retrives the sensors connected to this device.
     *
     * @param int $id of device.
     * @return array with sensors for this device.
     */
    function get_device_sensors( $id ) {
        /*
        $s1 = array(
            'name'  => 'kontaktor',
            'slug'  => 'kontaktor',
            'db'    => 'kontaktor',
            'type'  => 'bool'
        );
        $s2 = array(
            'name'  => 'motorskydd',
            'slug'  => 'motorskydd',
            'db'    => 'motorskydd',
            'type'  => 'bool'
        );
        $s3 = array(
            'name'  => 'timeON',
            'slug'  => 'timeON',
            'db'    => 'time_on',
            'type'  => 'int'
        );
        $s4 = array(
            'name'      => 'relay',
            'slug'      => 'relay',
            'output'    => true,
            'db'        => 'relay',
            'value'     => 0,
            'updated'   => true,
            'type'      => 'bool'
        );
        */
        //return array( $s1, $s2, $s3, $s4 );

        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();

        $query = $wpdb->prepare(
            "
            SELECT      s.*, ds.*, ds.id AS sensor_id
            FROM        $device_sensors_table ds
            LEFT JOIN   $sensor_table s
            ON          (ds.id = s.sensor_id)
            LEFT JOIN   $sensor_table s2
            ON          (ds.id = s2.sensor_id AND 
                        (s.time_updated < s2.time_updated OR
                        s.time_updated = s2.time_updated AND s.id < s2.id ))
            WHERE       s2.id IS NULL
            AND         ds.device_id = $id
            ",
            array(
            )
        );

        $devices = $wpdb->get_results( $query, ARRAY_A );
        return $devices;
    }

    function get_editable_sensors( $id ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();

        $query = $wpdb->prepare(
            "
            SELECT      s.*, ds.*, ds.id AS sensor_id
            FROM        $device_sensors_table ds
            LEFT JOIN   $sensor_table s
            ON          (ds.id = s.sensor_id)
            LEFT JOIN   $sensor_table s2
            ON          (ds.id = s2.sensor_id AND 
                        (s.time_updated < s2.time_updated OR
                        s.time_updated = s2.time_updated AND s.id < s2.id ))
            WHERE       s2.id IS NULL
            AND         ds.device_id = $id
            AND         ds.editable = 1
            ",
            array(
            )
        );

        $devices = $wpdb->get_results( $query, ARRAY_A );
        return $devices;
    }

    /**
     * Add sensor values to db
     *
     * @param int $id of device
     * @param array $sensors with $sensor arrays
     * @return void
     */
    function add_sensor_values( $id, $sensors ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();

        foreach ( $sensors as $sensor ) {
            $insert = array(
                'sensor_id' => $sensor['sensor_id']
            );
            $formats = array(
                '%d'
            );

            $insert[ $sensor['value_column'] ] = $sensor['value'];
            switch( $sensor['value_column'] ) {
                case 'int':
                case 'bool':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
            }

            $bool = $wpdb->insert( 
                $sensor_table,
                $insert,
                $formats
            );

            $update = $wpdb->update(
                $device_sensors_table,
                array( 'value' => $sensor['value'] ),
                array( 'id' => $sensor['sensor_id'] ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $bool;
    }

    function change_device_sensor_output_flag( $id ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();

        $update = $wpdb->update(
            $device_sensors_table,
            array(
                'updated' => 0
            ),
            array(
                'device_id' => $id,
                'editable'  => 1
            ),
            array(
                '%d'
            ),
            array(
                '%d',
                '%d'
            )
        );
    }
}

?>