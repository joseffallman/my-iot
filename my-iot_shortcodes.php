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

    function __construct() {
        add_shortcode( 'iot_widget', array( $this, 'iot_widget' ) );

        wp_register_script(
            'iot_widget',
            plugins_url( 'my-iot/js/shortcode.js' ),
            array( 'jquery', 'jquery-ui' ),
            '1.0'
        );

        wp_register_style(
            'iot_widget',
            plugins_url( 'my-iot/style/shortcode.css' ),
            array(),
            '1.0'
        );
    }

    function iot_widget( $args, $content, $shortcodename ) {
        wp_enqueue_script(  'iot_widget' );
        wp_enqueue_style(   'iot_widget' );
        wp_localize_script( 'iot_widget', 'args', $args );

        $device = ( isset( $args['device'] ) ) ? $args['device'] : false;
        $sensor = ( isset( $args['sensor'] ) ) ? $args['sensor'] : false;

        $output = "<div class='iot_widget'>hej</div>";

        return $output;
    }
}


 ?>