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

class myiot_add_device {

    function __construct() {
    }

    /**
     * Displays your add device page
     * 
     * @param array $args An array with the words to fill up the form fields
     *
     * @return void
     */
    function view( $args = array() ) {
        if ( isset( $_POST['submit'] ) && $_POST['submit'] == 'add device' ) {
            $is_entered = $this->submit_add_device_form();
            $args = array_merge( $is_entered, $args );
        }
        $page_add_device_url = get_admin_url( NULL , 'admin.php?page=manage_iot_devices' );
        $name    = ( isset( $args['device_name']    ) ) ? $args['device_name']    : '';
        $api_key = ( isset( $args['api_key'] ) ) ? $args['api_key'] : $this->generate_api_key();
        $api_url = ( isset( $args['api_url'] ) ) ? $args['api_url'] : '';
        $refresh_time = ( isset( $args['refresh_time'] ) ) ? $args['refresh_time'] : REFRESH_TIME;

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . __( 'My IOT Devices', 'my-iot' ) . '</h1>';
        echo '<a href="' . $page_add_device_url . '" class="page-title-action">' . __( 'Back to manage devices', 'my-iot' ) . '</a>';
        echo '<hr class="wp-header-end">';
        ?>
        <p>Ge din nya enhet ett namn, mata sedan in anslutnings-uppgifterna nedan i din enhet.</p>
        <form id="addDevice" action="" method="post">
            <table class="form-table">
                <tbody>
                <tr class="form-field form-required">
                    <th scope="row"><label for="device_name"><?php _e( 'Name', 'my-iot' ) ?> <span class="description">(<?php _e( 'required', 'my-iot' ) ?>)</span></label></th>
                    <td><input required name="device_name" type="text" id="device_name" value="<?php echo $name ?>" aria-required="true" autocapitalize="none" autocorrect="off" maxlength="60"></td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row"><label for="refresh_time"><?php _e( 'Refresh time', 'my-iot' ) ?> <span class="description">(<?php _e( 'required', 'my-iot' ) ?>)</span></label></th>
                    <td><input required name="refresh_time" type="text" id="refresh_time" value="<?php echo $refresh_time ?>" aria-required="true" autocapitalize="none" autocorrect="off" maxlength="60"></td>
                </tr>
                <tr class="form-field form">
                    <th scope="row"><label for="api_key2"><?php _e( 'API key', 'my-iot' ) ?></label></th>
                    <td><input disabled name="api_key2" type="text" id="api_key2" value="<?php echo $api_key ?>" maxlength="60"></td>
                </tr>
                <tr class="form-field form">
                    <th scope="row"><label for="api_url"><?php _e( 'API url', 'my-iot' ) ?></label></th>
                    <td><input disabled name="api_url" type="text" id="api_url" value="<?php echo $api_url ?>" maxlength="60"></td>
                </tr>
                </tbody>
            </table>
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            <input type="hidden" name="action" value="my-iot_addDevice" />
            <input type="hidden" name="api_key" value="<?php echo $api_key ?>" />
            <?php wp_nonce_field( 'my-iot_add_device' ) ?>

            <p class="submit">
                <input type="submit" name="submit" id="submit_addDevice" class="button button-primary" value="<?php _e( 'add device', 'my-iot' ) ?>" />
            </p>
        </form>
        <?php
        echo '</div>';
    }

    /**
     * This saves the data from form to database
     *
     * @return array An array with the entered values.
     */
    function submit_add_device_form() {
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'my-iot_add_device' ) ) {

            $name    = sanitize_text_field( $_POST['device_name'] );
            $api_key = sanitize_text_field( $_POST['api_key'] );
            $refreshtime = sanitize_key( $_POST['refresh_time'] );

            $db = new myiot_db;
            $success = $db->save_new_device( -1, $name, $api_key, '', $this->generate_security_key(), $refreshtime );
            
            if ( $success ) {
                $this->admin_notice( 'notice-success', __( 'Successfully saved your new device in database!', 'my-iot' ) );
            }

            return $_POST;
        } else {
            $this->admin_notice( 'notice-error', __( "Couldn't save the new device!", 'my-iot' ) );
        }
    }

    /**
     * Prints the admin notice of your choice
     *
     * @param [string] $class (notice-success, notice-error, notice-warning, notice-info) and optional is-dismissible
     * @param [string] $message
     * @return void
     */
    function admin_notice( $class, $message) {
        $class = 'notice ' . $class;
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
    }


    function generate_api_key() {
        return wp_generate_password( 28, false, false );
    }

    function generate_security_key() {
        return rand( 10000, 99999 );
    }
}

?>