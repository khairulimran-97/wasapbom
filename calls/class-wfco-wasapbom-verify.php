<?php

class WFCO_WasapBom_Verify extends \WFCO_Call {
    private static $instance = null;
    private $api_end_point = null;

    public function __construct() {
        $this->required_fields = array( 'api_key' );
        $this->api_end_point   = 'https://panel.wasapbom.com/api/whatsapp/instance';
    }

    /**
     * @return WFCO_WasapBom_Verify|null
     */

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get call slug
     *
     * @return string
     */
    public function get_slug(): string
    {
        return 'wfco_wasapbom_verify';
    }

    public function process() {
        $is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
        if ( false === $is_required_fields_present ) {
            return $this->show_fields_error();
        }

        $headers = array(
            'X-API-KEY' => $this->data['api_key']
        );
        $url = $this->api_end_point;

        return $this->make_wp_requests( $url, '', $headers, \BWF_CO::$GET );
    }
}

return 'WFCO_WasapBom_Verify';