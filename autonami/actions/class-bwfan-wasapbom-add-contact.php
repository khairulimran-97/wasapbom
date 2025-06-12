<?php

class BWFAN_WasapBom_Add_Contact extends BWFAN_Action {

    private static $instance = null;
    private bool $progress = false;

    public function __construct() {
        $this->action_name = __( 'Add Contact', 'funnelkit-wasapbom' );
        $this->action_desc = __( 'This action adds a contact to WasapBom contacts list', 'funnelkit-wasapbom' );
        $this->support_v2  = true;
    }

    /**
     * @return BWFAN_WasapBom_Add_Contact|null
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function make_v2_data( $automation_data, $step_data ): array
    {
        $this->progress = true;

        $data_to_set = array(
            'name'         => isset( $step_data['name'] ) ? BWFAN_Common::decode_merge_tags( $step_data['name'] ) : '',
            'phone_number' => isset( $step_data['phone_number'] ) ? BWFAN_Common::decode_merge_tags( $step_data['phone_number'] ) : '',
            'title'        => isset( $step_data['title'] ) ? BWFAN_Common::decode_merge_tags( $step_data['title'] ) : '',
            'notes'        => isset( $step_data['notes'] ) ? BWFAN_Common::decode_merge_tags( $step_data['notes'] ) : '',
        );

        $data_to_set['api_key'] = isset( $step_data['connector_data']['api_key'] ) ? $step_data['connector_data']['api_key'] : '';

        // Add custom fields (1-10)
        for ( $i = 1; $i <= 10; $i++ ) {
            $field_key = 'custom_field_' . $i;
            $label_key = 'custom_field_' . $i . '_label';

            if ( isset( $step_data[$field_key] ) ) {
                $data_to_set[$field_key] = BWFAN_Common::decode_merge_tags( $step_data[$field_key] );
            }

            if ( isset( $step_data[$label_key] ) ) {
                $data_to_set[$label_key] = BWFAN_Common::decode_merge_tags( $step_data[$label_key] );
            }
        }

        if (isset($automation_data['global']['order_id'])) {
            $data_to_set['order_id'] = $automation_data['global']['order_id'];
        } elseif (isset($automation_data['global']['cart_abandoned_id'])) {
            $data_to_set['cart_abandoned_id'] = $automation_data['global']['cart_abandoned_id'];
        }

        return $data_to_set;
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

        /** Validating connector */
        $load_connector = WFCO_Load_Connectors::get_instance();
        $call_class     = $load_connector->get_call( 'wfco_wasapbom_add_contact' );
        if ( is_null( $call_class ) ) {
            $this->progress = false;

            return array(
                'status'  => 4,
                'message' => __( 'Add Contact call not found', 'funnelkit-wasapbom' ),
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

        $call_class->set_data( $this->data );
        $response = $call_class->process();

        if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
            $this->progress = false;

            return array(
                'status'  => 3,
                'message' => __( 'Contact added successfully.', 'funnelkit-wasapbom' ),
            );
        }

        $message = __( 'Contact could not be added. ', 'funnelkit-wasapbom' );
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

        return array(
            'status'  => $status,
            'message' => $message,
        );
    }

    public function before_executing_task(): void
    {
        add_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
    }

    public function after_executing_task(): void
    {
        remove_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
    }

    public function modify_retry_limit( $retry_data ) {
        $retry_data[] = DAY_IN_SECONDS;

        return $retry_data;
    }

    public function handle_response_v2( $response ) {
        do_action( 'bwfan_add_contact_action_response', $response, $this->data );

        if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
            $this->progress = false;

            return $this->success_message( __( 'Contact added successfully.', 'funnelkit-wasapbom' ) );
        }

        $message = __( 'Contact could not be added. ', 'funnelkit-wasapbom' );

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
     * v2 Method: Get field Schema
     *
     * @return array[]
     */
    public function get_fields_schema(): array
    {
        $fields = [
            [
                'id'          => 'name',
                'label'       => __( "Contact Name", 'funnelkit-wasapbom' ),
                'type'        => 'text',
                'placeholder' => "John Doe",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => true,
            ],
            [
                'id'          => 'phone_number',
                'label'       => __( "Phone Number", 'funnelkit-wasapbom' ),
                'type'        => 'text',
                'placeholder' => "60123456789",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( 'Phone number with country code', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => true,
            ],
            [
                'id'          => 'title',
                'label'       => __( "Title (Optional)", 'funnelkit-wasapbom' ),
                'type'        => 'text',
                'placeholder' => "Mr./Mrs./Dr.",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => false,
            ],
            [
                'id'          => 'notes',
                'label'       => __( "Notes (Optional)", 'funnelkit-wasapbom' ),
                'type'        => 'textarea',
                'placeholder' => "Additional notes about the contact",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => false,
            ],
        ];

        // Add custom fields (1-10)
        for ( $i = 1; $i <= 10; $i++ ) {
            $fields[] = [
                'id'          => 'custom_field_' . $i,
                'label'       => sprintf( __( "Custom Field %d (Optional)", 'funnelkit-wasapbom' ), $i ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => false,
            ];

            $fields[] = [
                'id'          => 'custom_field_' . $i . '_label',
                'label'       => sprintf( __( "Custom Field %d Label (Optional)", 'funnelkit-wasapbom' ), $i ),
                'type'        => 'text',
                'placeholder' => "",
                "class"       => 'bwfan-input-wrapper',
                'tip'         => __( '', 'funnelkit-wasapbom' ),
                "description" => '',
                "required"    => false,
                'toggler'     => [
                    'fields'   => [
                        [
                            'id'    => 'custom_field_' . $i,
                            'value' => '',
                            'compare' => '!='
                        ],
                    ],
                    'relation' => 'AND',
                ],
            ];
        }

        return $fields;
    }
}

return 'BWFAN_WasapBom_Add_Contact';