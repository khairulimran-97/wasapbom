<?php

class WFCO_WasapBom_Send_Media extends WFCO_Call {

    private static $instance = null;
    private $api_end_point = null;

    public function __construct() {
        $this->required_fields = array( 'api_key', 'number', 'media_url', 'mediatype' );
    }

    /**
     * Get call slug
     *
     * @return string
     */
    public function get_slug() {
        return 'wfco_wasapbom_send_media';
    }

    /**
     * Get connector slug
     *
     * @return string
     */
    public function get_connector_slug(): string
    {
        return 'bwfco_wasapbom';
    }

    /**
     * @return WFCO_WasapBom_Send_Media|null
     */
    public static function get_instance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function process() {
        $is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
        if ( false === $is_required_fields_present ) {
            return $this->show_fields_error();
        }

        $url = "https://panel.wasapbom.com/api/whatsapp/send/media";

        $headers = array(
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->data['api_key']
        );

        $numbers = trim( stripslashes( $this->data['number'] ) );
        $numbers = explode( ',', $numbers );

        // Decode merge tags if present
        if ( function_exists( 'BWFAN_Common::decode_merge_tags' ) ) {
            $this->data['media_url'] = BWFAN_Common::decode_merge_tags( $this->data['media_url'] );
            if ( isset( $this->data['caption'] ) ) {
                $this->data['caption'] = BWFAN_Common::decode_merge_tags( $this->data['caption'] );
            }
        }

        $res = [];
        foreach ( $numbers as $number ) {
            $media_message = array(
                'mediatype' => $this->data['mediatype'],
                'media' => $this->data['media_url']
            );

            // Add caption if provided
            if ( !empty( $this->data['caption'] ) ) {
                $media_message['caption'] = $this->data['caption'];
            }

            $req_params = array(
                'number' => $number,
                'mediaMessage' => $media_message
            );

            /** User 2 digit country code passed */
            if ( isset( $this->data['country_code'] ) && ! empty( $this->data['country_code'] ) ) {
                $req_params['number'] = Phone_Numbers::add_country_code( $number, $this->data['country_code'] );
            }

            $str = ['+','-',' '];
            $req_params['number'] = str_replace( $str, '', $req_params['number'] );

            $res = $this->make_wp_requests( $url, wp_json_encode( $req_params ), $headers, BWF_CO::$POST );
        }

        return $res;
    }

}

return 'WFCO_WasapBom_Send_Media';