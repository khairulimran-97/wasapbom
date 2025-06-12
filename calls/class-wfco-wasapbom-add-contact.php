<?php

class WFCO_WasapBom_Add_Contact extends WFCO_Call {

    private static $instance = null;
    private $api_end_point = null;

    public function __construct() {
        $this->required_fields = array( 'api_key', 'name', 'phone_number' );
    }

    /**
     * Get call slug
     *
     * @return string
     */
    public function get_slug() {
        return 'wfco_wasapbom_add_contact';
    }

    /**
     * Get connector slug
     *
     * @return string
     */
    public function get_connector_slug() {
        return 'bwfco_wasapbom';
    }

    /**
     * @return WFCO_WasapBom_Add_Contact|null
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

        $url = "https://panel.wasapbom.com/api/whatsapp/contacts";

        $headers = array(
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->data['api_key']
        );

        // Decode merge tags if present
        if ( function_exists( 'BWFAN_Common::decode_merge_tags' ) ) {
            $this->data['name'] = BWFAN_Common::decode_merge_tags( $this->data['name'] );
            $this->data['phone_number'] = BWFAN_Common::decode_merge_tags( $this->data['phone_number'] );

            if ( isset( $this->data['title'] ) ) {
                $this->data['title'] = BWFAN_Common::decode_merge_tags( $this->data['title'] );
            }
            if ( isset( $this->data['notes'] ) ) {
                $this->data['notes'] = BWFAN_Common::decode_merge_tags( $this->data['notes'] );
            }
        }

        // Clean phone number
        $str = ['+','-',' '];
        $this->data['phone_number'] = str_replace( $str, '', $this->data['phone_number'] );

        /** User 2 digit country code passed */
        if ( isset( $this->data['country_code'] ) && ! empty( $this->data['country_code'] ) ) {
            if ( class_exists( 'Phone_Numbers' ) ) {
                $this->data['phone_number'] = Phone_Numbers::add_country_code( $this->data['phone_number'], $this->data['country_code'] );
            }
        }

        $req_params = array(
            'name' => $this->data['name'],
            'phone_number' => $this->data['phone_number']
        );

        // Add optional fields if they exist
        if ( !empty( $this->data['title'] ) ) {
            $req_params['title'] = $this->data['title'];
        }

        if ( !empty( $this->data['notes'] ) ) {
            $req_params['notes'] = $this->data['notes'];
        }

        // Add custom fields (1-10)
        for ( $i = 1; $i <= 10; $i++ ) {
            $field_key = 'custom_field_' . $i;
            $label_key = 'custom_field_' . $i . '_label';

            if ( !empty( $this->data[$field_key] ) ) {
                $req_params[$field_key] = BWFAN_Common::decode_merge_tags( $this->data[$field_key] );

                if ( !empty( $this->data[$label_key] ) ) {
                    $req_params[$label_key] = BWFAN_Common::decode_merge_tags( $this->data[$label_key] );
                }
            }
        }

        // Make the API request
        $response = $this->make_wp_requests( $url, wp_json_encode( $req_params ), $headers, BWF_CO::$POST );

        // Handle "Contact already exists" as success case
        if ( isset( $response['response'] ) && $response['response'] === 409 &&
            isset( $response['body']['error'] ) && $response['body']['error'] === 'Contact already exists' ) {

            // Create a success response using the existing contact data
            $success_response = array(
                'response' => 200,
                'body' => array(
                    'success' => true,
                    'message' => 'Contact already exists',
                    'contact' => isset( $response['body']['contact'] ) ? $response['body']['contact'] : null
                ),
                'headers' => isset( $response['headers'] ) ? $response['headers'] : array()
            );

            return $success_response;
        }

        return $response;
    }

}

return 'WFCO_WasapBom_Add_Contact';