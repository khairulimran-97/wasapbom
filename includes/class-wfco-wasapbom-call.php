<?php

abstract class WFCO_WasapBom_Call extends WFCO_Call {
    /**
     * In case of only api_key, no need to declare the construct in the child call class.
     * If more than api_key, then use construct and required fields in child call class.
     *
     * @param array $required_fields
     */
    public function __construct(array $required_fields = array( 'api_key' ) ) {
        $this->required_fields = $required_fields;
    }

    /** Abstract functions that must be present in child's call class */
    abstract function process_wasapbom_call();

    abstract function get_endpoint( $endpoint_var = '' );

    /** Required fields handling is done here, Also process_wasapbom_call must be implemented in child call class */
    public function process() {
        $is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
        if ( false === $is_required_fields_present ) {
            return $this->get_autonami_error( $this->show_fields_error()['body'][0] );
        }

        BWFCO_WasapBom::set_headers( $this->data['api_key'] );

        return $this->process_wasapbom_call();
    }
}