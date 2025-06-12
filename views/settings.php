<?php
$saved_data  = WFCO_Common::$connectors_saved_data;
$old_data    = ( isset( $saved_data[ $this->get_slug() ] ) && is_array( $saved_data[ $this->get_slug() ] ) && count( $saved_data[ $this->get_slug() ] ) > 0 ) ? $saved_data[ $this->get_slug() ] : array();
$api_key   = $old_data['api_key'] ?? '';
?>

<div class="wfco-form-group featured field-input">
    <label for="automation-name"><?php echo esc_html__( 'Enter API Key', 'autonami-automations-connectors' ); ?></label>
    <div class="field-wrap">
        <div class="wrapper">
            <input type="text" name="api_key" placeholder="<?php echo esc_attr__( 'Enter API Key', 'autonami-automations-connectors' ); ?>" class="form-control wfco_wasapbom_api_key" required value="<?php echo esc_attr__( $api_key ); ?>">
        </div>
    </div>
</div>

<div class="wfco-form-groups wfco_form_submit wfco_wasapbom_main_submit">
    <?php
    if ( isset( $old_data['id'] ) && (int) $old_data['id'] > 0 ) {
        ?>
        <input type="hidden" name="edit_nonce" value="<?php echo esc_attr__( wp_create_nonce( 'wfco-connector-edit' ) ); ?>"/>
        <input type="hidden" name="id" value="<?php echo esc_attr__( $old_data['id'] ); ?>"/>
        <input type="hidden" name="wfco_connector" value="<?php echo esc_attr__( $this->get_slug() ); ?>"/>
        <input type="submit" class="wfco_update_btn_style wfco_save_btn_style" name="autoresponderSubmit" value="<?php echo esc_attr__( 'Update', 'autonami-automations-connectors' ); ?>">
    <?php } else { ?>
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr__( wp_create_nonce( 'wfco-connector' ) ); ?>">
        <input type="hidden" name="wfco_connector" value="<?php echo esc_attr__( $this->get_slug() ); ?>"/>
        <input type="submit" class="wfco_save_btn_style" name="autoresponderSubmit" value="<?php echo esc_attr__( 'Save', 'autonami-automations-connectors' ); ?>">
    <?php } ?>
</div>
<div class="wfco_form_response" style="text-align: center;font-size: 15px;margin-top: 10px;"></div>