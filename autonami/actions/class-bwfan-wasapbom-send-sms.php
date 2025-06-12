<?php

class BWFAN_WasapBom_Send_SMS extends BWFAN_Action {

    private static $instance = null;
    private bool $progress = false;

    public function __construct() {
        $this->action_name = __( 'Send Message', 'funnelkit-wasapbom' );
        $this->action_desc = __( 'This action sends a message via WasapBom', 'funnelkit-wasapbom' );
        $this->support_v2  = true;
    }

    /**
     * @return BWFAN_WasapBom_Send_SMS|null
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function load_hooks(): void
    {
        add_filter( 'bwfan_modify_send_message_body', [ $this, 'shorten_link' ], 15, 1 );
    }

    public function make_v2_data( $automation_data, $step_data ): array
    {
        $this->add_action();
        $this->progress = true;
        $sms_body       = isset( $step_data['sms_body_textarea'] ) ? $step_data['sms_body_textarea'] : '';

        $data_to_set = array(
            'name'            => BWFAN_Common::decode_merge_tags( '{{customer_first_name}}' ),
            'promotional_sms' => ( isset( $step_data['promotional_sms'] ) ) ? 1 : 0,
            'append_utm'      => ( isset( $step_data['bwfan_bg_add_utm_params'] ) ) ? 1 : 0,
            'number'          => ( isset( $step_data['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $step_data['sms_to'] ) : '',
            'phone'           => ( isset( $step_data['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $step_data['sms_to'] ) : '',
            'event'           => ( isset( $step_data['event_data'] ) && isset( $step_data['event_data']['event_slug'] ) ) ? $step_data['event_data']['event_slug'] : '',
            'sms_body'        => BWFAN_Common::decode_merge_tags( $sms_body ),
        );

        $data_to_set['api_key'] = isset( $step_data['connector_data']['api_key'] ) ? $step_data['connector_data']['api_key'] : '';

        if (! empty( $step_data['sms_utm_source'] )) {
            $data_to_set['utm_source'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_source'] );
        }
        if (! empty( $step_data['sms_utm_medium'] )) {
            $data_to_set['utm_medium'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_medium'] );
        }
        if (! empty( $step_data['sms_utm_campaign'] )) {
            $data_to_set['utm_campaign'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_campaign'] );
        }
        if (! empty( $step_data['sms_utm_term'] )) {
            $data_to_set['utm_term'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_term'] );
        }

        if (isset($automation_data['global']['order_id'])) {
            $data_to_set['order_id'] = $automation_data['global']['order_id'];
        } elseif (isset($automation_data['global']['cart_abandoned_id'])) {
            $data_to_set['cart_abandoned_id'] = $automation_data['global']['cart_abandoned_id'];
        }

        /** If promotional checkbox is not checked, then empty the {{unsubscribe_link}} merge tag */
        if ( isset( $data_to_set['promotional_sms'] ) && 0 === absint( $data_to_set['promotional_sms'] ) ) {
            $data_to_set['sms_body'] = str_replace( '{{unsubscribe_link}}', '', $data_to_set['sms_body'] );
        }

        $data_to_set['sms_body'] = stripslashes( $data_to_set['sms_body'] );

        /** Append UTM and Create Conversation (Engagement Tracking) */
        $data_to_set['sms_body'] = BWFAN_Connectors_Common::modify_sms_body( $data_to_set['sms_body'], $data_to_set );

        $this->remove_action();

        return $data_to_set;
    }

    private function add_action(): void
    {
        add_filter( 'bwfan_order_billing_address_separator', [ $this, 'change_br_to_slash_n' ] );
        add_filter( 'bwfan_order_shipping_address_separator', [ $this, 'change_br_to_slash_n' ] );
    }

    private function remove_action(): void
    {
        remove_filter( 'bwfan_order_billing_address_params', [ $this, 'change_br_to_slash_n' ] );
        remove_filter( 'bwfan_order_shipping_address_separator', [ $this, 'change_br_to_slash_n' ] );
    }

    public function shorten_link( $body ): array|string|null
    {
        if ( true === $this->progress ) {
            $body = preg_replace_callback( '/((\w+:\/\/\S+)|(\w+[\.:]\w+\S+))[^\s,\.]/i', array( $this, 'shorten_urls' ), $body );
        }

        return preg_replace_callback( '/((\w+:\/\/\S+)|(\w+[\.:]\w+\S+))[^\s,\.]/i', array( $this, 'unsubscribe_url_with_mode' ), $body );
    }

    public function execute_action( $action_data ): array
    {
        global $wpdb;
        $this->set_data( $action_data['processed_data'] );
        $this->data['task_id'] = $action_data['task_id'];

        /** Attaching track id */
        $sql_query         = 'Select meta_value FROM {table_name} WHERE bwfan_task_id = %d AND meta_key = %s';
        $sql_query         = $wpdb->prepare( $sql_query, $this->data['task_id'], 't_track_id' ); //phpcs:ignore WordPress.DB.PreparedSQL
        $gids              = BWFAN_Model_Taskmeta::get_results( $sql_query );
        $this->data['gid'] = '';
        if ( ! empty( $gids ) && is_array( $gids ) ) {
            foreach ( $gids as $gid ) {
                $this->data['gid'] = $gid['meta_value'];
            }
        }

        /** Validating promotional sms */
        if ( 1 === absint( $this->data['promotional_sms'] ) && ( false === apply_filters( 'bwfan_force_promotional_sms', false, $this->data ) ) ) {
            $where             = array(
                'recipient' => $this->data['phone'],
                'mode'      => 2,
            );
            $check_unsubscribe = BWFAN_Model_Message_Unsubscribe::get_message_unsubscribe_row( $where );

            if ( ! empty( $check_unsubscribe ) ) {
                $this->progress = false;

                return array(
                    'status'  => 4,
                    'message' => __( 'User is already unsubscribed', 'funnelkit-wasapbom' ),
                );
            }
        }

        /** Validating connector */
        $load_connector = WFCO_Load_Connectors::get_instance();
        $call_class     = $load_connector->get_call( 'wfco_wasapbom_send_sms' );
        if ( is_null( $call_class ) ) {
            $this->progress = false;

            return array(
                'status'  => 4,
                'message' => __( 'Send SMS call not found', 'funnelkit-wasapbom' ),
            );
        }

        $integration              = BWFAN_WasapBom_Integration::get_instance();
        $this->data['api_key']    = $integration->get_settings( 'api_key' );

        /** WC order case */
        if ( ! empty( $this->data['order_id'] ) ) {
            $order_details = wc_get_order( $this->data['order_id'] );

            /** Appending country code */
            $country = $order_details->get_billing_country();
            if ( ! empty( $country ) ) {
                $this->data['country_code'] = $country;
            }
        } elseif ( ! empty( $this->data['cart_abandoned_id'] ) ) {
            /** Cart abandonment case */
            $cart_details = BWFAN_Merge_Tag_Loader::get_data( 'cart_details' );

            /** Appending country code in case available */
            $checkout_data = json_decode( $cart_details['checkout_data'], true );
            if ( is_array( $checkout_data ) && isset( $checkout_data['fields'] ) && isset( $checkout_data['fields']['billing_country'] ) && ! empty( $checkout_data['fields']['billing_country'] ) ) {
                $this->data['country_code'] = $checkout_data['fields']['billing_country'];
            }
        }

        /** Create Conversation */
        $automation_id = ! empty( $this->data['automation_id'] ) ? $this->data['automation_id'] : 0;
        $conversation  = $this->create_engagement( $this->data['number'], $automation_id, $this->data['sms_body'] );
        if ( $conversation instanceof BWFAN_Engagement_Tracking ) {
            $this->data['sms_body'] = $this->add_tracking_code( $conversation, $this->data['sms_body'] );
        }

        $call_class->set_data( $this->data );
        $response = $call_class->process();
        if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
            $this->progress = false;

            return array(
                'status'  => 3,
                'message' => __( 'Message sent successfully.', 'funnelkit-wasapbom' ),
            );
        }

        $message = __( 'Message could not be sent. ', 'funnelkit-wasapbom' );
        $status  = 4;

        if ( isset( $response['body']['success'] ) && $response['body']['success'] === false ) {
            if ( isset( $response['body']['message'] ) ) {
                $message .= $response['body']['message'];
            }
        } elseif (! empty( $response['bwfan_response'] )) {
            $message = $response['bwfan_response'];
        } elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
            $message = $message . $response['body'][0];
        }
        $this->progress = false;

        if ( $conversation instanceof BWFAN_Engagement_Tracking ) {
            BWFCRM_Core()->conversation->fail_the_conversation( $conversation->get_id(), $message );
        }

        return array(
            'status'  => $status,
            'message' => $message,
        );
    }

    public function add_unsubscribe_query_args( $link ) {
        if ( empty( $this->data ) ) {
            return $link;
        }
        if ( isset( $this->data['number'] ) ) {
            $link = add_query_arg( array(
                'subscriber_recipient' => $this->data['number'],
            ), $link );
        }
        if ( isset( $this->data['name'] ) ) {
            $link = add_query_arg( array(
                'subscriber_name' => $this->data['name'],
            ), $link );
        }

        return $link;
    }

    public function skip_name_email(): bool
    {
        return true;
    }

    public function before_executing_task(): void
    {
        add_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
        add_filter( 'bwfan_unsubscribe_link', array( $this, 'add_unsubscribe_query_args' ) );
        add_filter( 'bwfan_skip_name_email_from_unsubscribe_link', array( $this, 'skip_name_email' ) );
    }

    public function after_executing_task(): void
    {
        remove_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
        remove_filter( 'bwfan_unsubscribe_link', array( $this, 'add_unsubscribe_query_args' ) );
        remove_filter( 'bwfan_skip_name_email_from_unsubscribe_link', array( $this, 'skip_name_email' ) );
    }

    public function modify_retry_limit( $retry_data ) {
        $retry_data[] = DAY_IN_SECONDS;

        return $retry_data;
    }

    public function change_br_to_slash_n(): string
    {
        return "\n";
    }

    protected function shorten_urls( $matches ): string
    {
        $string = $matches[0];

        /**
         * method exist check is required here as it is outside the connector plugin
         * same is not required for the connector inside the connector plugin
         */
        if ( method_exists( 'BWFAN_Connectors_Common', 'get_shorten_url' ) ) {
            return BWFAN_Connectors_Common::get_shorten_url( $string );
        }

        return do_shortcode( '[bwfan_bitly_shorten]' . $string . '[/bwfan_bitly_shorten]' );
    }

    protected function create_engagement( $phone, $automation_id, $body ) {
        if ( empty( $body ) || empty( $automation_id ) ) {
            return false;
        }

        $contact = BWFCRM_Common::get_contact_by_email_or_phone( $phone );
        if ( ! $contact instanceof BWFCRM_Contact || ! $contact->is_contact_exists() ) {
            return false;
        }

        /** 1 for Text Only */
        $template_id = BWFCRM_Core()->conversation->get_or_create_template( 1, '', $body );

        $conversation = new BWFAN_Engagement_Tracking();
        $conversation->set_oid( absint( $automation_id ) );
        $conversation->set_mode( BWFAN_Email_Conversations::$MODE_WHATSAPP );
        $conversation->set_contact( $contact );
        $conversation->set_send_to( $contact->contact->get_contact_no() );
        $conversation->enable_tracking();
        $conversation->set_type( BWFAN_Email_Conversations::$TYPE_AUTOMATION );
        $conversation->set_template_id( $template_id );
        $conversation->set_status( BWFAN_Email_Conversations::$STATUS_SEND );
        $conversation->add_merge_tags_from_string( $body, array() );

        if ( ! $conversation->save() ) {
            return false;
        }

        return $conversation;
    }

    /**
     * @param BWFAN_Engagement_Tracking $conversation
     * @param string $body
     */
    protected function add_tracking_code( $conversation, $body ) {
        $utm  = BWFAN_UTM_Tracking::get_instance();
        $body = $utm->maybe_add_utm_parameters( $body, $this->data );
        $body = BWFAN_Core()->conversations->add_tracking_code( $body, $this->data, $conversation->get_hash(), $conversation->get_oid(), true, BWFAN_Email_Conversations::$MODE_SMS );

        return $body;
    }

    public function handle_response_v2( $response ) {
        do_action( 'bwfan_sendsms_action_response', $response, $this->data );

        if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
            $this->progress = false;

            return $this->success_message( __( 'Message sent successfully.', 'funnelkit-wasapbom' ) );
        }

        $message = __( 'Message could not be sent. ', 'funnelkit-wasapbom' );

        if ( ( isset( $response['body']['success'] ) && $response['body']['success'] === false )  && isset( $response['body']['message'] ) ) {
            $message = $response['body']['message'];
        } elseif ( isset( $response['bwfan_response'] ) && ! empty( $response['bwfan_response'] ) ) {
            $message = $response['bwfan_response'];
        } elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
            $message = $message . $response['body'][0];
        }
        $this->progress = false;

        return $this->error_response( $message );
    }

    /**
     * adding mode in unsubscribe link
     *
     * @param $matches
     *
     * @return string
     */
    protected function unsubscribe_url_with_mode( $matches ): string
    {
        $string = $matches[0];

        /** if its a unsubscriber link then pass the mode in url */
        if ( strpos( $string, 'unsubscribe' ) !== false ) {
            $string = add_query_arg( array(
                'mode' => 2,
            ), $string );
        }

        return $string;
    }

    /**
     * v2 Method: Get field Schema
     *
     * @return array[]
     */
    public function get_fields_schema(): array
    {

        return [
            [
                'id'          => 'sms_to',
                'label'       => __( "To", 'wp-marketing-automations' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => true,
            ],
            [
                'id'          => 'sms_body_textarea',
                'label'       => __( "Text Message", 'wp-marketing-automations' ),
                'type'        => 'textarea',
                'placeholder' => "Message Body",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
            ],
            [
                'id'          => 'test_sms_to',
                'label'       => __( "Send Test Message", 'wp-marketing-automations-connectors' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => '',
                "description" => '',
                "hint"        => __( 'Enter Mobile no with country code', 'wp-marketing-automations-connectors' ),
                "required"    => false,
            ],
            [
                'id'          => 'send_whatsapp_message',
                'type'        => 'send_data',
                'label'       => '',
                'send_action' => 'bwfan_wb_test_message',
                'send_field'  => [
                    'test_sms_to'       => 'test_sms_to',
                    'sms_body_textarea' => 'sms_body_textarea',
                    'sms_provider'		=> 'bwfco_wasapbom'
                ],
                "hint"        => __( "", 'wp-marketing-automations-connectors' )
            ],
            [
                'id'            => 'promotional_sms',
                'checkboxlabel' => __( "Mark as Promotional", 'wp-marketing-automations' ),
                'type'          => 'checkbox',
                "class"         => '',
                'hint'          => __( 'SMS marked as promotional will not be send to the unsubscribers.', 'wp-marketing-automations' ),
                'description'   => __( 'SMS marked as promotional will not be send to the unsubscribers.', 'funnelkit-wasapbom' ),
                "required"      => false,
            ],
            [
                'id'            => 'bwfan_bg_add_utm_params',
                'checkboxlabel' => __( " Add UTM parameters to the links", 'wp-marketing-automations' ),
                'type'          => 'checkbox',
                "class"         => '',
                'hint'          => 'Add UTM parameters in all the links present in the sms.',
                'description'   => __( 'Add UTM parameters in all the links present in the sms.', 'funnelkit-wasapbom' ),
                "required"      => false,
            ],
            [
                'id'          => 'sms_utm_source',
                'label'       => __( "UTM Source", 'wp-marketing-automations' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => '',
                "description" => __( '', 'funnelkit-wasapbom' ),
                "required"    => false,
                'toggler'     => array(
                    'fields'   => array(
                        array(
                            'id'    => 'bwfan_bg_add_utm_params',
                            'value' => true,
                        ),
                    ),
                    'relation' => 'AND',
                ),
            ],
            [
                'id'          => 'sms_utm_medium',
                'label'       => __( "UTM Medium", 'wp-marketing-automations' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => '',
                "description" => __( '', 'funnelkit-wasapbom' ),
                "required"    => false,
                'toggler'     => array(
                    'fields'   => array(
                        array(
                            'id'    => 'bwfan_bg_add_utm_params',
                            'value' => true,
                        ),
                    ),
                    'relation' => 'AND',
                ),
            ],
            [
                'id'          => 'sms_utm_campaign',
                'label'       => __( "UTM Campaign", 'wp-marketing-automations' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => '',
                "description" => __( '', 'funnelkit-wasapbom' ),
                "required"    => false,
                'toggler'     => array(
                    'fields'   => array(
                        array(
                            'id'    => 'bwfan_bg_add_utm_params',
                            'value' => true,
                        ),
                    ),
                    'relation' => 'AND',
                ),
            ],
            [
                'id'          => 'utm_utm_term',
                'label'       => __( "UTM Term", 'wp-marketing-automations' ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => '',
                "description" => __( '', 'funnelkit-wasapbom' ),
                "required"    => false,
                'toggler'     => array(
                    'fields'   => array(
                        array(
                            'id'    => 'bwfan_bg_add_utm_params',
                            'value' => true,
                        ),
                    ),
                    'relation' => 'AND',
                ),
            ],
        ];
    }
}

return 'BWFAN_WasapBom_Send_SMS';