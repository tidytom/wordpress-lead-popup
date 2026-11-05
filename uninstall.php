<?php
/**
 * Uninstall handler - runs when the plugin is deleted from WP Admin.
 * Removes all options created by the plugin.
 *
 * @package LeadPopup
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$lead_popup_options = array(
	'lead_popup_web3_key',
	'lead_popup_pdf_url',
	'lead_popup_frequency',
	'lead_popup_bg_color',
	'lead_popup_delay_seconds',
	'lead_popup_privacy_text',
	'lead_popup_heading',
	'lead_popup_checkbox_label',
	'lead_popup_trigger_text',
);

foreach ( $lead_popup_options as $lead_popup_opt ) {
	delete_option( $lead_popup_opt );
}
