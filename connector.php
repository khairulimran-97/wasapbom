<?php

class BWFCO_WasapBom extends BWF_CO {
    public static string $api_end_point = "https://panel.wasapbom.com/api/whatsapp/";
    public static $headers = null;
    private static $ins = null;
    public bool $v2 = true;

    public function __construct() {

        /** Connector.php initialization */
        $this->keys_to_track = [
            'api_key'
        ];
        $this->form_req_keys = [
            'api_key'
        ];

        $this->sync          = false;
        $this->connector_url = WFCO_WASAPBOM_PLUGIN_URL;
        $this->dir           = __DIR__;
        $this->nice_name     = __( 'WasapBom', 'funnelkit-wasapbom' );

        $this->autonami_int_slug = 'BWFAN_WasapBom_Integration';

        add_filter( 'wfco_connectors_loaded', array( $this, 'add_card' ) );
        add_action( 'wp_ajax_bwfan_wb_test_message', array( __CLASS__, 'send_message_via_ajax_call' ) );

    }

    public function get_fields_schema(): array
    {
        return array(
            array(
                'id'          => 'api_key',
                'label'       => __( 'API Key', 'wp-marketing-automations-connectors' ),
                'type'        => 'text',
                'class'       => 'bwfan_wasapbom_api_key',
                'placeholder' => __( 'API Key', 'wp-marketing-automations-connectors' ),
                'required'    => true,
                'toggler'     => array(),
            )
        );
    }

    public function get_settings_fields_values(): array
    {
        $saved_data = WFCO_Common::$connectors_saved_data;
        $old_data   = ( isset( $saved_data[ $this->get_slug() ] ) && is_array( $saved_data[ $this->get_slug() ] ) && count( $saved_data[ $this->get_slug() ] ) > 0 ) ? $saved_data[ $this->get_slug() ] : array();
        $vals       = array();
        if ( isset( $old_data['api_key'] ) ) {
            $vals['api_key'] = $old_data['api_key'];
        }

        return $vals;
    }

    /**
     * Get data from the API call, must required function otherwise call
     *
     * @param $data
     *
     * @return array
     */
    protected function get_api_data( $data ): array
    {
        $load_connector = WFCO_Load_Connectors::get_instance();
        $call_class     = $load_connector->get_call( 'wfco_wasapbom_verify' );

        $resp_array = array(
            'api_data' => $data,
            'status'   => 'failed',
            'message'  => __( 'There is a problem verifying your credentials. Confirm entered details.', 'funnelkit-wasapbom' ),
        );

        if ( is_null( $call_class ) ) {
            return $resp_array;
        }

        $payload = array(
            'api_key' => $data['api_key'] ?? ''
        );

        $call_class->set_data( $payload );
        $request = $call_class->process();

        if ( is_array( $request ) && ( 200 === $request['response'] || 304 === $request['response'] ) && isset( $request['body'] ) ) {
            if ( ! isset( $request['body']['success'] ) ) {
                $resp_array['status']  = 'failed';
                $resp_array['message'] = __( 'Undefined API Error', 'funnelkit-wasapbom' );

                return $resp_array;
            }

            if ( $request['body']['success'] === false ) {
                $resp_array['status']  = 'failed';
                $resp_array['message'] = $request['body']['message'] ?? __('Undefined API Error', 'funnelkit-wasapbom');

                return $resp_array;
            }

        } else {
            $resp_array['status']  = 'failed';
            $resp_array['message'] = __( 'Unable to verify credentials', 'funnelkit-wasapbom' );

            return $resp_array;
        }

        $response                         = [];
        $response['status']               = 'success';
        $response['api_data']['api_key']  = $data['api_key'];

        return $response;
    }


    public static function get_instance(): ?BWFCO_WasapBom
    {
        if ( null === self::$ins ) {
            self::$ins = new self();
        }

        return self::$ins;
    }

    public static function set_headers(): void
    {

        $headers = array(
            'Content-Type' => 'application/json'
        );

        self::$headers = $headers;
    }

    public function add_card( $available_connectors ): array
    {
        $available_connectors['autonami']['connectors']['bwfco_wasapbom'] = array(
            'name'            => 'WasapBom',
            'desc'            => __( 'Automate WhatsApp communication for your store – abandoned cart reminders, updates, and follow-ups.', 'funnelkit-wasapbom' ),
            'connector_class' => 'BWFCO_WasapBom',
            'image'           => $this->get_image(),
            'source'          => '',
            'file'            => '',
        );

        return $available_connectors;
    }

    /**
     * Get the logo image URL
     */
    public function get_image(): string
    {
        return WFCO_WASAPBOM_PLUGIN_URL . '/views/wasapbom-logo.svg';
    }

    /**
     * Sending message by ajax request
     */
    public static function send_message_via_ajax_call(): void
    {
        BWFAN_Common::check_nonce();
        $response = self::send_message( true );
        wp_send_json( $response );
    }

    /**
     * sending test message
     */
    public static function send_message( $is_ajax, $data = [] ): array
    {
        BWFAN_PRO_Common::nocache_headers();

        error_log( "send_message function triggered" );
        $result = array(
            'status' => false,
            'msg'    => __( 'Error', 'wp-marketing-automations' ),
        );

        if ( $is_ajax ) {
            $post = $_POST;
        } else {
            $post = $data;
        }

        $sms_to = $post['test_sms_to'] ?? '';
        $sms_to = $post['sms_to'] ?? $sms_to;
        $sms_to = empty( $sms_to ) && isset( $post['data']['test_sms_to'] ) ? $post['data']['test_sms_to'] : $sms_to;
        $sms_to = empty( $sms_to ) && isset( $post['data']['sms_to'] ) ? $post['data']['sms_to'] : $sms_to;
        if ( empty( $sms_to ) ) {
            $result['msg'] = __( 'Phone number can\'t be blank', 'wp-marketing-automations' );

            return $result;
        }

        if ( isset( $post['v'] ) && 2 === absint( $post['v'] ) ) {
            $sms_body = isset( $post['sms_body_textarea'] ) ? stripslashes( $post['sms_body_textarea'] ) : '';

        } else {
            $sms_body = isset( $post['data']['sms_body_textarea'] ) ? stripslashes( $post['data']['sms_body_textarea'] ) : '';

        }
        $data_to_set['number']   = $sms_to;
        $data_to_set['sms_body'] = $sms_body;

        $data_to_set['test'] = true;

        /** @var  $global_settings */
        $global_settings = WFCO_Common::$connectors_saved_data;
        if ( ! array_key_exists( 'bwfco_wasapbom', $global_settings ) ) {
            return array(
                'msg'    => __( 'WasapBom is not connected', 'wp-marketing-automations' ),
                'status' => false,
            );
        }

        $wasapbom_settings = $global_settings['bwfco_wasapbom'];

        $load_connector = WFCO_Load_Connectors::get_instance();
        $call_class     = $load_connector->get_call( 'wfco_wasapbom_send_sms' );

        $data_to_set['api_key'] = $wasapbom_settings['api_key'];

        // is_preview set to true for merge tag before sending data for sms;
        BWFAN_Merge_Tag_Loader::set_data( array(
            'is_preview' => true,
        ) );

        $call_class->set_data( $data_to_set );
        $response = $call_class->process();

        if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
            return array(
                'status' => true,
                'msg'    => __( 'Message sent successfully.', 'wp-marketing-automations' ),
            );
        }

        $message = __( 'Message could not be sent. ', 'funnelkit-wasapbom' );

        if ( isset( $response['body']['message'] ) && $response['body']['success'] === false ) {
            $message = $response['body']['message'];
        } elseif ( isset( $response['body']['message'] ) ) {
            $message = $response['body']['message'];
        } elseif (! empty( $response['bwfan_response'] )) {
            $message = $response['bwfan_response'];
        } elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
            $message = $message . $response['body'][0];
        }

        return array(
            'status' => false,
            'msg'    => $message,
        );
    }

}

WFCO_Load_Connectors::register( 'BWFCO_WasapBom' );