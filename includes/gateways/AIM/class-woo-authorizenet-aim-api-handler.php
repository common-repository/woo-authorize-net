<?php

if (!defined('ABSPATH')) {
    exit;
}

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
define("AUTHORIZENET_LOG_FILE", version_compare(WC_VERSION, '3.0', '<') ? wc_get_log_file_path('woo_authorizenet_aim') : WC_Log_Handler_File::get_log_file_path('woo_authorizenet_aim'));

class Woo_AuthorizeNet_AIM_API_Handler {
    public $gateway;
    public $pre_wc_30;
    public $gateway_calculation;
    public $request;
    public $request_name;
    public $response;
    public $mask_request;
    public $order_item;
    public $result;
    public $order;
    public $order_id;
    public $invoice_number;
    public $invoice_id_prefix;
    public $order_status;
    public $refund_amount;
    public $refund_reason;
    public $paymentaction;
    public $card;
    public $transaction_id;
    public $api_aim;
    public $tresponse;
    public $end_point;
    public $solution_id;

    /**
     * Authorize.Net Request object
     */
    public function __construct($gateway) {
        try {
            $this->gateway = $gateway;
            $this->pre_wc_30 = version_compare(WC_VERSION, '3.0', '<');
            if (!class_exists('Woo_AuthorizeNet_AIM_Calculations')) {
                require_once( WOO_AUTHORIZENET_PLUGIN_DIR . '/includes/class-woo-authorizenet-calculations.php' );
            }
            include_once( WOO_AUTHORIZENET_PLUGIN_DIR . '/includes/lib/sdk-php-master/vendor/autoload.php' );
            $this->api_aim = new AuthorizeNetAIM($this->gateway->api_login_id, $this->gateway->api_transaction_key);
            if ($this->gateway->testmode) {
                $this->api_aim->setSandbox(true);
                $this->end_point = "https://apitest.authorize.net";
                $this->solution_id = 'AAA100302';
            } else {
                $this->end_point = 'https://api2.authorize.net';
                $this->api_aim->setSandbox(false);
                $this->solution_id = 'AAA172579';
            }
            $this->gateway_calculation = new Woo_AuthorizeNet_AIM_Calculations($this->gateway);
        } catch (Exception $ex) {
            $ex;
        }
    }

    public function woo_authorizenet_do_direct_payment_request_param() {
        try {
            $this->order_id = version_compare(WC_VERSION, '3.0', '<') ? $this->order->id : $this->order->get_id();
            $this->invoice_number = preg_replace("/[^a-zA-Z0-9]/", "", $this->order->get_order_number());
            $this->order_cart_data = $this->gateway_calculation->order_calculation($this->order_id);
            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($this->gateway->api_login_id);
            $merchantAuthentication->setTransactionKey($this->gateway->api_transaction_key);
            $refId = 'ref' . time();
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($this->card->number);
            $creditCard->setExpirationDate($this->card->exp_year . '-' . $this->card->exp_month);
            $creditCard->setCardCode($this->card->cvc);
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);
            $order_obj = new AnetAPI\OrderType();
            $order_obj->setInvoiceNumber($this->gateway->invoice_prefix . $this->invoice_number);
            $order_obj->setDescription(get_bloginfo('blogname') . ' Order #' . $this->order->get_order_number());
            $customerAddress = new AnetAPI\CustomerAddressType();
            $customerAddress->setFirstName($this->pre_wc_30 ? $this->order->billing_first_name : $this->order->get_billing_first_name());
            $customerAddress->setLastName($this->pre_wc_30 ? $this->order->billing_last_name : $this->order->get_billing_last_name());
            $customerAddress->setCompany($this->pre_wc_30 ? $this->order->billing_company : $this->order->get_billing_company());
            $customerAddress->setAddress($this->pre_wc_30 ? trim($this->order->billing_address_1 . ' ' . $this->order->billing_address_2) : trim($this->order->get_billing_address_1() . ' ' . $this->order->get_billing_address_2()));
            $customerAddress->setCity($this->pre_wc_30 ? $this->order->billing_city : $this->order->get_billing_city());
            $customerAddress->setState($this->pre_wc_30 ? $this->order->billing_state : $this->order->get_billing_state());
            $customerAddress->setZip($this->pre_wc_30 ? $this->order->billing_postcode : $this->order->get_billing_postcode());
            $customerAddress->setCountry($this->pre_wc_30 ? $this->order->billing_country : $this->order->get_billing_country());
            if ($this->order->needs_shipping_address()) {
                $customer_shipping_address = new AnetAPI\CustomerAddressType();
                $customer_shipping_address->setFirstName($this->pre_wc_30 ? $this->order->shipping_first_name : $this->order->get_shipping_first_name());
                $customer_shipping_address->setLastName($this->pre_wc_30 ? $this->order->shipping_last_name : $this->order->get_shipping_last_name());
                $customer_shipping_address->setCompany($this->pre_wc_30 ? $this->order->shipping_company : $this->order->get_shipping_company());
                $customer_shipping_address->setAddress($this->pre_wc_30 ? trim($this->order->shipping_address_1 . ' ' . $this->order->shipping_address_2) : trim($this->order->get_shipping_address_1() . ' ' . $this->order->get_shipping_address_2()));
                $customer_shipping_address->setCity($this->pre_wc_30 ? $this->order->shipping_city : $this->order->get_shipping_city());
                $customer_shipping_address->setState($this->pre_wc_30 ? $this->order->shipping_state : $this->order->get_shipping_state());
                $customer_shipping_address->setZip($this->pre_wc_30 ? $this->order->shipping_postcode : $this->order->get_shipping_postcode());
                $customer_shipping_address->setCountry($this->pre_wc_30 ? $this->order->shipping_country : $this->order->get_shipping_country());
            }
            $customerData = new AnetAPI\CustomerDataType();
            $customerData->setType("individual");
            $customerData->setId($this->pre_wc_30 ? $this->order->billing_phone : $this->order->get_billing_phone());
            $customerData->setEmail($this->pre_wc_30 ? $this->order->billing_email : $this->order->get_billing_email());
            $duplicateWindowSetting = new AnetAPI\SettingType();
            $duplicateWindowSetting->setSettingName("duplicateWindow");
            $duplicateWindowSetting->setSettingValue("60");
            $headerEmailReceiptSetting = new AnetAPI\SettingType();
            $headerEmailReceiptSetting->setSettingName("headerEmailReceipt");
            $headerEmailReceiptSetting->setSettingValue('Order Receipt ' . get_bloginfo('blogname'));
            $footerEmailReceiptSetting = new AnetAPI\SettingType();
            $footerEmailReceiptSetting->setSettingName("footerEmailReceipt");
            $footerEmailReceiptSetting->setSettingValue('Thank you for Using ' . get_bloginfo('blogname'));
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType("authCaptureTransaction");
            $transactionRequestType->setAmount(woo_authorizenet_number_format($this->order->get_total()));
            $transactionRequestType->setOrder($order_obj);
            $transactionRequestType->setPayment($paymentOne);
            if ($this->order->needs_shipping_address()) {
                $transactionRequestType->setShipTo($customer_shipping_address);
            }
            $transactionRequestType->setBillTo($customerAddress);
            $transactionRequestType->setCustomer($customerData);
            $solution = new AnetAPI\SolutionType();
            $solution->setId($this->solution_id);
            $transactionRequestType->setSolution($solution);
            $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
            $request_obj = new AnetAPI\CreateTransactionRequest();
            $request_obj->setMerchantAuthentication($merchantAuthentication);
            $request_obj->setRefId($refId);
            $request_obj->setTransactionRequest($transactionRequestType);
            $this->request = $request_obj;
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function woo_authorizenet_request() {
        try {
            $this->request = apply_filters('woo_authorizenet_request_param', $this->request);
            switch ($this->request_name) {
                case 'auth_capture_transaction':
                    $controller = new AnetController\CreateTransactionController($this->request);
                    $this->response = $controller->executeWithApiResponse($this->end_point);
                    break;
                case 'refund_transaction':
                    $controller = new AnetController\CreateTransactionController($this->request);
                    $this->response = $controller->executeWithApiResponse($this->end_point);
                    break;
            }
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function woo_authorizenet_response() {
        try {
            if (empty($this->response)) {
                Woo_AuthorizeNet_AIM::log('Empty response!');
                throw new Exception(__('Empty Authorize.Net response.', 'woo-authorizenet'));
            }
            return $this->result = $this->response;
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
            $this->woo_authorizenet_redirect_action(wc_get_cart_url());
        }
    }

    public function woo_authorizenet_response_handler() {
        try {
            if ($this->woo_authorizenet_is_response_success_or_successwithwarning() == true) {
                switch ($this->request_name) {
                    case 'auth_capture_transaction':
                        $this->tresponse = $this->result->getTransactionResponse();
                        if ($this->tresponse != null && $this->tresponse->getMessages() != null) {
                            $this->transaction_id = $this->tresponse->getTransId();
                            update_post_meta($this->order_id, 'Transaction ID', $this->tresponse->getTransId());
                            update_post_meta($this->order_id, '_cc_last4', $this->tresponse->getAccountNumber());
                            $this->order->add_order_note('Transaction ID: ' . $this->tresponse->getTransId());
                            $this->order->add_order_note('Transaction Response Code: ' . $this->tresponse->getResponseCode());
                            $this->order->add_order_note('Message Code: ' . $this->tresponse->getMessages()[0]->getCode());
                            $this->order->add_order_note('Auth Code: ' . $this->tresponse->getAuthCode());
                            $this->order->add_order_note('AVS result Code: ' . $this->tresponse->getAvsResultCode());
                            $this->order->add_order_note('CVV result Code: ' . $this->tresponse->getCvvResultCode());
                            
                            $this->order->add_order_note('Description: ' . $this->tresponse->getMessages()[0]->getDescription());
                            WC()->cart->empty_cart();
                            $this->order->add_order_note(sprintf(__('Payment Status Completed via %s', 'woo-authorizenet'), $this->gateway->title));
                            $this->order->payment_complete($this->transaction_id);
                            $this->woo_authorizenet_redirect_action($this->gateway->get_return_url($this->order));
                        } else {
                            if (function_exists('wc_add_notice')) {
                                $tresponse = $this->tresponse->getTransactionResponse();
                                if ($this->tresponse->getErrors() != null) {
                                    $ERRORCODE = "Error Code  : " . $this->tresponse->getErrors()[0]->getErrorCode() . "<br>";
                                    $ERRORCODE .= "Error Message : " . $this->tresponse->getErrors()[0]->getErrorText() . "</br>";
                                }
                                wc_add_notice($ERRORCODE, 'error');
                                $this->order->add_order_note(print_r($this->tresponse, true));
                                $this->woo_authorizenet_redirect_action(wc_get_cart_url());
                            }
                        }
                        break;
                    case 'refund_transaction':
                        $this->tresponse = $this->result->getTransactionResponse();
                        if ($this->tresponse != null && $this->tresponse->getMessages() != null) {
                            $this->transaction_id = $this->tresponse->getTransId();
                            update_post_meta($this->order_id, 'Refund Transaction ID', $this->tresponse->getTransId());
                            $this->order->add_order_note('Refund Transaction ID: ' . $this->tresponse->getTransId());
                            $this->order->add_order_note('Refund Transaction Response Code: ' . $this->tresponse->getResponseCode());
                            $this->order->add_order_note('Refund Message Code: ' . $this->tresponse->getMessages()[0]->getCode());
                            $this->order->add_order_note('Refund Description: ' . $this->tresponse->getMessages()[0]->getDescription());
                            $this->order->add_order_note(sprintf(__('Payment Status Refund via %s', 'woo-authorizenet'), $this->gateway->title));
                            if (!empty($this->refund_reason)) {
                                $this->order->add_order_note('Refund reason: ' . $this->refund_reason);
                            }
                            return true;
                        } else {
                            $tresponse = $this->tresponse->getTransactionResponse();
                            if ($this->tresponse->getErrors() != null) {
                                $ERRORCODE = "Error Code  : " . $this->tresponse->getErrors()[0]->getErrorCode() . "<br>";
                                $ERRORCODE .= "Error Message : " . $this->tresponse->getErrors()[0]->getErrorText() . "</br>";
                            }
                            $this->order->add_order_note($ERRORCODE);
                        }
                }
            } else {
                if (function_exists('wc_add_notice')) {
                    $tresponse = $this->result->getTransactionResponse();
                    if ($tresponse != null && $tresponse->getErrors() != null) {
                        $ERRORCODE = "Error Code  : " . $tresponse->getErrors()[0]->getErrorCode() . "<br>";
                        $ERRORCODE .= "Error Message : " . $tresponse->getErrors()[0]->getErrorText() . "</br>";
                    } else {
                        $ERRORCODE = "Error Code  : " . $this->result->getMessages()->getMessage()[0]->getCode() . "</br>";
                        $ERRORCODE .= "Error Message : " . $this->result->getMessages()->getMessage()[0]->getText() . "</br>";
                    }
                    $this->order->add_order_note($ERRORCODE);
                    wc_add_notice($ERRORCODE, 'error');
                }
                return false;
            }
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function woo_authorizenet_is_response_success_or_successwithwarning() {
        try {
            if ($this->result->getMessages()->getResultCode() == "Ok") {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function request_do_direct_payment($order, $card) {
        try {
            $this->order = $order;
            $this->card = $card;
            $this->woo_authorizenet_do_direct_payment_request_param();
            //    Woo_AuthorizeNet_AIM::log($this->request);
            $this->request_name = 'auth_capture_transaction';
            $this->woo_authorizenet_request();
            $this->woo_authorizenet_response();
            $this->woo_authorizenet_response_handler();
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function woo_authorizenet_get_transaction_details() {
        if (!empty($this->transaction_id)) {
            $this->request = $this->woo_authorizenet_get_transaction_details_param();
            $this->request_name = 'get_transaction_details';
            $this->woo_authorizenet_request();
            $this->woo_authorizenet_response();
        }
    }

    public function woo_authorizenet_get_transaction_details_param() {
        $post_data = array(
            'VERSION' => $this->gateway->api_version,
            'SIGNATURE' => $this->gateway->api_signature,
            'USER' => $this->gateway->api_username,
            'PWD' => $this->gateway->api_password,
            'METHOD' => 'GetTransactionDetails',
            'TRANSACTIONID' => $this->transaction_id
        );
        return $post_data;
    }

    public function woo_authorizenet_refund_transaction_param() {
        try {
            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($this->gateway->api_login_id);
            $merchantAuthentication->setTransactionKey($this->gateway->api_transaction_key);
            $refId = 'ref' . time();
            $_cc_last4 = get_post_meta($this->order_id, '_cc_last4', true);
            $_cc_last = substr($_cc_last4, -4);
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($_cc_last);
            $creditCard->setExpirationDate("XXXX");
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);
            $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType("refundTransaction");
            $transactionRequest->setAmount($this->refund_amount);
            $transactionRequest->setPayment($paymentOne);
            $solution = new AnetAPI\SolutionType();
            $solution->setId($this->solution_id);
            $transactionRequest->setSolution($solution);
            $transactionRequest->setRefTransId($this->order->get_transaction_id());
            $request_obj = new AnetAPI\CreateTransactionRequest();
            $request_obj->setMerchantAuthentication($merchantAuthentication);
            $request_obj->setRefId($refId);
            $request_obj->setTransactionRequest($transactionRequest);
            $this->request = $request_obj;
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function request_process_refund($order_id, $amount = null, $reason = '') {
        try {
            $this->order_id = $order_id;
            $this->order = wc_get_order($this->order_id);
            $this->refund_amount = $amount;
            $this->refund_reason = $reason;
            $this->transaction_id = $this->order->get_transaction_id();
            $this->woo_authorizenet_refund_transaction_param();
            $this->request_name = 'refund_transaction';
            $this->woo_authorizenet_request();
            $this->woo_authorizenet_response();
            return $this->woo_authorizenet_response_handler();
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function get_user_ip() {
        try {
            return WC_Geolocation::get_ip_address();
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }

    public function woo_authorizenet_redirect_action($url) {
        try {
            if (!empty($url)) {
                if (!is_ajax()) {
                    wp_redirect($url);
                    exit;
                } else {
                    if ($this->request_name == 'auth_capture_transaction') {
                        wp_send_json(array(
                            'result' => 'success',
                            'redirect' => add_query_arg('utm_nooverride', '1', $url)
                        ));
                        exit();
                    } else {
                        wp_send_json(array('url' => $url));
                        exit();
                    }
                }
            }
        } catch (Exception $ex) {
            Woo_AuthorizeNet_AIM::log($ex->getMessage());
        }
    }
}
