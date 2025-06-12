<?php

class WFCO_WasapBom_Send_SMS extends WFCO_Call {

    private static $instance = null;
    private $api_end_point = null;

    public function __construct() {
        $this->required_fields = array( 'api_key', 'number', 'sms_body' );
    }

    /**
     * @return WFCO_WasapBom_Send_SMS|null
     */
    public static function get_instance(): ?WFCO_WasapBom_Send_SMS
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

        $url = "https://panel.wasapbom.com/api/whatsapp/send/text";

        $headers = array(
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->data['api_key']
        );

        $numbers = trim( stripslashes( $this->data['number'] ) );
        $numbers = explode( ',', $numbers );

        $this->data['sms_body'] = BWFAN_Common::decode_merge_tags( $this->data['sms_body'] );

        /** Allow link shorting for message type text */
        $this->data['sms_body'] = apply_filters( 'bwfan_modify_send_message_body', $this->data['sms_body'], $this->data );

        $res = [];
        foreach ( $numbers as $number ) {
            $req_params = array(
                'number' => $number,
                'textMessage' => array(
                    'text' => $this->data['sms_body']
                )
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

return 'WFCO_WasapBom_Send_SMS';