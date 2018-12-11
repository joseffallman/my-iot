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

    function init() {
        global $wpdb;
        $wpdb->query('SET SQL_BIG_SELECTS=1');
    }

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
            $where = "WHERE d.id = %d ";
            $where_var = $id;
        } elseif ( null != $apikey ) {
            $where = "WHERE d.apikey = %s ";
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
        $device = $wpdb->get_row( $query, ARRAY_A );
        if ( false === $device ) {
            return false;
        }
        return $device;
    }

    function get_all_devices( $orderby = '', $order = '') {
        global $wpdb;
        $device_table = $this->get_device_table();
        $device_sensors_table = $this->get_device_sensors_table();

        $orderby = ( 'time_updated' == $orderby ) ? "ORDER BY ds.time_updated" : "ORDER BY d.id" ;
        $order = ( 'DESC' == strtoupper( $order ) ) ? "DESC" : "ASC";

        $query = "
        SELECT      d.*, ds.time_updated, count(ds.id) AS sensors
        FROM        $device_table d
        LEFT JOIN   $device_sensors_table ds
        ON          (d.id = ds.device_id)
        $orderby $order
        ";

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
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();

        $query = "
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
            ORDER BY    ds.name ASC
            ";

        $devices = $wpdb->get_results( $query, ARRAY_A );
        return $devices;
        */


        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_values_table  = $this->get_sensor_table();

        $query = "
            SELECT      ds.*, ds.id AS sensor_id
            FROM        $device_sensors_table ds
            WHERE       ds.device_id = $id
            ORDER BY    ds.name ASC
        ";

        $sensors = $wpdb->get_results( $query, ARRAY_A );

        if ( $sensors ) {
            foreach( $sensors as $key => $sensor ) {
                $sensor_id = $sensor['id'];
                $query = "
                    SELECT      s.*
                    FROM        $sensor_values_table s
                    WHERE       s.sensor_id = $sensor_id
                    ORDER BY    s.time_updated DESC LIMIT 1
                ";

                $values = $wpdb->get_row( $query, ARRAY_A );
                if ( $values ) {
                    $sensors[$key] = array_merge( $sensor, $values );
                }
            }
        }
        return $sensors;
    }

    function get_editable_sensors( $id ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();
        $editable_sensors = [];

        $query = "
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
            ";

        $outputs = $wpdb->get_results( $query, ARRAY_A );
        foreach( $outputs as $output ) {
            $editable_sensors[ $output['name'] ] = array(
                "slug" => $output['slug'],
                "value" => $output['value'],
                "updated" => $output['updated']
            );
        }
        return $editable_sensors;
    }

    /**
     * Return a sensor with id
     *
     * @param [int] $id of sensor to return.
     * @return array
     */
    function get_sensor( $id ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();
        $sensor_table = $this->get_sensor_table();
        
        $query = $wpdb->prepare("
            SELECT      s.*, ds.*, ds.id AS sensor_id
            FROM        $device_sensors_table ds
            LEFT JOIN   $sensor_table s
            ON          (ds.id = s.sensor_id)
            LEFT JOIN   $sensor_table s2
            ON          (ds.id = s2.sensor_id AND 
                        (s.time_updated < s2.time_updated OR
                        s.time_updated = s2.time_updated AND s.id < s2.id ))
            WHERE       s2.id IS NULL
            AND         ds.id = %d
            ",
            $id
        );
        
        $outputs = $wpdb->get_row( $query, ARRAY_A );
        return $outputs;
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

            $insert['time_updated'] = current_time('mysql');
            $formats[] = '%s';

            $bool = $wpdb->insert( 
                $sensor_table,
                $insert,
                $formats
            );

            $update = $wpdb->update(
                $device_sensors_table,
                array( 'value' => $sensor['value'], 'time_updated' => current_time('mysql') ),
                array( 'id' => $sensor['sensor_id'] ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        return $bool;
    }

    /**
     * Mark a sensor as updated so it will be given to your device on next update
     *
     * @param [type] $sensor_id
     * @param [type] $value
     * @return void
     */
    function sensor_output_flag( $sensor_id, $value ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();

        $update = $wpdb->update(
            $device_sensors_table,
            array( 'updated' => $value ),
            array( 'id' => $sensor_id ),
            array( '%d' ),
            array( '%d' )
        );

        return $update;
    }

    /**
     * Restores all output flag. When your device have read and updated it's state's
     *
     * @param [type] $id
     * @return void
     */
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

    function get_latest_update( $device_id ) {
        global $wpdb;
        $device_sensors_table = $this->get_device_sensors_table();

        $query = $wpdb->prepare(
            "
            SELECT      time_updated
            FROM        $device_sensors_table
            WHERE       device_id = %d
            AND         ( editable IS NULL OR editable != %d )
            ORDER BY    time_updated DESC
            ",
            array(
                $device_id,
                1
            )
        );
        $time_updated = $wpdb->get_var( $query );
        return $time_updated;
    }
}

?>