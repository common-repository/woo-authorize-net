<?php

/**
 * Woo_AuthorizeNet_AIM class.
 *
 * @extends WC_Payment_Gateway_CC
 */
class Woo_AuthorizeNet_AIM extends WC_Payment_Gateway_CC {

    public $api_request_handler;
    public static $log_enabled = false;
    public static $log = false;

    public function __construct() {
        $this->id = 'woo_authorizenet_aim';
        $this->method_title = __('Authorize.net AIM', 'woo-authorizenet');
        $this->method_description = __('Authorize.net AIM works by adding credit card fields on the checkout and then sending the details to Authorize.net for verification.', 'woo-authorizenet');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->icon = $this->get_option('card_icon', WOO_AUTHORIZENET_ASSET_URL . 'assets/images/wpg_cards.png');
        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
            $this->icon = preg_replace("/^http:/i", "https:", $this->icon);
        }
        $this->icon = apply_filters('woocommerce_woo_authorizenet_aim_icon', $this->icon);
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode', "no") === "yes" ? true : false;
        if ($this->testmode) {
            $this->api_login_id = $this->get_option('test_api_login_id');
            $this->api_transaction_key = $this->get_option('test_api_transaction_key');
        } else {
            $this->api_login_id = $this->get_option('api_login_id');
            $this->api_transaction_key = $this->get_option('api_transaction_key');
        }
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        self::$log_enabled = $this->debug;
        $this->send_items = $this->get_option('send_items', "no") === "yes" ? true : false;
        $this->invoice_prefix = $this->get_option('invoice_prefix');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        try {
            $this->form_fields = include( 'settings-authorizenet-aim.php' );
        } catch (Exception $ex) {
            
        }
    }

    public function is_available() {
        if ($this->enabled === "yes") {
            return true;
        }
        return false;
    }

    public function payment_fields() {
        if (!empty($this->description)) {
            echo '<p>' . wp_kses_post($this->description);
        }
        if ($this->testmode == true) {
            echo '<p>';
            _e('NOTICE: SANDBOX (TEST) MODE ENABLED.', 'woo-authorizenet');
            echo '<br />';
            _e('For testing purposes you can use the card number 4111111111111111 with any CVC and a valid expiration date.', 'woo-authorizenet');
            echo '</p>';
        }
        parent::payment_fields();
    }

    private function get_posted_card() {
        $card_number = isset($_POST['woo_authorizenet_aim-card-number']) ? wc_clean($_POST['woo_authorizenet_aim-card-number']) : '';
        $card_cvc = isset($_POST['woo_authorizenet_aim-card-cvc']) ? wc_clean($_POST['woo_authorizenet_aim-card-cvc']) : '';
        $card_expiry = isset($_POST['woo_authorizenet_aim-card-expiry']) ? wc_clean($_POST['woo_authorizenet_aim-card-expiry']) : '';
        $card_number = str_replace(array(' ', '-'), '', $card_number);
        $card_expiry = array_map('trim', explode('/', $card_expiry));
        $card_exp_month = str_pad($card_expiry[0], 2, "0", STR_PAD_LEFT);
        $card_exp_year = isset($card_expiry[1]) ? $card_expiry[1] : '';
        if (strlen($card_exp_year) == 2) {
            $card_exp_year += 2000;
        }
        return (object) array(
                    'number' => $card_number,
                    'type' => '',
                    'cvc' => $card_cvc,
                    'exp_month' => $card_exp_month,
                    'exp_year' => $card_exp_year
        );
    }

    public function validate_fields() {
        try {
            $card = $this->get_posted_card();
            if (empty($card->exp_month) || empty($card->exp_year)) {
                throw new Exception(__('Card expiration date is invalid', 'woo-authorizenet'));
            }
            if (!ctype_digit($card->cvc)) {
                throw new Exception(__('Card security code is invalid (only digits are allowed)', 'woo-authorizenet'));
            }
            if (!ctype_digit($card->exp_month) || !ctype_digit($card->exp_year) || $card->exp_month > 12 || $card->exp_month < 1 || $card->exp_year < date('y')) {
                throw new Exception(__('Card expiration date is invalid', 'woo-authorizenet'));
            }
            if (empty($card->number) || !ctype_digit($card->number)) {
                throw new Exception(__('Card number is invalid', 'woo-authorizenet'));
            }
            return true;
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return false;
        }
    }

    public function init_request_api() {
        try {
            include_once( WOO_AUTHORIZENET_PLUGIN_DIR . '/includes/gateways/AIM/class-woo-authorizenet-aim-api-handler.php' );
            $this->api_request_handler = new Woo_AuthorizeNet_AIM_API_Handler($this);
        } catch (Exception $ex) {
            self::log($ex->getMessage());
        }
    }

    public function process_payment($order_id) {
        $this->init_request_api();
        $order = wc_get_order($order_id);
        $card = $this->get_posted_card();
        self::log('Processing order #' . $order_id);
        return $this->api_request_handler->request_do_direct_payment($order, $card);
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $this->init_request_api();
        self::log('Processing Refund order #' . $order_id);
        return $this->api_request_handler->request_process_refund($order_id, $amount, $reason);
    }

    public static function log($message, $level = 'info') {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'woo_authorizenet_aim'));
        }
    }
}
