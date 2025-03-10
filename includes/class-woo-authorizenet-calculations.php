<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woo_AuthorizeNet_AIM_Calculations')) {

    class Woo_AuthorizeNet_AIM_Calculations {

        public $order_total;
        public $taxamt;
        public $shippingamt;
        public $order_items;
        public $itemamt;
        public $zdp_currencies = array('HUF', 'JPY', 'TWD');
        public $payment;
        public $discount_amount;
        public $decimals;
        public $is_adjust;
        public $payment_method;
        public $temp_total;
        public $is_separate_discount;

        public function __construct($payment_method = null) {
            $this->is_adjust = false;
            $this->payment_method = $payment_method;
            $is_zdp_currency = in_array(get_woocommerce_currency(), $this->zdp_currencies);
            if ($is_zdp_currency) {
                $this->decimals = 0;
            } else {
                $this->decimals = 2;
            }
        }

        public function cart_calculation() {
            if (!defined('WOOCOMMERCE_CHECKOUT')) {
                define('WOOCOMMERCE_CHECKOUT', true);
            }
            if (!defined('WOOCOMMERCE_CART')) {
                define('WOOCOMMERCE_CART', true);
            }
            $desc = '';
            WC()->cart->calculate_totals();
            $this->payment = array();
            $this->itemamt = 0;
            $this->order_items = array();
            $roundedPaymentTotal = 0;
            $this->discount_amount = round(WC()->cart->get_cart_discount_total(), $this->decimals);
            if ($this->get_giftcard_amount() != false) {
                $this->discount_amount = round($this->discount_amount + $this->get_giftcard_amount(), $this->decimals);
            }
            if (WC()->cart->has_discount() && $this->discount_amount == 0) {
                $applied_coupons = WC()->cart->get_applied_coupons();
                if (!empty($applied_coupons)) {
                    foreach ($applied_coupons as $code) {
                        $coupon = new WC_Coupon($code);
                        if (version_compare(WC_VERSION, '3.0', '<')) {
                            $coupon_amount = (!empty($coupon->amount) ) ? $coupon->amount : 0;
                            $this->discount_amount = round($this->discount_amount + $coupon_amount, $this->decimals);
                        } else {
                            $coupon_amount = $coupon->get_amount();
                            $this->discount_amount = round($this->discount_amount + $coupon_amount, $this->decimals);
                        }
                    }
                }
            }
            foreach (WC()->cart->cart_contents as $cart_item_key => $values) {
                $amount = round($values['line_subtotal'] / $values['quantity'], $this->decimals);
                if (version_compare(WC_VERSION, '3.0', '<')) {
                    $product = $values['data'];
                    $name = $values['data']->post->post_title;
                } else {
                    $product = $values['data'];
                    $name = $product->get_title();
                }
                if (is_object($product)) {
                    if ($product->is_type('variation')) {
                        if (version_compare(WC_VERSION, '3.0', '<')) {
                            $attributes = $product->get_variation_attributes();
                            if (!empty($attributes) && is_array($attributes)) {
                                foreach ($attributes as $key => $value) {
                                    $key = str_replace(array('attribute_pa_', 'attribute_'), '', $key);
                                    $desc .= ' ' . ucwords($key) . ': ' . $value;
                                }
                                $desc = trim($desc);
                            }
                        } else {
                            $attributes = $product->get_attributes();
                            if (!empty($attributes) && is_array($attributes)) {
                                foreach ($attributes as $key => $value) {
                                    $desc .= ' ' . ucwords($key) . ': ' . $value;
                                }
                            }
                            $desc = trim($desc);
                        }
                    }
                }
                $product_sku = null;
                if (is_object($product)) {
                    $product_sku = $product->get_sku();
                }
                $item = array(
                    'name' => html_entity_decode(wc_trim_string($name ? $name : __('Item', 'woo-authorizenet'), 127), ENT_NOQUOTES, 'UTF-8'),
                    'desc' => html_entity_decode(wc_trim_string($desc, 127), ENT_NOQUOTES, 'UTF-8'),
                    'qty' => $values['quantity'],
                    'amt' => woo_authorizenet_number_format($amount),
                    'number' => $product_sku
                );
                $this->order_items[] = $item;
                $roundedPaymentTotal += round($amount * $values['quantity'], $this->decimals);
            }

            $this->taxamt = round(WC()->cart->tax_total + WC()->cart->shipping_tax_total, $this->decimals);
            $this->shippingamt = round(WC()->cart->shipping_total, $this->decimals);
            $this->itemamt = round(WC()->cart->cart_contents_total, $this->decimals) + $this->discount_amount;

            foreach (WC()->cart->get_fees() as $cart_item_key => $fee_values) {
                $fee_item = array(
                    'name' => html_entity_decode(wc_trim_string($fee_values->name ? $fee_values->name : __('Fee', 'woo-authorizenet'), 127), ENT_NOQUOTES, 'UTF-8'),
                    'desc' => '',
                    'qty' => 1,
                    'amt' => woo_authorizenet_number_format($fee_values->amount),
                    'number' => ''
                );
                $this->order_items[] = $fee_item;
                $roundedPaymentTotal += round($fee_values->amount * 1, $this->decimals);
                $this->itemamt += $fee_values->amount;
            }

            $this->order_total = round($this->itemamt + $this->taxamt + $this->shippingamt, $this->decimals);
            if ($this->itemamt == $this->discount_amount) {
                unset($this->order_items);
                $this->itemamt -= $this->discount_amount;
                $this->order_total -= $this->discount_amount;
            } else {
                if ($this->discount_amount > 0) {
                    $discLineItem = array(
                        'name' => 'Discount',
                        'desc' => 'Discount Amount',
                        'qty' => 1,
                        'number' => '',
                        'amt' => '-' . woo_authorizenet_number_format($this->discount_amount)
                    );
                    $this->order_items[] = $discLineItem;
                }
                $this->itemamt -= $this->discount_amount;
                $this->order_total -= $this->discount_amount;
            }
            if (!is_numeric($this->shippingamt)) {
                $this->shippingamt = 0;
            }
            $this->cart_re_calculate();
            $this->payment['itemamt'] = woo_authorizenet_number_format(round($this->itemamt, $this->decimals));
            $this->payment['taxamt'] = woo_authorizenet_number_format(round($this->taxamt, $this->decimals));
            $this->payment['shippingamt'] = woo_authorizenet_number_format(round($this->shippingamt, $this->decimals));
            $this->payment['order_items'] = $this->order_items;
            $this->payment['discount_amount'] = woo_authorizenet_number_format(round($this->discount_amount, $this->decimals));
            if ($this->taxamt < 0 || $this->shippingamt < 0) {
                $this->payment['is_calculation_mismatch'] = true;
            } else {
                $this->payment['is_calculation_mismatch'] = false;
            }
            return $this->payment;
        }

        public function order_calculation($order_id) {
            $this->payment = array();
            $order = wc_get_order($order_id);
            $this->itemamt = 0;
            $this->discount_amount = 0;
            $this->order_items = array();
            $roundedPaymentTotal = 0;
            $this->discount_amount = round($order->get_total_discount(), $this->decimals);
            if ($order->get_discount_total() > 0 && $this->discount_amount == 0) {
                $this->discount_amount = round($order->get_discount_total(), $this->decimals);
            }
            $desc = '';
            foreach ($order->get_items() as $cart_item_key => $values) {
                $product = $order->get_product_from_item($values);
                $product_sku = null;
                if (is_object($product)) {
                    $product_sku = $product->get_sku();
                }
                if (empty($values['name'])) {
                    $name = 'Item';
                } else {
                    $name = $values['name'];
                }
                $amount = round($values['line_subtotal'] / $values['qty'], $this->decimals);
                if (is_object($product)) {
                    if ($product->is_type('variation')) {
                        if (version_compare(WC_VERSION, '3.0', '<')) {
                            $attributes = $product->get_variation_attributes();
                            if (!empty($attributes) && is_array($attributes)) {
                                foreach ($attributes as $key => $value) {
                                    $key = str_replace(array('attribute_pa_', 'attribute_'), '', $key);
                                    $desc .= ' ' . ucwords($key) . ': ' . $value;
                                }
                                $desc = trim($desc);
                            }
                        } else {
                            $attributes = $product->get_attributes();
                            if (!empty($attributes) && is_array($attributes)) {
                                foreach ($attributes as $key => $value) {
                                    $desc .= ' ' . ucwords($key) . ': ' . $value;
                                }
                            }
                            $desc = trim($desc);
                        }
                    }
                }
                $item = array(
                    'name' => html_entity_decode(wc_trim_string($name ? $name : __('Item', 'woo-authorizenet'), 127), ENT_NOQUOTES, 'UTF-8'),
                    'desc' => html_entity_decode(wc_trim_string($desc, 127), ENT_NOQUOTES, 'UTF-8'),
                    'qty' => $values['qty'],
                    'amt' => woo_authorizenet_number_format($amount),
                    'number' => $product_sku,
                );
                $this->order_items[] = $item;
                $roundedPaymentTotal += round($amount * $values['qty'], $this->decimals);
            }
            foreach ($order->get_fees() as $cart_item_key => $fee_values) {
                $fee_item_name = version_compare(WC_VERSION, '3.0', '<') ? $fee_values['name'] : $fee_values->get_name();
                $amount = $order->get_line_total($fee_values);
                $fee_item = array(
                    'name' => html_entity_decode(wc_trim_string($fee_item_name ? $fee_item_name : __('Fee', 'woo-authorizenet'), 127), ENT_NOQUOTES, 'UTF-8'),
                    'desc' => '',
                    'qty' => 1,
                    'amt' => woo_authorizenet_number_format($amount),
                    'number' => ''
                );
                $this->order_items[] = $fee_item;
                $roundedPaymentTotal += round($amount * 1, $this->decimals);
            }
            $this->taxamt = round($order->get_total_tax(), $this->decimals);
            $this->shippingamt = round(( version_compare(WC_VERSION, '3.0', '<') ? $order->get_total_shipping() : $order->get_shipping_total()), $this->decimals);
            $this->itemamt = round($order->get_subtotal(), $this->decimals);
            $this->order_total = round($this->itemamt + $this->taxamt + $this->shippingamt, $this->decimals);
            if ($this->itemamt == $this->discount_amount) {
                unset($this->order_items);
                $this->itemamt -= $this->discount_amount;
                $this->order_total -= $this->discount_amount;
            } else {
                if ($this->is_separate_discount == false) {
                    if ($this->discount_amount > 0) {
                        $discLineItem = array(
                            'name' => 'Discount',
                            'desc' => 'Discount Amount',
                            'number' => '',
                            'qty' => 1,
                            'amt' => '-' . woo_authorizenet_number_format($this->discount_amount)
                        );
                        $this->order_items[] = $discLineItem;
                        $this->itemamt -= $this->discount_amount;
                        $this->order_total -= $this->discount_amount;
                    }
                }
            }
            if (!is_numeric($this->shippingamt)) {
                $this->shippingamt = 0;
            }
            $this->order_re_calculate($order);
            $this->payment['itemamt'] = woo_authorizenet_number_format(round($this->itemamt, $this->decimals));
            $this->payment['taxamt'] = woo_authorizenet_number_format(round($this->taxamt, $this->decimals));
            $this->payment['shippingamt'] = woo_authorizenet_number_format(round($this->shippingamt, $this->decimals));
            $this->payment['order_items'] = $this->order_items;
            $this->payment['discount_amount'] = woo_authorizenet_number_format(round($this->discount_amount, $this->decimals));
            if ($this->taxamt < 0 || $this->shippingamt < 0) {
                $this->payment['is_calculation_mismatch'] = true;
            } else {
                $this->payment['is_calculation_mismatch'] = false;
            }
            return $this->payment;
        }

        public function cart_re_calculate() {
            $temp_roundedPaymentTotal = 0;
            if (!empty($this->order_items) && is_array($this->order_items)) {
                foreach ($this->order_items as $key => $values) {
                    $temp_roundedPaymentTotal += round($values['amt'] * $values['qty'], $this->decimals);
                }
            }
            $this->itemamt = round($temp_roundedPaymentTotal, $this->decimals);
            if ($this->is_separate_discount == true) {
                $this->temp_total = round($this->itemamt + $this->taxamt + $this->shippingamt - $this->discount_amount, $this->decimals);
            } else {
                $this->temp_total = round($this->itemamt + $this->taxamt + $this->shippingamt, $this->decimals);
            }
            if (round(WC()->cart->total, $this->decimals) != $this->temp_total) {
                $cartItemAmountDifference = round(WC()->cart->total, $this->decimals) - $this->temp_total;
                if ($this->shippingamt > 0) {
                    $this->shippingamt += round($cartItemAmountDifference, $this->decimals);
                } elseif ($this->taxamt > 0) {
                    $this->taxamt += round($cartItemAmountDifference, $this->decimals);
                } else {
                    if (count($this->order_items) == 1 && (!empty($this->order_items[0]['qty']) && $this->order_items[0]['qty'] > 1 )) {
                        unset($this->order_items);
                        $this->order_total = WC()->cart->total;
                        $this->itemamt = WC()->cart->total;
                    } else {
                        foreach ($this->order_items as $key => $value) {
                            if ($value['qty'] == 1 && $this->is_adjust == false) {
                                $this->order_items[$key]['amt'] = $this->order_items[$key]['amt'] + round($cartItemAmountDifference, $this->decimals);
                                $this->order_total += round($cartItemAmountDifference, $this->decimals);
                                $this->itemamt += round($cartItemAmountDifference, $this->decimals);
                                $this->is_adjust = true;
                            }
                        }
                        if ($this->is_adjust == false) {
                            unset($this->order_items);
                            $this->order_total = WC()->cart->total;
                            $this->itemamt = WC()->cart->total;
                        }
                    }
                }
            }
        }

        public function order_re_calculate($order) {
            $temp_roundedPaymentTotal = 0;
            if (!empty($this->order_items) && is_array($this->order_items)) {
                foreach ($this->order_items as $key => $values) {
                    $temp_roundedPaymentTotal += round($values['amt'] * $values['qty'], $this->decimals);
                }
            }
            $this->itemamt = $temp_roundedPaymentTotal;
            if ($this->is_separate_discount == true) {
                $this->temp_total = round($this->itemamt + $this->taxamt + $this->shippingamt - $this->discount_amount, $this->decimals);
            } else {
                $this->temp_total = round($this->itemamt + $this->taxamt + $this->shippingamt, $this->decimals);
            }
            if (round($order->get_total(), $this->decimals) != $this->temp_total) {
                $cartItemAmountDifference = round($order->get_total(), $this->decimals) - $this->temp_total;
                if ($this->shippingamt > 0) {
                    $this->shippingamt += round($cartItemAmountDifference, $this->decimals);
                } elseif ($this->taxamt > 0) {
                    $this->taxamt += round($cartItemAmountDifference, $this->decimals);
                } else {
                    if (count($this->order_items) == 1 && (!empty($this->order_items[0]['qty']) && $this->order_items[0]['qty'] > 1 )) {
                        unset($this->order_items);
                        $this->order_total = WC()->cart->total;
                        $this->itemamt = WC()->cart->total;
                    } else {
                        foreach ($this->order_items as $key => $value) {
                            if ($value['qty'] == 1 && $this->is_adjust == false) {
                                $this->order_items[$key]['amt'] = $this->order_items[$key]['amt'] + round($cartItemAmountDifference, $this->decimals);
                                $this->order_total += round($cartItemAmountDifference, $this->decimals);
                                $this->itemamt += round($cartItemAmountDifference, $this->decimals);
                                $this->is_adjust = true;
                            }
                        }
                        if ($this->is_adjust == false) {
                            unset($this->order_items);
                            $this->order_total = WC()->cart->total;
                            $this->itemamt = WC()->cart->total;
                        }
                    }
                }
            }
        }

        public function get_giftcard_amount($order_id = null) {
            if (class_exists('WPR_Giftcard')) {
                if (!empty(WC()->session->giftcard_post)) {
                    $giftCards = WC()->session->giftcard_post;
                    $giftcard = new WPR_Giftcard();
                    $price = $giftcard->wpr_get_payment_amount();
                    return $price;
                } else {
                    $giftCardPayment = get_post_meta($order_id, 'rpgc_payment', true);
                    if (!empty($giftCardPayment) && is_array($giftCardPayment)) {
                        return $giftCardPayment[count($giftCardPayment) - 1];
                    }
                    return $giftCardPayment;
                }
            } else {
                return false;
            }
        }

    }

}