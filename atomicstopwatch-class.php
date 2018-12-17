<?php
/*
 * Plugin Name: Atomic Stopwatch
 * Plugin URI:  https://github.com/iconifyit/wp-stopwatch
 * Description: A simple WordPress plugin to measure and display page render time.
 * Author:      Scott Lewis <scott@atomiclotus.net>
 * Author URI:  https://atomiclotus.net
 * Version:     0.1
 * Text Domain: atomic-stopwatch
 * License:     GPL v2 or later
 */

/**
 * WP Atomic Stopwatch measures page render speed and displays the result.
 *
 * LICENSE
 * This file is part of WP Atomic Stopwatch.
 *
 * WP Atomic Stopwatch is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package    Atomic Stopwatch
 * @author     Scott Lewis <scott@atomiclotus.net>
 * @copyright  Copyright 2018 Atomic Lotus, LLC
 * @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
 * @link       https://github.com/iconifyit/wp-stopwatch
 * @since      0.1
 */

defined( 'ABSPATH' ) or die();

/**
 * Class AtomicStopwatch
 */
class AtomicStopwatch {

    /**
     * @var (float) $start_time
     */
    public static $start_time;

    /**
     * @var (float) $end_time
     */
    public static $end_time;

    /**
     * @var (float) $elapsed_time
     */
    public static $elapsed_time;

    /**
     * AtomicStopwatch constructor. Register the actions.
     */
    function __construct() {

        register_setting( 'general', 'my_ip_address', 'esc_attr' );

        add_action( 'admin_init', array( 'AtomicStopwatch', 'settings_fields' ) );

        // Only initialize the plugin if the user's IP address matches the saved one.
        if ( get_option( 'my_ip_address' ) === self::get_ip_address() ) {
            add_action( 'admin_head', array( 'AtomicStopwatch', 'styles' ) );
            add_action( 'init',       array( 'AtomicStopwatch', 'start' ) );
            add_action( 'shutdown',   array( 'AtomicStopwatch', 'stop' ) );
        }
    }

    /**
     * Get the current user's IP
     * @return null
     */
    private static function get_ip_address() {
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    /**
     * Settings fields callback.
     */
    public function settings_fields() {
        add_settings_section(
            'atomic_stopwatch_settings',
            'Atomic Stopwatch',
            array( 'AtomicStopwatch', 'settings_callback' ),
            'general'
        );

        add_settings_field(
            'my_ip_address',
            'My IP Address',
            array( 'AtomicStopwatch', 'textbox_callback' ),
            'general',
            'atomic_stopwatch_settings',
            array(
                'my_ip_address'
            )
        );

        register_setting( 'general', 'my_ip_address', 'esc_attr' );
    }

    /**
     * Settings text callback.
     */
    function settings_callback() {
        $ip_address = self::get_ip_address();
        echo "<p>Enter the IP address on which to show the timer. Your current address is: {$ip_address}</p>";
    }

    /**
     * Textbox callback.
     * @param $args
     */
    function textbox_callback( $args ) {
        $option = get_option( $args[0] );
        if ( empty( $option ) ) {
            $option = self::get_ip_address();
        }
        echo '<input type="text" id="'. $args[0] .'" name="'. $args[0] .'" value="' . $option . '" />';
    }

    /**
     * The output buffer handler.
     * @param $buffer
     *
     * @return mixed
     */
    public static function ob_handler( $buffer ) {

        self::$elapsed_time = round(
            floatval(microtime(true)) - floatval(self::$start_time),
            2
        );

        $label = __( 'Page Render Time', 'atomic-stopwatch' );

        return str_replace(
            '</body>',
            "<div id=\"stopwatch\">{$label} : " . self::$elapsed_time . " seconds</div></body>",
            $buffer
        );

    }

    /**
     * Start the output buffer.
     */
    public function start() {
        self::$start_time = microtime(true);
        ob_start( array( 'AtomicStopwatch', 'ob_handler' ) );
    }

    /**
     * Flush the buffer.
     */
    public function stop() {
        self::$end_time = microtime(true);
        ob_end_flush();
    }

    /**
     * Print the styles in the HTML header.
     */
    public function styles() {
        echo "\n<style>"
            . "#stopwatch { "
            . "bottom: 0; width: 100%; "
            . "height: 50px; line-height: 50px; "
            . "background-color: black; color: #fff; "
            . "font-weight: bold; text-align: center; "
            . "position: fixed; z-index: 10000; "
            . "}</style>\n";
    }

    /**
     * Use a singleton to prevent the plugin loading twice.
     * @return AtomicStopwatch
     */
    public static function init() {
        static $instance;
        if ( is_null( $instance ) ) {
            $instance = new AtomicStopwatch();
        }
        return $instance;
    }
}

new AtomicStopwatch();
