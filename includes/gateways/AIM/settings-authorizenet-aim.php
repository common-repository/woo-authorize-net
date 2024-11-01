<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Authorize.net AIM Gateway.
 */
return $this->form_fields = array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'woo-authorizenet'),
        'label' => __('Enable Authorize.net AIM', 'woo-authorizenet'),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no'
    ),
    'title' => array(
        'title' => __('Title', 'woo-authorizenet'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woo-authorizenet'),
        'default' => __('Credit card (Authorize.net)', 'woo-authorizenet'),
        'desc_tip' => true
    ),
    'description' => array(
        'title' => __('Description', 'woo-authorizenet'),
        'type' => 'text',
        'description' => __('This controls the description which the user sees during checkout.', 'woo-authorizenet'),
        'default' => __('Pay with your credit card via Authorize.net.', 'woo-authorizenet'),
        'desc_tip' => true
    ),
    'testmode' => array(
        'title' => __('Test Mode', 'woo-authorizenet'),
        'label' => __('Enable Sandbox/Test Mode', 'woo-authorizenet'),
        'type' => 'checkbox',
        'description' => __('Place the payment gateway in development mode.', 'woo-authorizenet'),
        'default' => 'no',
        'desc_tip' => true
    ),
    'api_login_id' => array(
        'title' => __('API Login ID', 'woo-authorizenet'),
        'type' => 'text',
        'desc_tip' => __('Your Authorize.Net API Login ID', 'woo-authorizenet'),
    ),
    'api_transaction_key' => array(
        'title' => __('API Transaction Key', 'woo-authorizenet'),
        'type' => 'password',
        'desc_tip' => __('Your Authorize.Net API Transaction Key', 'woo-authorizenet'),
    ),
    'test_api_login_id' => array(
        'title' => __('Test API Login ID', 'woo-authorizenet'),
        'type' => 'text',
        'desc_tip' => __('Your test Authorize.Net API Login ID', 'woo-authorizenet'),
    ),
    'test_api_transaction_key' => array(
        'title' => __('Test API Transaction Key', 'woo-authorizenet'),
        'type' => 'password',
        'desc_tip' => __('Your test Authorize.Net API Transaction Key', 'woo-authorizenet'),
    ),
    'invoice_prefix' => array(
        'title' => __('Invoice Prefix', 'woo-authorizenet'),
        'type' => 'text',
        'description' => __('Please enter a prefix for your invoice numbers.', 'woo-authorizenet'),
        'default' => 'WC_',
        'desc_tip' => true,
    ),
    'debug' => array(
        'title' => __('Debug', 'woo-authorizenet'),
        'type' => 'checkbox',
        'label' => sprintf(__('Enable logging<code>%s</code>', 'woo-authorizenet'), version_compare(WC_VERSION, '3.0', '<') ? wc_get_log_file_path('woo_authorizenet_aim') : WC_Log_Handler_File::get_log_file_path('woo_authorizenet_aim')),
        'default' => 'yes'
    )
);
