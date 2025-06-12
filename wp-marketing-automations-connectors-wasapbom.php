<?php
/**
 * Plugin Name: FunnelKit Automations Connectors - WasapBom
 * Plugin URI: https://panel.wasapbom.com
 * Description: Turn WhatsApp into a powerful sales and support tool — automate messages for cart abandonment, order updates, and post-purchase follow-ups with FunnelKit Automations.
 * Version: 1.0.0
 * Author: Web Impian Sdn Bhd
 * Author URI: https://panel.wasapbom.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: funnelkit-wasapbom
 * Requires Plugins: wp-marketing-automations-connectors
 *
 * Requires at least: 5.0
 * Tested up to: 6.8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @property true $sync
 */
final class WFCO_WasapBom {
    /**
     * @var WFCO_WasapBom
     */

    public static $_instance = null;

    private function __construct() {
        $this->sync = true;

        /**
         * Load important variables and constants
         */
        $this->define_plugin_properties();

        /**
         * Loads common file
         */
        $this->load_commons();
    }

    /**
     * Defining constants
     */
    public function define_plugin_properties(): void
    {
        define( 'WFCO_WASAPBOM_PLUGIN_FILE', __FILE__ );
        define( 'WFCO_WASAPBOM_PLUGIN_DIR', __DIR__ );
        define( 'WFCO_WASAPBOM_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFCO_WASAPBOM_PLUGIN_FILE ) ) );
        define( 'WFCO_WASAPBOM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        define( 'WFCO_WASAPBOM_MAIN', 'funnelkit-wasapbom' );
        define( 'WFCO_WASAPBOM_ENCODE', sha1( WFCO_WASAPBOM_PLUGIN_BASENAME ) );
    }

    /**
     * Load common hooks
     */
    public function load_commons(): void
    {
        $this->load_hooks();
    }

    public function load_hooks(): void
    {
        add_action( 'wfco_load_connectors', [ $this, 'load_connector_classes' ] );
        add_action( 'bwfan_automations_loaded', [ $this, 'load_autonami_classes' ] );
        add_action( 'bwfan_loaded', [ $this, 'init_wasapbom' ] );
    }

    public function init_wasapbom(): void
    {
        require WFCO_WASAPBOM_PLUGIN_DIR . '/includes/class-wfco-wasapbom-call.php';
    }

    public static function get_instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Load Connector Classes
     */
    public function load_connector_classes(): void
    {
        require_once( WFCO_WASAPBOM_PLUGIN_DIR . '/includes/class-wfco-wasapbom-call.php' );
        require_once( WFCO_WASAPBOM_PLUGIN_DIR . '/connector.php' );

        do_action( 'wfco_wasapbom_connector_loaded', $this );
    }

    /**
     * Load Autonami Integration classes
     */
    public function load_autonami_classes(): void
    {
        $integration_dir = WFCO_WASAPBOM_PLUGIN_DIR . '/autonami';
        foreach ( glob( $integration_dir . '/class-*.php' ) as $_field_filename ) {
            require_once( $_field_filename );
        }
        do_action( 'wfco_wasapbom_integrations_loaded', $this );

    }
}

if ( ! function_exists( 'WFCO_WasapBom_Core' ) ) {
    function WFCO_WasapBom_Core() {  //@codingStandardsIgnoreLine
        return WFCO_WasapBom::get_instance();
    }
}

WFCO_WasapBom_Core();