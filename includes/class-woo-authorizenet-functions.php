<?php

function woo_authorizenet_get_posted_card($payment_method) {
    $card_number = isset($_POST[$payment_method . '-card-number']) ? wc_clean($_POST[$payment_method . '-card-number']) : '';
    $card_cvc = isset($_POST[$payment_method . '-card-cvc']) ? wc_clean($_POST[$payment_method . '-card-cvc']) : '';
    $card_expiry = isset($_POST[$payment_method . '-card-expiry']) ? wc_clean($_POST[$payment_method . '-card-expiry']) : '';
    $card_number = str_replace(array(' ', '-'), '', $card_number);
    $card_expiry = array_map('trim', explode('/', $card_expiry));
    $card_exp_month = str_pad($card_expiry[0], 2, "0", STR_PAD_LEFT);
    $card_exp_year = isset($card_expiry[1]) ? $card_expiry[1] : '';
    if (strlen($card_exp_year) == 2) {
        $card_exp_year += 2000;
    }
    $first_four = substr($card_number, 0, 4);
    return (object) array(
                'number' => $card_number,
                'type' => woo_authorizenet_card_type_from_account_number($first_four),
                'cvc' => $card_cvc,
                'exp_month' => $card_exp_month,
                'exp_year' => $card_exp_year,
    );
}

function is_woo_authorizenet_credit_supported() {
    if (substr(get_option("woocommerce_default_country"), 0, 2) == 'US' || substr(get_option("woocommerce_default_country"), 0, 2) == 'GB') {
        return true;
    } else {
        return false;
    }
}

function woo_authorizenet_card_type_from_account_number($account_number) {
    $types = array(
        'visa' => '/^4/',
        'mastercard' => '/^5[1-5]/',
        'amex' => '/^3[47]/',
        'discover' => '/^(6011|65|64[4-9]|622)/',
        'diners' => '/^(36|38|30[0-5])/',
        'jcb' => '/^35/',
        'maestro' => '/^(5018|5020|5038|6304|6759|676[1-3])/',
        'laser' => '/^(6706|6771|6709)/',
    );
    foreach ($types as $type => $pattern) {
        if (1 === preg_match($pattern, $account_number)) {
            return $type;
        }
    }
    return null;
}

function woo_authorizenet_round($price, $order) {
    $precision = 2;
    if (!woo_authorizenet_currency_has_decimals(version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency())) {
        $precision = 0;
    }
    return round($price, $precision);
}

function woo_authorizenet_number_format($price) {
    $decimals = 2;
    if (!woo_authorizenet_currency_has_decimals(get_woocommerce_currency())) {
        $decimals = 0;
    }
    return number_format($price, $decimals, '.', '');
}

function woo_authorizenet_currency_has_decimals($currency) {
    if (in_array($currency, array('HUF', 'JPY', 'TWD'))) {
        return false;
    }
    return true;
}

function woo_authorizenet_set_session($key, $value) {
    if (!function_exists('WC')) {
        return false;
    }
    if (sizeof(WC()->session) == 0) {
        return false;
    }
    $woo_authorizenet_session = WC()->session->get('woo_authorizenet_session');
    if (!is_array($woo_authorizenet_session)) {
        $woo_authorizenet_session = array();
    }
    $woo_authorizenet_session[$key] = $value;
    WC()->session->set('woo_authorizenet_session', $woo_authorizenet_session);
}

function woo_authorizenet_get_session($key) {
    if (!function_exists('WC')) {
        return false;
    }
    if (sizeof(WC()->session) == 0) {
        return false;
    }
    $woo_authorizenet_session = WC()->session->get('woo_authorizenet_session');
    if (!empty($woo_authorizenet_session[$key])) {
        return $woo_authorizenet_session[$key];
    }
    return false;
}

function woo_authorizenet_unset_session($key) {
    if (!function_exists('WC')) {
        return false;
    }
    if (sizeof(WC()->session) == 0) {
        return false;
    }
    $woo_authorizenet_session = WC()->session->get('woo_authorizenet_session');
    if (!empty($woo_authorizenet_session[$key])) {
        unset($woo_authorizenet_session[$key]);
        WC()->session->set('woo_authorizenet_session', $woo_authorizenet_session);
    }
}

function is_woo_authorizenet_express_checkout_ready_to_capture() {
    $TOKEN = woo_authorizenet_get_session('TOKEN');
    $PAYERID = woo_authorizenet_get_session('PAYERID');
    if (!empty($TOKEN) && !empty($PAYERID)) {
        return true;
    } else {
        return false;
    }
}

function is_woo_authorizenet_payment_method_saved() {
    if (is_user_logged_in()) {
        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'woo-authorizenet');
        if (sizeof($tokens) > 0) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function woo_authorizenet_maybe_clear_session_data() {
    if (!function_exists('WC')) {
        return false;
    }
    if (sizeof(WC()->session) == 0) {
        return false;
    }
    WC()->session->set('woo_authorizenet_session', '');
}

function woo_authorizenet_get_option($getwoo_authorizenety_name, $key, $default = false) {
    if (!empty($getway_name)) {
        $gateway_key = 'woocommerce_' . $getway_name . '_settings';
        $setting_value = get_option($gateway_key);
        if (!empty($setting_value)) {
            $value = !empty($setting_value[$key]) ? $setting_value[$key] : $default;
            return $value;
        }
    }
    return false;
}

function is_woo_authorizenet_cart_contains_pre_order() {
    if (class_exists('WC_Pre_Orders_Cart')) {
        if (WC_Pre_Orders_Cart::cart_contains_pre_order()) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function is_woo_authorizenet_pre_order_activated() {
    if (class_exists('WC_Pre_Orders_Order')) {
        return true;
    } else {
        return false;
    }
}

function is_woo_authorizenet_cart_contains_subscription() {
    $cart_contains_subscription = false;
    if (class_exists('WC_Subscriptions_Order') && class_exists('WC_Subscriptions_Cart')) {
        $cart_contains_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
    }
    return $cart_contains_subscription;
}

function is_woo_authorizenet_subscription_activated() {
    if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
        return true;
    } else {
        return false;
    }
}

function woo_authorizenet_is_token_exist($gateway_id, $user_id, $token) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE gateway_id = %s AND user_id = %s AND token = %s", $gateway_id, $user_id, $token ));
}
