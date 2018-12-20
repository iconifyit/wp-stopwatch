<?php
/*
 * Plugin Name: Atomic Stopwatch
 * Plugin URI:  https://github.com/iconifyit/wp-stopwatch
 * Description: A simple WordPress plugin to measure and display page render time.
 * Author:      Scott Lewis <scott@atomiclotus.net>
 * Author URI:  https://atomiclotus.net
 * Version:     0.5
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
     * @var (array)
     */
    private static $ip_addresses;

    /**
     * @var
     */
    static $markers;

    /**
     * AtomicStopwatch constructor. Register the actions.
     */
    function __construct() {

        self::marker( __LINE__, __METHOD__, __FILE__, 'Create AtomicStopwatch instance' );

        register_setting( 'general', 'my_ip_address', 'esc_attr' );

        add_action( 'admin_init', array( 'AtomicStopwatch', 'settings_fields' ) );

        self::set_ip_addresses();

        // Only initialize the plugin if the user's IP address matches the saved one.

        if ( self::can_run() ) {
            add_action( 'admin_enqueue_scripts', array( 'AtomicStopwatch', 'styles' ) );
            add_action( 'admin_bar_menu',        array( 'AtomicStopwatch', 'admin_bar'), 900 );
        }

        add_action( 'init',     array( 'AtomicStopwatch', 'start' ) );
        add_action( 'shutdown', array( 'AtomicStopwatch', 'stop' ) );

    }

    private static function can_run() {

        self::set_ip_addresses();

        return in_array( self::get_ip_address(), self::$ip_addresses );
    }

    /**
     * Set ip_addresses variables.
     */
    private static function set_ip_addresses() {
        self::$ip_addresses = array_map(
            'trim',
            explode( ',', get_option( 'my_ip_address' ) )
        );
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
            "My IP Address <br><span style='font-weight: normal;'>(Show on these addresses)</spna>",
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
        $my_address = self::get_ip_address();
        echo "<p>To add your IP address to the current configuration, simply click" .
             "this link <a href='javascript:void(0);' id='my-address'>{$my_address}</a>" .
             "then click 'Save'.</p>";
    }

    /**
     * Textbox callback.
     * @param $args
     */
    function textbox_callback( $args ) {

        $my_address = self::get_ip_address();
        $option = get_option( $args[0] );
        if ( empty( $option ) ) {
            $option = self::get_ip_address();
        }
        ?>
        <script>
            ;(function($) {
                $(function() {
                    $("#my-address").click(function(e) {
                        var value = $("#<?php echo $args[0]; ?>").val();
                        var my_address = '<?php echo $my_address; ?>';
                        if (value.indexOf(my_address) == -1) {
                            value += ( $.trim(value) != '' ? ', ' : ''  ) + my_address;
                        }
                        $("#<?php echo $args[0]; ?>").val(value);
                    });
                });
            })(jQuery);
        </script>
        <?php
        echo "<textarea cols=\"50\" " . "rows=\"10\" class=\"atomic-stopwatch\" " . 
             "id=\"{$args[0]}\" name=\"{$args[0]}\" >{$option}</textarea>";
    }

    /**
     * The output buffer handler.
     * @param $buffer
     *
     * @return mixed
     */
    public static function ob_handler( $buffer ) {

        $toolbar_tab = "<!-- AtomicStopwatch toolbar tab placeholder -->";
        $the_report  = "<!-- AtomicStopwatch report placeholder -->";

        self::$elapsed_time = round(
            floatval(microtime(true)) - floatval(self::$start_time),
            2
        );

        $label = __( 'Page Render', 'atomic-stopwatch' );

        if ( self::can_run() ) {
            $toolbar_tab = $label . ' : ' . self::$elapsed_time . 's';
            self::marker( __LINE__, __METHOD__, __FILE__, 'Finish AtomicStopwatch' );
            $the_report = "<h2>Atomic Stopwatch Report</h2>" . self::report();
        }

        return str_replace(
            ['{{atomic-stopwatch}}', '{{stopwatch-report}}'],
            [$toolbar_tab, $the_report ],
            $buffer
        );
    }

    public function admin_bar( $wp_admin_bar ) {

        $args = array(
            'id'     => 'atomic_stopwatch',
            'title'	 =>	'{{atomic-stopwatch}}',
            'meta'   => array(
                'class' => 'first-toolbar-group'
            ),
        );
        $wp_admin_bar->add_node( $args );

        // add child items
        $args = array();
        array_push($args,array(
            'id'		=> 'atomic-settings',
            'title'		=> 'Settings',
            'href'		=> 'http://turnpike.vm/wp-admin/options-general.php',
            'parent'	=> 'atomic_stopwatch',
        ));

        array_push($args,array(
            'id'		=> 'atomic-lotus',
            'title'		=> 'Atomic Lotus',
            'href'		=> 'https://atomiclotus.net',
            'meta'      => [ 'target' => '_blank' ],
            'parent'	=> 'atomic_stopwatch',
        ));

        foreach( $args as $each_arg)	{
            $wp_admin_bar->add_node($each_arg);
        }

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
        wp_register_style( 'atomic_stopwatch', plugins_url('/assets/atomic-stopwatch.css', __FILE__), false, null );
        wp_enqueue_style( 'atomic_stopwatch' );
    }

    public static function report() {
        if ( is_null( self::$markers ) ) return null;

        $table = "<table style='width: 100%;' class='atomic-stopwatch'> 
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Duration this step</th>
                    <th>Total Run time</th>
                    <th>Line</th>
                    <th>Function</th>
                    <th>File</th>
                    <th>Extra</th>
                </tr>";
        $rownum = 1;
        foreach ( self::$markers as $marker ) {
            $table .=
                "<tr>
                    <td>{$rownum}</td>
                    <td>{$marker['time']}</td>
                    <td>{$marker['elapsed']}</td>
                    <td>{$marker['runtime']}</td>
                    <td>{$marker['line']}</td>
                    <td>{$marker['func']}</td>
                    <td>{$marker['file']}</td>
                    <td>{$marker['extra']}</td>
                </tr>";
            $rownum++;
        }
        $table .= "</table>";
        return $table;
    }

    /**
     * Prints a marker indicating current line, function,
     * and file in code execution.
     *
     * Example:
     *     AtomicStopwatch::marker( __LINE__, __FUNCTION__, __FILE__, "Cool" );
     *
     * Then, somewhere in your page add:
     *    {{stopwatch-report}}
     *
     * @param $line
     * @param $func
     * @param $file
     */
    public static function marker( $line, $func, $file, $extra=null ) {

        static $hold;

        $last_time = $hold;
        $hold = microtime(true);

        $time    = round(microtime(true), 2);
        $elapsed = ( is_null( $last_time ) ? '0.00' : round(microtime(true) - floatval($last_time), 2) );
        $runtime = ( is_null( $last_time ) ? '0.00' : round(microtime(true) - floatval(self::$start_time), 2) );

        $file = "/wp-content" . array_pop( explode( 'wp-content', $file ) );

        if ( is_null( self::$markers ) ) {
            self::$markers = [];
        }

        self::$markers[] = [
            'time'    => $time,
            'runtime' => $runtime,
            'elapsed' => $elapsed,
            'line'    => $line,
            'func'    => $func,
            'file'    => $file,
            'extra'   => $extra
        ];
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
